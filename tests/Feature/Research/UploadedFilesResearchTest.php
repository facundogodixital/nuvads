<?php

namespace Tests\Feature\Research;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\ResearchRun;
use App\Services\UserService;
use App\Services\BrandService;
use App\Models\KnowledgeSource;
use Illuminate\Http\UploadedFile;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Storage;
use App\Services\KnowledgeSourceService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\UploadedFiles\ResearchUploadedFilesJob;


class UploadedFilesResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchUploadedFilesJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchUploadedFilesJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Pastelería',
            'brand_offer_description' => 'Tortas.',
            'brand_history_description' => 'Desde 1990.',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // Cada archivo subido pasa por el modelo: la foto como imagen y el menú como documento, los dos en base64. El que
    // falla queda marcado y no frena a los demás. El análisis general mezcla solo el campo que los archivos cambian, y
    // la pantalla recibe todos los archivos con su enlace.
    #[Test]
    public function analyzes_each_uploaded_file_and_merges_only_the_brand_fields_they_change(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => $this->fakeOpenAiResponse(...)]);

        $response = $this->post('/api/research-runs', [
            'type' => 'uploaded_files',
            'files' => [
                UploadedFile::fake()->create('torta.jpg', 20, 'image/jpeg'),
                UploadedFile::fake()->create('menu.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('roto.pdf', 20, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json']);
        $researchRun = ResearchRun::query()->findOrFail($response->assertCreated()->json('data.id'));
        Queue::assertPushedOn('research_queue', ResearchUploadedFilesJob::class);
        (new ResearchUploadedFilesJob($researchRun->id))->handle();

        $researchRun->refresh();
        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertCount(2, $researchRun->knowledge_source_ids);
        [$imageContent, $documentContent] = array_map(
            fn (Request $request): array => $request['input'][0]['content'][1],
            array_slice($this->recordedOpenAiRequests(), 0, 2),
        );
        $this->assertStringStartsWith('data:image/jpeg;base64,', $imageContent['image_url']);
        $this->assertSame('menu.pdf', $documentContent['filename']);
        $this->assertStringStartsWith('data:application/pdf;base64,', $documentContent['file_data']);

        $this->assertSame('Tortas, incluida la de chocolate a $5000.', $brand->brand_offer_description);
        $this->assertSame('Desde 1990.', $brand->brand_history_description);

        $uploadedFilesInsights = $this->getJson('/api/knowledge-insights/uploaded-files')->assertOk()->json('data');
        $this->assertSame('La torta de chocolate es la estrella.', $uploadedFilesInsights['insights'][0]['body']);
        $filesByName = array_column($uploadedFilesInsights['files'], null, 'title');
        $this->assertSame('ready', $filesByName['torta.jpg']['status']);
        $imageDescription = $filesByName['torta.jpg']['payload']['description'];
        $this->assertSame('Torta de chocolate sobre fondo blanco.', $imageDescription);
        $this->assertSame('Torta de chocolate $5000', $filesByName['menu.pdf']['payload']['content']);
        $this->assertSame('failed', $filesByName['roto.pdf']['status']);
        $this->assertStringContainsString('signature=', $filesByName['torta.jpg']['url']);
    }


    // Mientras se analizan los archivos no se puede subir ni borrar otro. Borrar uno se lleva su archivo y rehace el
    // análisis con los que quedan, sin tocar la marca aunque el modelo devuelva campos.
    #[Test]
    public function deleting_a_file_redoes_the_analysis_without_it_and_keeps_the_brand(): void
    {
        $deletedFile = $this->createAnalyzedFile('viejo.jpg', 'Una torta vieja.');
        $keptFile = $this->createAnalyzedFile('torta.jpg', 'Torta de chocolate sobre fondo blanco.');
        Http::fake(['https://api.openai.com/v1/responses' => $this->fakeOpenAiResponse(...)]);

        $researchRunId = $this->deleteJson("/api/uploaded-files/{$deletedFile->id}")->assertOk()->json('data.id');
        $this->deleteJson("/api/uploaded-files/{$keptFile->id}")->assertUnprocessable();
        $this->post('/api/research-runs', [
            'type' => 'uploaded_files',
            'files' => [UploadedFile::fake()->create('otra.jpg', 20, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        (new ResearchUploadedFilesJob($researchRunId))->handle();

        $knowledgeSourceService = resolve(KnowledgeSourceService::class);
        $this->assertNull($knowledgeSourceService->find($this->brand, $deletedFile->id));
        Storage::disk('local')->assertMissing($deletedFile->s3_path);
        $filesAnalysisRequest = $this->recordedOpenAiRequests()[0];
        $this->assertStringNotContainsString('viejo.jpg', $filesAnalysisRequest['input']);
        $this->assertStringContainsString('torta.jpg', $filesAnalysisRequest['input']);
        $this->assertSame('Tortas.', $this->brand->fresh()->brand_offer_description);
        $this->getJson('/api/knowledge-insights/uploaded-files')->assertOk()
            ->assertJsonPath('data.analysis.knowledge_source_ids', [$keptFile->id])
            ->assertJsonPath('data.analysis.payload.brand', []);
    }


    private function createAnalyzedFile(string $fileName, string $description): KnowledgeSource
    {
        $storedPath = "uploaded-files/{$this->brand->id}/{$fileName}";
        Storage::disk('local')->put($storedPath, 'imagen');

        return resolve(KnowledgeSourceService::class)->create($this->brand, [
            'type' => 'image',
            'status' => 'ready',
            'title' => $fileName,
            's3_path' => $storedPath,
            'payload' => [
                'file_name' => $fileName,
                'mime_type' => 'image/jpeg',
                'size' => 6,
                'description' => $description,
                'transcription' => null,
            ],
        ]);
    }


    private function recordedOpenAiRequests(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)->map(fn (array $pair): Request => $pair[0])->values()->all();
    }


    // Simula al modelo según lo que recibe. La foto y el menú devuelven lo que muestran; roto.pdf falla en OpenAI. El
    // análisis general cambia solo la oferta de la marca.
    private function fakeOpenAiResponse(Request $request): PromiseInterface
    {
        $hasAttachment = is_array($request['input']);
        if ($hasAttachment) {
            $attachment = $request['input'][0]['content'][1];
            $isImage = $attachment['type'] === 'input_image';
            if ($isImage) {
                return Http::response($this->openAiResponse([
                    'description' => 'Torta de chocolate sobre fondo blanco.',
                    'transcription' => null,
                ]));
            }
            if ($attachment['filename'] === 'roto.pdf') {
                return Http::response(['error' => ['message' => 'The file could not be parsed.']], 400);
            }
            return Http::response($this->openAiResponse([
                'description' => 'Menú con precios.',
                'content' => 'Torta de chocolate $5000',
            ]));
        }

        return Http::response($this->openAiResponse([
            'matches_brand' => true,
            'brand' => [
                'brand_offer_description' => 'Tortas, incluida la de chocolate a $5000.',
                'brand_history_description' => null,
                'brand_customers_description' => null,
                'brand_visual_style_description' => null,
                'brand_customers_faq_description' => null,
                'brand_tone_of_voice_description' => null,
                'brand_differentiators_description' => null,
                'brand_customers_needs_description' => null,
                'brand_communication_topics_description' => null,
                'brand_content_opportunities_description' => null,
                'brand_customers_valued_aspects_description' => null,
            ],
            'summary' => 'Fotos y el menú de una pastelería.',
            'insights' => ['La torta de chocolate es la estrella.'],
        ]));
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}

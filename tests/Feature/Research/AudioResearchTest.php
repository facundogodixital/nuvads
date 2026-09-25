<?php

namespace Tests\Feature\Research;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\ResearchRun;
use App\Services\UserService;
use App\Services\BrandService;
use Illuminate\Http\UploadedFile;
use Database\Factories\UserFactory;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Storage;
use App\Services\KnowledgeSourceService;
use App\Services\KnowledgeInsightService;
use App\Jobs\Research\Audio\ResearchAudioJob;
use Illuminate\Foundation\Testing\RefreshDatabase;


class AudioResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchAudioJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchAudioJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Maderera',
            'brand_offer_description' => 'Madera para construcción.',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // El audio grabado queda en el disco hasta que el job lo transcribe y lo borra; la transcripción se guarda como
    // fuente, y una foto que llega de más en files se descarta. El análisis reemplaza al del audio anterior, y en la
    // marca se guarda solo el campo que el modelo cambió: el que devuelve en null conserva su texto.
    #[Test]
    public function analyzes_the_recorded_audio(): void
    {
        $previousAnalysis = resolve(KnowledgeInsightService::class)->create($this->brand, [
            'type' => 'audio_analysis', 'body' => 'Audio anterior.', 'status' => 'active', 'level' => 1,
        ]);
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => ' Empezamos en 1990 con mi viejo, con un aserradero chico. ',
            ]),
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse([
                'matches_brand' => true,
                'brand' => [
                    'brand_offer_description' => null,
                    'brand_history_description' => 'Empezó en 1990 como un aserradero familiar.',
                    'brand_customers_description' => null,
                    'brand_differentiators_description' => null,
                    'brand_customers_needs_description' => null,
                    'brand_content_opportunities_description' => null,
                ],
                'summary' => 'Cuenta cómo empezó el negocio.',
                'insights' => ['La historia familiar sirve para comunicar.'],
            ])),
        ]);

        $strayPhoto = UploadedFile::fake()->createWithContent('foto.jpg', 'foto');
        $response = $this->post(
            '/api/research-runs',
            ['type' => 'audio', 'audio_file' => $this->audioFile(), 'files' => [$strayPhoto]],
            ['Accept' => 'application/json'],
        );
        $researchRun = ResearchRun::query()->findOrFail($response->assertCreated()->json('data.id'));
        Queue::assertPushedOn('research_queue', ResearchAudioJob::class);
        (new ResearchAudioJob($researchRun->id))->handle();

        $researchRun->refresh();
        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->status);
        Storage::disk('local')->assertMissing($researchRun->input['audio_path']);
        $audio = resolve(KnowledgeSourceService::class)->find($brand, $researchRun->knowledge_source_ids[0]);
        $this->assertSame([$audio->id], resolve(KnowledgeSourceService::class)->list($brand)->modelKeys());
        $this->assertSame('Empezamos en 1990 con mi viejo, con un aserradero chico.', $audio->payload['transcript']);
        $this->assertSame('Empezó en 1990 como un aserradero familiar.', $brand->brand_history_description);
        $this->assertSame('Madera para construcción.', $brand->brand_offer_description);
        $this->assertSame('outdated', $previousAnalysis->fresh()->status);

        $this->getJson('/api/research-runs/audio/status')->assertOk()
            ->assertJsonPath('data.last_completed.id', $researchRun->id);
        $this->getJson('/api/knowledge-insights/audio')->assertOk()
            ->assertJsonPath('data.analysis.body', 'Cuenta cómo empezó el negocio.')
            ->assertJsonPath('data.insights.0.knowledge_source_ids', [$audio->id])
            ->assertJsonPath('data.audio.id', $audio->id);
    }


    // Un audio sin voz no tiene nada para analizar: la investigación termina vacía con un mensaje que lo dice, sin
    // guardar la fuente ni consultar al modelo de análisis, y el análisis del audio anterior sigue vigente.
    #[Test]
    public function ends_empty_without_replacing_the_previous_analysis_when_the_audio_has_no_speech(): void
    {
        $previousAnalysis = resolve(KnowledgeInsightService::class)->create($this->brand, [
            'type' => 'audio_analysis', 'body' => 'Audio anterior.', 'status' => 'active', 'level' => 1,
        ]);
        Http::fake(['https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => ''])]);
        $researchRun = resolve(ResearchRunService::class)->create($this->brand, [
            'type' => 'audio',
            'audio_file' => $this->audioFile(),
        ]);

        (new ResearchAudioJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('empty', $researchRun->status);
        $this->assertStringStartsWith('No escuchamos nada en el audio.', $researchRun->status_message);
        $this->assertSame([], $researchRun->knowledge_source_ids);
        Http::assertSentCount(1);
        $this->getJson('/api/knowledge-insights/audio')->assertOk()
            ->assertJsonPath('data.analysis.id', $previousAnalysis->id);
    }


    // Una grabación webm como la que arma el navegador: basta el encabezado para que se reconozca el formato.
    private function audioFile(): UploadedFile
    {
        $webmHeader = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08"
            ."\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02";
        $audioFilePath = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($audioFilePath, $webmHeader.str_repeat("\0", 64));

        return new UploadedFile($audioFilePath, 'grabacion.webm', 'audio/webm', null, true);
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}

<?php

namespace Tests\Feature\Research;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\ResearchRun;
use App\Services\UserService;
use Illuminate\Support\Sleep;
use App\Services\BrandService;
use App\Exceptions\ApiException;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use App\Services\KnowledgeSourceService;
use App\Services\KnowledgeInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\Instagram\ResearchInstagramJob;


class InstagramResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Sleep::fake();
        config()->set('services.apify.api_key', 'testing-key');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchInstagramJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchInstagramJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Mi marca',
            'instagram_username' => 'mi.marca',
            'brand_visual_style_description' => 'Fotos reales.',
            'brand_tone_of_voice_description' => 'Cercano.',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // El usuario y la cantidad de posteos se toman de la marca y la configuración y quedan congelados en la
    // ejecución; el job se corta antes de que la queue lo dé por perdido.
    #[Test]
    public function starts_with_saved_username_and_a_timeout_below_retry_after(): void
    {
        $this->postJson('/api/research-runs', ['type' => 'instagram'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.input.username', 'mi.marca')
            ->assertJsonPath('data.input.posts_limit', config('research.instagram.posts_limit'));

        $retryAfter = config('queue.connections.database.retry_after');
        Queue::assertPushedOn('research_queue', ResearchInstagramJob::class);
        Queue::assertPushed(
            ResearchInstagramJob::class, fn (ResearchInstagramJob $job): bool => $job->timeout < $retryAfter,
        );
    }


    // Espera a que Apify termine, transcribe cada posteo con todas sus imágenes y los guarda como fuentes, aunque el
    // modelo devuelva más entradas que imágenes; un posteo que falla se saltea. El análisis final recibe el texto
    // actual de la marca: lo mezclado se guarda y lo que vuelve vacío no borra nada. Las métricas ignoran los likes
    // ocultos y los fijados no cuentan para la frecuencia. La pantalla de Instagram lee el estado, el análisis con sus
    // métricas, las conclusiones y los posteos leídos.
    #[Test]
    public function analyzes_the_posts_and_merges_the_brand_fields(): void
    {
        $analysis = $this->analysis(['brand_tone_of_voice_description' => 'Cercano y con humor.']);
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::sequence()
                ->push($this->apifyRun('RUNNING'))
                ->push($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($this->apifyPosts()),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($this->transcription(2)))
                ->push($this->openAiResponse($this->transcription(2)))
                ->push(['error' => ['message' => 'Error while downloading the image.']], 400)
                ->push($this->openAiResponse($analysis)),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchInstagramJob($researchRun->id))->handle();

        $researchRun->refresh();
        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertSame('run1', $researchRun->external_run_id);
        $this->assertCount(2, $researchRun->knowledge_source_ids);

        $knowledgeSourceService = resolve(KnowledgeSourceService::class);
        $image = $knowledgeSourceService->find($brand, $researchRun->knowledge_source_ids[0]);
        $carousel = $knowledgeSourceService->find($brand, $researchRun->knowledge_source_ids[1]);
        $this->assertCount(2, $image->payload['images']);
        $this->assertSame('instagram_post', $carousel->type);
        $this->assertSame('Imagen 2', $carousel->payload['images'][1]['description']);
        $openAiRequests = $this->recordedOpenAiRequests();
        $this->assertSame(
            ['https://cdn.example/carousel-1.jpg', 'https://cdn.example/carousel-2.jpg'],
            $this->sentImageUrls($openAiRequests[1]),
        );
        $analysisInput = $this->decodeOpenAiText($openAiRequests[3]['input']);
        $this->assertSame('Cercano.', $analysisInput['brand']['brand_tone_of_voice_description']);

        $this->assertSame('Cercano y con humor.', $brand->brand_tone_of_voice_description);
        $this->assertSame('Fotos reales.', $brand->brand_visual_style_description);

        $this->getJson('/api/research-runs/instagram/status')->assertOk()
            ->assertJsonPath('data.last_completed.id', $researchRun->id);
        $instagramInsights = $this->getJson('/api/knowledge-insights/instagram')->assertOk()->json('data');
        $metrics = $instagramInsights['analysis']['payload']['metrics'];
        $this->assertEquals(2, $metrics['posts_per_week']);
        $this->assertNull($metrics['formats']['image']['average_likes']);
        $this->assertSame(120, $metrics['formats']['carousel']['average_likes']);
        $this->assertCount(2, $instagramInsights['insights']);
        $this->assertSame($researchRun->knowledge_source_ids, array_column($instagramInsights['posts'], 'id'));
    }


    // Si Apify termina la ejecución sin completarla, la investigación falla sin guardar fuentes ni consultar al
    // modelo.
    #[Test]
    public function marks_the_run_as_failed_when_apify_does_not_complete_the_run(): void
    {
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('FAILED')),
        ]);
        $researchRun = $this->createResearchRun();
        $job = new ResearchInstagramJob($researchRun->id);

        try {
            $job->handle();
            $this->fail('El fallo de Apify debía propagarse.');
        } catch (ApiException $exception) {
            $job->failed($exception);
        }

        $researchRun->refresh();
        $this->assertSame('failed', $researchRun->status);
        $this->assertSame('No se pudo completar el análisis de Instagram.', $researchRun->status_message);
        $this->assertDatabaseCount('knowledge_sources', 0);
        $this->assertSame([], $this->recordedOpenAiRequests());
    }


    // Un perfil sin posteos no tiene nada para analizar: la investigación termina vacía con un mensaje que lo dice, sin
    // consultar al modelo, y el análisis anterior sigue vigente.
    #[Test]
    public function ends_empty_without_replacing_the_previous_analysis_when_the_profile_has_no_posts(): void
    {
        $previousAnalysis = resolve(KnowledgeInsightService::class)->create($this->brand, [
            'type' => 'instagram_analysis', 'body' => 'Análisis anterior.', 'status' => 'active', 'level' => 1,
        ]);
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response([]),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchInstagramJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('empty', $researchRun->status);
        $this->assertNotNull($researchRun->status_message);
        $this->assertSame([], $this->recordedOpenAiRequests());
        $this->assertSame('active', $previousAnalysis->fresh()->status);
    }


    private function createResearchRun(): ResearchRun
    {
        return resolve(ResearchRunService::class)->create($this->brand, ['type' => 'instagram']);
    }


    private function recordedOpenAiRequests(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)->map(fn (array $pair): Request => $pair[0])->values()->all();
    }


    private function sentImageUrls(Request $request): array
    {
        $content = collect($request['input'][0]['content']);

        return $content->where('type', 'input_image')->pluck('image_url')->all();
    }


    // El helper de OpenAI agrega un recordatorio de JSON al final del texto; se decodifica solo el objeto.
    private function decodeOpenAiText(string $text): array
    {
        $jsonObject = substr($text, 0, strrpos($text, '}') + 1);

        return json_decode($jsonObject, true);
    }


    private function apifyRun(string $status): array
    {
        return ['data' => ['id' => 'run1', 'status' => $status, 'defaultDatasetId' => 'dataset1']];
    }


    // Una imagen fijada y vieja con los likes ocultos, un carrusel de dos imágenes y un reel, con una semana
    // entre estos dos últimos.
    private function apifyPosts(): array
    {
        return [
            [
                'type' => 'Image',
                'url' => 'https://www.instagram.com/p/image/',
                'caption' => 'Llegaron los sustratos',
                'displayUrl' => 'https://cdn.example/image.jpg',
                'images' => [],
                'likesCount' => -1,
                'commentsCount' => 4,
                'timestamp' => '2024-01-01T12:00:00.000Z',
                'isPinned' => true,
            ],
            [
                'type' => 'Sidecar',
                'url' => 'https://www.instagram.com/p/carousel/',
                'caption' => 'Cómo trasplantar',
                'displayUrl' => 'https://cdn.example/carousel-1.jpg',
                'images' => ['https://cdn.example/carousel-1.jpg', 'https://cdn.example/carousel-2.jpg'],
                'likesCount' => 120,
                'commentsCount' => 10,
                'timestamp' => '2026-09-08T12:00:00.000Z',
                'isPinned' => false,
            ],
            [
                'type' => 'Video',
                'url' => 'https://www.instagram.com/p/reel/',
                'caption' => null,
                'displayUrl' => 'https://cdn.example/reel.jpg',
                'images' => [],
                'likesCount' => 300,
                'commentsCount' => 20,
                'timestamp' => '2026-09-15T12:00:00.000Z',
                'isPinned' => false,
            ],
        ];
    }


    // Respuesta de la transcripción de un posteo, con la cantidad indicada de entradas numeradas.
    private function transcription(int $entriesCount): array
    {
        $images = [];
        for ($imageNumber = 1; $imageNumber <= $entriesCount; $imageNumber++) {
            $images[] = ['transcription' => null, 'description' => "Imagen {$imageNumber}"];
        }

        return ['images' => $images];
    }


    // Respuesta del análisis final con los cinco campos en null, salvo los indicados.
    private function analysis(array $brandValues): array
    {
        $brand = [
            'brand_tone_of_voice_description' => null,
            'brand_visual_style_description' => null,
            'brand_communication_topics_description' => null,
            'brand_customers_description' => null,
            'brand_customers_needs_description' => null,
        ];

        return [
            'matches_brand' => true,
            'brand' => [...$brand, ...$brandValues],
            'summary' => 'Cuenta de jardinería.',
            'insights' => ['Los reels tienen más likes.', 'Nunca muestra precios.'],
        ];
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}

<?php

namespace Tests\Feature\Research;

use Mockery;
use Tests\TestCase;
use App\Models\Brand;
use RuntimeException;
use App\Models\ResearchRun;
use App\Services\UserService;
use App\Services\BrandService;
use App\Exceptions\ApiException;
use App\Models\KnowledgeInsight;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use App\Services\KnowledgeSourceService;
use Illuminate\Http\Client\RequestException;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Jobs\Research\Website\ResearchWebsiteJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Dispatchers\ResearchDispatcherService;


class WebsiteResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('services.firecrawl.api_key', 'testing-key');
        config()->set('logging.channels.ResearchWebsiteJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchWebsiteJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Mi marca',
            'website_url' => 'https://example.com',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // La URL se toma de la marca autenticada y queda congelada en la ejecución; solo se admite una activa.
    #[Test]
    public function starts_with_saved_url_and_rejects_a_second_active_run(): void
    {
        $response = $this->postJson('/api/research-runs', [
            'type' => 'website', 'brand_id' => 999, 'input' => ['url' => 'https://other.example'],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.brand_id', $this->brand->id)
            ->assertJsonPath('data.input.url', 'https://example.com');
        $researchRunId = $response->json('data.id');
        resolve(BrandService::class)->update($this->brand, ['website_url' => 'https://changed.example']);

        $this->postJson('/api/research-runs', ['type' => 'website'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->getJson("/api/research-runs/{$researchRunId}")->assertOk()
            ->assertJsonPath('data.input.url', 'https://example.com');
        Queue::assertPushedOn('research_queue', ResearchWebsiteJob::class);
        Queue::assertPushed(ResearchWebsiteJob::class, 1);
        $this->assertDatabaseCount('research_runs', 1);
    }


    // Con la identidad visual completa en Firecrawl y sin enlaces para elegir, alcanza con una consulta: la marca
    // toma colores, fuentes y logo de Firecrawl, y quedan el análisis y una fila por conclusión.
    #[Test]
    public function completes_the_research_with_the_homepage_only(): void
    {
        $insights = ['Tiene local.', 'Envía en el día.'];
        $analysis = $this->analysis(['brand_offer_description' => 'Jardinería'], $insights);
        $branding = $this->firecrawlBranding('https://example.com/logo.png');
        $homepage = $this->firecrawlPage('Vendemos plantas.', [], $branding);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($homepage),
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($analysis)),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $researchRun->refresh();
        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertNotNull($researchRun->started_at);
        $this->assertNotNull($researchRun->finished_at);
        $this->assertCount(1, $researchRun->knowledge_source_ids);
        $this->assertSame('Jardinería', $brand->brand_offer_description);
        $this->assertSame(['https://example.com/logo.png'], $brand->brand_logos);
        $this->assertSame('#3F3D38', $brand->brand_colors['text']);
        // MySQL guarda las claves del JSON en otro orden; se compara el contenido.
        $this->assertEquals(['heading' => 'Lora', 'body' => 'Poppins'], $brand->brand_fonts);
        Http::assertSentCount(2);
        $this->getJson("/api/research-runs/{$researchRun->id}")->assertOk()
            ->assertJsonCount(1, 'data.knowledge_sources')
            ->assertJsonCount(3, 'data.knowledge_insights')
            ->assertJsonPath('data.knowledge_insights.0.type', 'website_brand_analysis')
            ->assertJsonPath('data.knowledge_insights.0.body', 'Vivero online.')
            ->assertJsonPath('data.knowledge_insights.0.knowledge_source_ids', $researchRun->knowledge_source_ids)
            ->assertJsonPath('data.knowledge_insights.0.payload.brand.brand_offer_description', 'Jardinería')
            ->assertJsonPath('data.knowledge_insights.2.type', 'website_insight')
            ->assertJsonPath('data.knowledge_insights.2.body', 'Envía en el día.');
    }


    // Un logo de Firecrawl que no es una URL, como un placeholder en base64, se pide al modelo en la primera
    // consulta; los colores y las fuentes de Firecrawl se conservan.
    #[Test]
    public function asks_the_model_only_for_the_visual_fields_firecrawl_missed(): void
    {
        $placeholderLogo = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        $branding = $this->firecrawlBranding($placeholderLogo);
        // El modelo devuelve también colores, pero no se le pidieron: se descartan.
        $homepageReview = $this->homepageReview([], [
            'brand_logos' => ['https://example.com/logo.png'],
            'brand_colors' => [
                'primary' => '#000000', 'secondary' => null, 'accent' => null, 'background' => null, 'text' => null,
            ],
        ]);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada', [], $branding)),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($homepageReview))
                ->push($this->openAiResponse($this->analysis())),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $brand = $this->brand->fresh();
        $this->assertSame(['brand_logos'], $this->recordedOpenAiInputs()[0]['missing_fields']);
        $this->assertSame(['https://example.com/logo.png'], $brand->brand_logos);
        $this->assertSame('#339D33', $brand->brand_colors['primary']);
    }


    // El modelo elige hasta dos enlaces internos de la portada; se leen y se analizan todas las páginas juntas
    // en una segunda consulta, con el markdown sin URLs ni imágenes.
    #[Test]
    public function reads_the_pages_chosen_by_the_model_and_analyzes_them_together(): void
    {
        $homepageLinks = [
            '/about', '/services', '/about#team', 'https://outside.example/about', 'mailto:a@example.com',
            'https://example.com/',
        ];
        $chosenUrls = ['https://example.com/about', 'https://example.com/services'];
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::sequence()
                ->push($this->firecrawlPage('Portada [Nosotros](/about) ![Logo](/logo.png)', $homepageLinks))
                ->push($this->firecrawlPage('Desde 2010'))
                ->push($this->firecrawlPage('Asesoramiento')),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($this->homepageReview($chosenUrls)))
                ->push($this->openAiResponse($this->analysis(['brand_history_description' => 'Desde 2010']))),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $this->assertSame('completed', $researchRun->fresh()->status);
        $this->assertCount(3, $researchRun->fresh()->knowledge_source_ids);
        $this->assertSame('Desde 2010', $this->brand->fresh()->brand_history_description);
        $this->assertDatabaseCount('knowledge_sources', 3);
        Http::assertSentCount(5);
        $openAiInputs = $this->recordedOpenAiInputs();
        $this->assertSame($chosenUrls, $openAiInputs[0]['available_links']);
        $this->assertCount(3, $openAiInputs[1]['pages']);
        $this->assertSame('Portada Nosotros ', $openAiInputs[1]['pages'][0]['markdown']);
    }


    // El modelo recibe el texto actual de la marca y lo que devuelve mezclado se guarda; lo que vuelve vacío no borra
    // nada. El nombre y la identidad visual solo completan lo vacío, aunque el usuario los haya cargado durante el
    // análisis.
    #[Test]
    public function merges_text_fields_and_only_fills_empty_name_and_visual_identity(): void
    {
        $userColors = [
            'primary' => '#111111', 'secondary' => null, 'accent' => null, 'background' => null, 'text' => null,
        ];
        resolve(BrandService::class)->update($this->brand, [
            'brand_colors' => $userColors,
            'brand_history_description' => 'Mi historia',
            'brand_tone_of_voice_description' => 'Mi voz',
        ]);
        $analysis = $this->analysis([
            'name' => 'Otro nombre',
            'brand_tone_of_voice_description' => '  Mi voz, con humor  ',
        ]);
        $branding = $this->firecrawlBranding('https://example.com/logo.png');
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada', [], $branding)),
            'https://api.openai.com/v1/responses' => function () use ($analysis) {
                // El usuario carga un logo mientras el modelo responde.
                Brand::query()->whereKey($this->brand->id)->update(['brand_logos' => ['https://example.com/mio.png']]);
                return Http::response($this->openAiResponse($analysis));
            },
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $brand = $this->brand->fresh();
        $this->assertSame('Mi voz', $this->recordedOpenAiInputs()[0]['brand']['brand_tone_of_voice_description']);
        $this->assertSame('Mi voz, con humor', $brand->brand_tone_of_voice_description);
        $this->assertSame('Mi historia', $brand->brand_history_description);
        $this->assertSame('Mi marca', $brand->name);
        $this->assertSame(['https://example.com/mio.png'], $brand->brand_logos);
        $this->assertEquals($userColors, $brand->brand_colors);
        $this->assertEquals(['heading' => 'Lora', 'body' => 'Poppins'], $brand->brand_fonts);
    }


    // Un JSON sin ningún valor, como colores todos en null o una lista de logos vacía, se guarda como null.
    #[Test]
    public function stores_null_for_empty_json_values(): void
    {
        $homepageReview = $this->homepageReview([], [
            'brand_logos' => [],
            'brand_colors' => [
                'primary' => null, 'secondary' => null, 'accent' => null, 'background' => null, 'text' => null,
            ],
            'brand_fonts' => ['heading' => null, 'body' => null],
        ]);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($homepageReview))
                ->push($this->openAiResponse($this->analysis())),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->fresh()->status);
        $this->assertNull($brand->brand_logos);
        $this->assertNull($brand->brand_colors);
        $this->assertNull($brand->brand_fonts);
    }


    // Un JSON con otra forma que la fija se rechaza aunque el resto de la respuesta sea válido.
    #[Test]
    #[DataProvider('jsonValuesOutsideTheFixedShape')]
    public function rejects_json_values_outside_the_fixed_shape(array $visualValues): void
    {
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => Http::response(
                $this->openAiResponse($this->homepageReview([], $visualValues)),
            ),
        ]);
        $researchRun = $this->createResearchRun();
        $job = new ResearchWebsiteJob($researchRun->id);

        try {
            $job->handle();
            $this->fail('La forma inválida debía interrumpir la investigación.');
        } catch (ApiException $exception) {
            $this->assertSame('website_analysis_invalid', $exception->errorCode);
        }

        $this->assertDatabaseCount('knowledge_insights', 0);
    }


    public static function jsonValuesOutsideTheFixedShape(): array
    {
        return [
            'logo as text' => [['brand_logos' => 'https://example.com/logo.png']],
            'colors as list' => [['brand_colors' => [['hex' => '#339D33', 'role' => 'primary']]]],
            'colors missing keys' => [['brand_colors' => ['primary' => '#339D33']]],
            'colors with unknown key' => [['brand_colors' => [
                'primary' => '#339D33', 'secondary' => null, 'accent' => null, 'background' => null, 'text' => null,
                'link' => '#000000',
            ]]],
            'invalid hex' => [['brand_colors' => [
                'primary' => 'green', 'secondary' => null, 'accent' => null, 'background' => null, 'text' => null,
            ]]],
            'fonts with extra key' => [['brand_fonts' => [
                'heading' => 'Poppins', 'body' => null, 'paragraph' => 'Arial',
            ]]],
        ];
    }


    // Una selección fuera de los enlaces ofrecidos, o de más de dos, interrumpe la investigación antes
    // de leer más páginas.
    #[Test]
    #[DataProvider('invalidAdditionalUrls')]
    public function rejects_additional_urls_the_model_was_not_offered(array $additionalUrls): void
    {
        $homepageLinks = ['/about', '/services', '/contact'];
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada', $homepageLinks)),
            'https://api.openai.com/v1/responses' => Http::response(
                $this->openAiResponse($this->homepageReview($additionalUrls)),
            ),
        ]);
        $researchRun = $this->createResearchRun();
        $job = new ResearchWebsiteJob($researchRun->id);

        try {
            $job->handle();
            $this->fail('La selección inválida debía interrumpir la investigación.');
        } catch (ApiException $exception) {
            $this->assertSame('website_analysis_invalid', $exception->errorCode);
            $job->failed($exception);
        }

        $this->assertSame('failed', $researchRun->fresh()->status);
        $this->assertDatabaseCount('knowledge_insights', 0);
        Http::assertSentCount(2);
    }


    public static function invalidAdditionalUrls(): array
    {
        return [
            'unknown page' => [['https://example.com/unknown']],
            'external site' => [['https://outside.example/about']],
            'three pages' => [[
                'https://example.com/about', 'https://example.com/services', 'https://example.com/contact',
            ]],
        ];
    }


    // Una investigación nueva pasa a outdated las conclusiones activas anteriores; las corregidas por el usuario
    // (superseded) siguen vigentes.
    #[Test]
    public function outdates_previous_insights_but_keeps_user_corrections(): void
    {
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($this->homepageReview()))
                ->push($this->openAiResponse($this->analysis([], ['Primera', 'Corregida'])))
                ->push($this->openAiResponse($this->homepageReview()))
                ->push($this->openAiResponse($this->analysis([], ['Nueva']))),
        ]);
        $firstResearchRun = $this->createResearchRun();
        (new ResearchWebsiteJob($firstResearchRun->id))->handle();
        $correctedInsight = KnowledgeInsight::query()->where('body', 'Corregida')->first();
        $correctedInsight->update(['status' => 'superseded', 'user_body' => 'Corregida por el usuario']);

        $secondResearchRun = $this->createResearchRun();
        (new ResearchWebsiteJob($secondResearchRun->id))->handle();

        $insightStatuses = KnowledgeInsight::query()->orderBy('id')->get()
            ->map(fn (KnowledgeInsight $insight): string => "{$insight->body}: {$insight->status}")->all();
        $this->assertSame([
            'Vivero online.: outdated',
            'Primera: outdated',
            'Corregida: superseded',
            'Vivero online.: active',
            'Nueva: active',
        ], $insightStatuses);
        $this->getJson('/api/research-runs/website/status')->assertOk()
            ->assertJsonPath('data.active', null)
            ->assertJsonPath('data.last_completed.id', $secondResearchRun->id);
    }


    // Un fallo del proveedor deja la ejecución como fallida, con un mensaje genérico y sin datos parciales.
    #[Test]
    public function marks_the_run_as_failed_when_a_provider_fails(): void
    {
        Http::fake(['https://api.firecrawl.dev/v2/scrape' => Http::response(['error' => 'secret-data'], 500)]);
        $researchRun = $this->createResearchRun();
        $job = new ResearchWebsiteJob($researchRun->id);

        try {
            $job->handle();
            $this->fail('El error del proveedor debía propagarse.');
        } catch (RequestException $exception) {
            $job->failed($exception);
        }

        $researchRun->refresh();
        $this->assertSame('failed', $researchRun->status);
        $this->assertNotNull($researchRun->finished_at);
        $this->assertStringNotContainsString('secret-data', $researchRun->error_message);
        $this->assertDatabaseCount('knowledge_sources', 0);
        $this->getJson('/api/research-runs/website/status')->assertOk()
            ->assertJsonPath('data.latest.status', 'failed')
            ->assertJsonPath('data.active', null);
    }


    // Un error al encolar revierte también la creación, evitando una ejecución pendiente sin job.
    #[Test]
    public function rolls_back_creation_when_dispatch_fails(): void
    {
        $researchDispatcherService = Mockery::mock(ResearchDispatcherService::class);
        $researchDispatcherService->shouldReceive('dispatchResearchWebsiteJob')->once()
            ->andThrow(new RuntimeException('Queue unavailable'));
        $this->app->instance(ResearchDispatcherService::class, $researchDispatcherService);

        $this->postJson('/api/research-runs', ['type' => 'website'])->assertStatus(500);

        $this->assertDatabaseCount('research_runs', 0);
    }


    // Las lecturas y la creación requieren autenticación y nunca exponen ejecuciones de otra marca.
    #[Test]
    public function isolates_runs_and_validates_the_requested_source(): void
    {
        $researchRun = $this->createResearchRun();
        $otherUser = UserFactory::new()->owner()->create();
        resolve(BrandService::class)->create($otherUser->client, ['name' => 'Otra marca']);
        $credentials = resolve(UserService::class)->createApiToken($otherUser);
        $this->withToken($credentials['token']);

        $this->getJson("/api/research-runs/{$researchRun->id}")->assertNotFound();
        $this->getJson('/api/research-runs/website/status')->assertOk()->assertJsonPath('data.latest', null);
        $this->postJson('/api/research-runs', ['type' => 'website'])->assertUnprocessable()
            ->assertJsonValidationErrors('website_url');
        $this->postJson('/api/research-runs', ['type' => 'instagram'])->assertUnprocessable()
            ->assertJsonValidationErrors('instagram_username');
        $this->withoutToken();
        $this->getJson("/api/research-runs/{$researchRun->id}")->assertUnauthorized();
        $this->postJson('/api/research-runs', ['type' => 'website'])->assertUnauthorized();
    }


    // Los IDs conservados en un JSON también se filtran por marca al recuperar las fuentes.
    #[Test]
    public function filters_foreign_source_ids_from_results(): void
    {
        $otherUser = UserFactory::new()->owner()->create();
        $otherBrand = resolve(BrandService::class)->create($otherUser->client, ['name' => 'Otra marca']);
        $knowledgeSource = resolve(KnowledgeSourceService::class)->create($otherBrand, [
            'type' => 'web_page', 'title' => 'Privado', 'status' => 'ready',
        ]);
        $researchRun = $this->createResearchRun();
        $researchRun->update(['knowledge_source_ids' => [$knowledgeSource->id]]);

        $this->getJson("/api/research-runs/{$researchRun->id}")
            ->assertOk()->assertJsonCount(0, 'data.knowledge_sources');
    }


    private function createResearchRun(): ResearchRun
    {
        return resolve(ResearchRunService::class)->create($this->brand, ['type' => 'website']);
    }


    private function recordedOpenAiInputs(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)
            ->map(fn (array $pair) => $this->decodeOpenAiInput($pair[0]))->values()->all();
    }


    // El helper de OpenAI agrega un recordatorio de JSON al final del input; se decodifica solo el objeto.
    private function decodeOpenAiInput(Request $request): array
    {
        $input = $request['input'];
        $jsonObject = substr($input, 0, strrpos($input, '}') + 1);

        return json_decode($jsonObject, true);
    }


    private function firecrawlPage(string $markdown, array $links = [], ?array $branding = null): array
    {
        return ['success' => true, 'data' => [
            'links' => $links,
            'branding' => $branding,
            'markdown' => $markdown,
            'metadata' => ['title' => 'Mi marca'],
            'images' => ['https://example.com/logo.png'],
        ]];
    }


    private function firecrawlBranding(string $logo): array
    {
        return [
            'logo' => $logo,
            'typography' => ['fontFamilies' => ['primary' => 'Poppins', 'heading' => 'Lora']],
            'colors' => [
                'primary' => '#339D33', 'secondary' => '#FFD745', 'accent' => '#2C3E50', 'background' => '#FFFFFF',
                'textPrimary' => '#3F3D38',
            ],
        ];
    }


    // Respuesta de la primera consulta: páginas elegidas y los tres campos visuales vacíos, salvo los indicados.
    private function homepageReview(array $additionalUrls = [], array $visualValues = []): array
    {
        $visual = ['brand_logos' => [], 'brand_colors' => null, 'brand_fonts' => null];

        return ['additional_urls' => $additionalUrls, 'visual' => [...$visual, ...$visualValues]];
    }


    // Respuesta de la segunda consulta con todos los campos de texto en null, salvo los indicados.
    private function analysis(array $brandValues = [], array $insights = []): array
    {
        $brand = [
            'name' => null,
            'brand_offer_description' => null,
            'brand_differentiators_description' => null,
            'brand_history_description' => null,
            'brand_customers_description' => null,
            'brand_customers_needs_description' => null,
            'brand_visual_style_description' => null,
            'brand_tone_of_voice_description' => null,
            'brand_customers_valued_aspects_description' => null,
            'brand_customers_faq_description' => null,
            'brand_communication_topics_description' => null,
            'brand_content_opportunities_description' => null,
        ];

        return [
            'brand' => [...$brand, ...$brandValues],
            'inferred_fields' => [],
            'summary' => 'Vivero online.',
            'insights' => $insights,
        ];
    }


    private function openAiResponse(array $analysis): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($analysis)]],
        ]]];
    }

}

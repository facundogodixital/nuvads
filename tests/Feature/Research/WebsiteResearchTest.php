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
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use App\Services\KnowledgeSourceService;
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
            ->assertJsonPath('data.input.url', 'https://example.com')
            ->assertJsonPath('data.input.overwrite', true);
        $researchRunId = $response->json('data.id');
        resolve(BrandService::class)->update($this->brand, ['website_url' => 'https://changed.example']);

        $this->postJson('/api/research-runs', ['type' => 'website'])
            ->assertConflict()->assertJsonPath('code', 'research_already_running');

        $this->getJson("/api/research-runs/{$researchRunId}")->assertOk()
            ->assertJsonPath('data.input.url', 'https://example.com');
        Queue::assertPushedOn('research_queue', ResearchWebsiteJob::class);
        Queue::assertPushed(ResearchWebsiteJob::class, 1);
        $this->assertDatabaseCount('research_runs', 1);
    }


    // Con la portada alcanza: el modelo no pide más páginas y la marca se completa con una fuente y un insight.
    #[Test]
    public function completes_the_research_with_the_homepage_only(): void
    {
        $colors = ['primary' => '#339D33', 'secondary' => null, 'accent' => null, 'background' => null, 'text' => null];
        $analysis = $this->analysis([
            'brand_offer_description' => 'Jardinería',
            'brand_logo' => ['https://example.com/logo.png'],
            'brand_colors' => $colors,
        ]);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Vendemos plantas.')),
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($analysis)),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertNotNull($researchRun->started_at);
        $this->assertNotNull($researchRun->finished_at);
        $this->assertCount(1, $researchRun->knowledge_source_ids);
        $this->assertSame('Jardinería', $this->brand->fresh()->brand_offer_description);
        // MySQL guarda las claves del JSON en otro orden; se compara el contenido.
        $this->assertEquals($colors, $this->brand->fresh()->brand_colors);
        $this->assertSame(['https://example.com/logo.png'], $this->brand->fresh()->brand_logo);
        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_insights', 1);
        Http::assertSentCount(2);
        $this->getJson("/api/research-runs/{$researchRun->id}")->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonCount(1, 'data.knowledge_sources')
            ->assertJsonCount(1, 'data.knowledge_insights')
            ->assertJsonPath('data.knowledge_insights.0.model', config('research.website.analysis_model'))
            ->assertJsonPath('data.knowledge_insights.0.payload.brand.brand_offer_description', 'Jardinería');
    }


    // El modelo elige hasta dos enlaces internos de la portada; se leen y se analiza todo junto en una
    // segunda consulta que ya no ofrece enlaces.
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
                ->push($this->firecrawlPage('Portada', $homepageLinks))
                ->push($this->firecrawlPage('Desde 2010'))
                ->push($this->firecrawlPage('Asesoramiento')),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($this->analysis([], $chosenUrls)))
                ->push($this->openAiResponse($this->analysis(['brand_history_description' => 'Desde 2010']))),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $this->assertSame('completed', $researchRun->fresh()->status);
        $this->assertCount(3, $researchRun->fresh()->knowledge_source_ids);
        $this->assertSame('Desde 2010', $this->brand->fresh()->brand_history_description);
        $this->assertDatabaseCount('knowledge_sources', 3);
        $this->assertDatabaseCount('knowledge_insights', 1);
        Http::assertSentCount(5);
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';
        $openAiInputs = Http::recorded($isOpenAiRequest)
            ->map(fn (array $pair) => $this->decodeOpenAiInput($pair[0]))->values();
        $this->assertSame($chosenUrls, $openAiInputs[0]['available_links']);
        $this->assertCount(1, $openAiInputs[0]['pages']);
        $this->assertSame([], $openAiInputs[1]['available_links']);
        $this->assertCount(3, $openAiInputs[1]['pages']);
    }


    // Sin overwrite, los valores que la marca ya tiene se conservan, incluso los editados durante el análisis.
    #[Test]
    public function keeps_existing_brand_values_and_edits_made_during_the_analysis(): void
    {
        resolve(BrandService::class)->update($this->brand, ['brand_tone_of_voice_description' => 'Mi voz']);
        $analysis = $this->analysis([
            'name' => 'Otro nombre',
            'brand_tone_of_voice_description' => 'Voz sugerida',
            'brand_history_description' => 'Historia sugerida',
            'brand_offer_description' => '  Jardinería  ',
        ]);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => function () use ($analysis) {
                // El usuario edita la marca mientras el modelo responde.
                Brand::query()->whereKey($this->brand->id)->update(['brand_history_description' => 'Mi historia']);
                return Http::response($this->openAiResponse($analysis));
            },
        ]);
        $researchRun = resolve(ResearchRunService::class)->create($this->brand, [
            'type' => 'website', 'overwrite' => false,
        ]);

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $brand = $this->brand->fresh();
        $this->assertSame('Mi marca', $brand->name);
        $this->assertSame('Mi voz', $brand->brand_tone_of_voice_description);
        $this->assertSame('Mi historia', $brand->brand_history_description);
        $this->assertSame('Jardinería', $brand->brand_offer_description);
    }


    // Con overwrite el análisis pisa los valores existentes; lo que el modelo devuelve vacío no borra nada.
    #[Test]
    public function overwrites_existing_brand_values_when_requested(): void
    {
        resolve(BrandService::class)->update($this->brand, [
            'brand_history_description' => 'Mi historia',
            'brand_tone_of_voice_description' => 'Mi voz',
        ]);
        $analysis = $this->analysis(['name' => 'Otro nombre', 'brand_tone_of_voice_description' => 'Voz sugerida']);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($analysis)),
        ]);
        $researchRunId = $this->postJson('/api/research-runs', ['type' => 'website', 'overwrite' => true])
            ->assertCreated()->assertJsonPath('data.input.overwrite', true)->json('data.id');

        (new ResearchWebsiteJob($researchRunId))->handle();

        $brand = $this->brand->fresh();
        $this->assertSame('Otro nombre', $brand->name);
        $this->assertSame('Voz sugerida', $brand->brand_tone_of_voice_description);
        $this->assertSame('Mi historia', $brand->brand_history_description);
    }


    // Un JSON sin ningún valor, como colores todos en null o una lista de logos vacía, se guarda como null.
    #[Test]
    public function stores_null_for_empty_json_values(): void
    {
        $analysis = $this->analysis([
            'brand_logo' => [],
            'brand_colors' => [
                'primary' => null, 'secondary' => null, 'accent' => null, 'background' => null, 'text' => null,
            ],
            'brand_fonts' => ['heading' => null, 'body' => null],
        ]);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($analysis)),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchWebsiteJob($researchRun->id))->handle();

        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->fresh()->status);
        $this->assertNull($brand->brand_logo);
        $this->assertNull($brand->brand_colors);
        $this->assertNull($brand->brand_fonts);
    }


    // Un JSON con otra forma que la fija se rechaza aunque el resto de la respuesta sea válido.
    #[Test]
    #[DataProvider('jsonValuesOutsideTheFixedShape')]
    public function rejects_json_values_outside_the_fixed_shape(array $brandValues): void
    {
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => Http::response(
                $this->openAiResponse($this->analysis($brandValues)),
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
            'logo as text' => [['brand_logo' => 'https://example.com/logo.png']],
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
                $this->openAiResponse($this->analysis([], $additionalUrls)),
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


    // Dos investigaciones sobre el mismo contenido comparten la fuente en vez de duplicarla.
    #[Test]
    public function reuses_identical_sources_between_research_runs(): void
    {
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage('Portada')),
            'https://api.openai.com/v1/responses' => Http::response($this->openAiResponse($this->analysis())),
        ]);
        $firstResearchRun = $this->createResearchRun();
        (new ResearchWebsiteJob($firstResearchRun->id))->handle();

        $secondResearchRun = $this->createResearchRun();
        (new ResearchWebsiteJob($secondResearchRun->id))->handle();

        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_insights', 2);
        $this->assertSame(
            $firstResearchRun->fresh()->knowledge_source_ids, $secondResearchRun->fresh()->knowledge_source_ids,
        );
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
        } catch (ApiException $exception) {
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
            ->assertJsonValidationErrors('type');
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


    // El helper de OpenAI agrega un recordatorio de JSON al final del input; se decodifica solo el objeto.
    private function decodeOpenAiInput(Request $request): array
    {
        $input = $request['input'];
        $jsonObject = substr($input, 0, strrpos($input, '}') + 1);

        return json_decode($jsonObject, true);
    }


    private function firecrawlPage(string $markdown, array $links = []): array
    {
        return ['success' => true, 'data' => [
            'markdown' => $markdown,
            'metadata' => ['title' => 'Mi marca'],
            'images' => ['https://example.com/logo.png'],
            'links' => $links,
        ]];
    }


    // Respuesta del modelo con todos los campos de la marca en null, salvo los indicados.
    private function analysis(array $brandValues = [], array $additionalUrls = []): array
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
            'brand_logo' => [],
            'brand_colors' => null,
            'brand_fonts' => null,
        ];

        return [
            'brand' => [...$brand, ...$brandValues],
            'inferred_fields' => [],
            'additional_urls' => $additionalUrls,
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

<?php

namespace Tests\Feature\Competitors;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Competitor;
use App\Services\UserService;
use App\Services\BrandService;
use App\Services\CompetitorService;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use App\Models\CompetitorResearchRun;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use App\Services\CompetitorResearchRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\Competitors\ResearchBrandCompetitionJob;
use App\Jobs\Research\Competitors\ResearchCompetitorWebsiteJob;


class CompetitorWebsiteResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;
    private Competitor $competitor;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('services.firecrawl.api_key', 'testing-key');
        config()->set('logging.channels.ResearchCompetitorWebsiteJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchCompetitorWebsiteJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Mi marca',
            'brand_offer_description' => 'Plantas de interior.',
        ]);
        $this->competitor = resolve(CompetitorService::class)->create($this->brand, [
            'name' => 'Vivero Sur',
            'website_url' => 'https://viverosur.example',
            'competitor_weaknesses_description' => 'No responde consultas.',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // Analiza la portada y la página elegida, mezcla los campos del competidor sin borrar nada ni tocar la marca y
    // encola el cruce; la pantalla lee el estado, el análisis y sus conclusiones.
    #[Test]
    public function analyzes_the_chosen_pages_and_merges_the_competitor_fields(): void
    {
        $homepage = $this->firecrawlPage('Vivero con envíos', ['/precios', 'https://otro.example/precios']);
        Http::fake([
            'https://api.firecrawl.dev/v2/scrape' => Http::sequence()
                ->push($homepage)
                ->push($this->firecrawlPage('Precios mayoristas')),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse(['additional_urls' => ['https://viverosur.example/precios']]))
                ->push($this->openAiResponse($this->analysis())),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchCompetitorWebsiteJob($researchRun->id))->handle();

        $researchRun->refresh();
        $competitor = $this->competitor->fresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertCount(2, $researchRun->competitor_source_ids);
        $openAiInputs = $this->recordedOpenAiInputs();
        $this->assertSame(['https://viverosur.example/precios'], $openAiInputs[0]['available_links']);
        $this->assertCount(2, $openAiInputs[1]['pages']);
        $sentCompetitorFields = $openAiInputs[1]['competitor'];
        $this->assertSame('No responde consultas.', $sentCompetitorFields['competitor_weaknesses_description']);

        $this->assertSame('Plantas con envío a todo el país.', $competitor->competitor_offer_description);
        $this->assertSame('No responde consultas.', $competitor->competitor_weaknesses_description);
        $this->assertSame('Plantas de interior.', $this->brand->fresh()->brand_offer_description);
        Queue::assertPushedOn('research_queue', ResearchBrandCompetitionJob::class);

        $this->getJson("/api/competitors/{$competitor->id}/research-runs/website/status")->assertOk()
            ->assertJsonPath('data.last_completed.id', $researchRun->id);
        $this->getJson("/api/competitors/{$competitor->id}/insights/website")->assertOk()
            ->assertJsonPath('data.analysis.body', 'Vivero que vende por mayor.')
            ->assertJsonCount(2, 'data.insights');
    }


    // Una portada sin texto no tiene nada para analizar: la investigación termina vacía con un mensaje, sin guardar
    // fuentes ni consultar al modelo, y sin recalcular lo que la marca sabe de su competencia.
    #[Test]
    public function ends_empty_when_the_homepage_has_no_text(): void
    {
        Http::fake(['https://api.firecrawl.dev/v2/scrape' => Http::response($this->firecrawlPage(''))]);
        $researchRun = $this->createResearchRun();

        (new ResearchCompetitorWebsiteJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('empty', $researchRun->status);
        $this->assertNotNull($researchRun->status_message);
        $this->assertDatabaseCount('competitor_sources', 0);
        $this->assertSame([], $this->recordedOpenAiInputs());
        Queue::assertNotPushed(ResearchBrandCompetitionJob::class);
    }


    private function createResearchRun(): CompetitorResearchRun
    {
        return resolve(CompetitorResearchRunService::class)->create($this->competitor, 'website');
    }


    private function recordedOpenAiInputs(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)
            ->map(fn (array $pair): array => $this->decodeOpenAiText($pair[0]['input']))
            ->values()
            ->all();
    }


    // El helper de OpenAI agrega un recordatorio de JSON al final del texto; se decodifica solo el objeto.
    private function decodeOpenAiText(string $text): array
    {
        $jsonObject = substr($text, 0, strrpos($text, '}') + 1);

        return json_decode($jsonObject, true);
    }


    private function firecrawlPage(string $markdown, array $links = []): array
    {
        return ['success' => true, 'data' => [
            'links' => $links,
            'images' => [],
            'markdown' => $markdown,
            'metadata' => ['title' => 'Vivero Sur'],
        ]];
    }


    // La oferta vuelve mezclada; las debilidades vuelven vacías y no tienen que borrar lo que ya sabíamos.
    private function analysis(): array
    {
        return [
            'matches_competitor' => true,
            'competitor' => [
                'competitor_offer_description' => 'Plantas con envío a todo el país.',
                'competitor_customers_description' => null,
                'competitor_strengths_description' => null,
                'competitor_weaknesses_description' => null,
                'competitor_communication_description' => null,
                'competitor_differentiators_description' => 'Precios mayoristas.',
            ],
            'summary' => 'Vivero que vende por mayor.',
            'insights' => ['Promete envíos a todo el país.', 'Muestra precios mayoristas.'],
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

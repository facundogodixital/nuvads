<?php

namespace Tests\Feature\Research;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\ResearchRun;
use App\Services\UserService;
use Illuminate\Support\Sleep;
use App\Services\BrandService;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use App\Services\KnowledgeSourceService;
use App\Jobs\Research\MetaAds\ResearchMetaAdsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;


class MetaAdsResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Sleep::fake();
        $this->travelTo('2026-09-24 12:00:00');
        config()->set('services.apify.api_key', 'testing-key');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchMetaAdsJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchMetaAdsJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Mi marca',
            'meta_ads_url' => 'https://www.facebook.com/mimarca',
            'brand_offer_description' => 'Sustratos.',
            'brand_visual_style_description' => 'Fotos reales.',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // La página y la cantidad de anuncios se toman de la marca y la configuración y quedan congelados en la
    // ejecución, que se encola en research_queue.
    #[Test]
    public function starts_with_saved_page_url_and_ads_limit(): void
    {
        $this->postJson('/api/research-runs', ['type' => 'meta_ads'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.input.url', 'https://www.facebook.com/mimarca')
            ->assertJsonPath('data.input.ads_limit', config('research.meta_ads.ads_limit'));

        Queue::assertPushedOn('research_queue', ResearchMetaAdsJob::class);
    }


    // Pide a Apify los anuncios más nuevos, transcribe cada uno con sus imágenes y los guarda como fuentes; un
    // anuncio que falla se saltea. Las métricas cuentan los días de los activos hasta hoy y los de los terminados
    // hasta su fin. El análisis final recibe el texto actual de la marca: lo mezclado se guarda y lo que vuelve vacío
    // no borra nada. La pantalla de anuncios lee el estado, el análisis, las conclusiones y los anuncios leídos.
    #[Test]
    public function analyzes_the_ads_and_merges_the_brand_fields(): void
    {
        $analysis = $this->analysis(['brand_offer_description' => 'Sustratos con envío gratis.']);
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::sequence()
                ->push($this->apifyRun('RUNNING'))
                ->push($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($this->apifyAds()),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($this->transcription(1)))
                ->push(['error' => ['message' => 'Error while downloading the image.']], 400)
                ->push($this->openAiResponse($this->transcription(2)))
                ->push($this->openAiResponse($analysis)),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchMetaAdsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertCount(2, $researchRun->knowledge_source_ids);
        $apifyStartUrl = 'https://api.apify.com/v2/actors/apify~facebook-ads-scraper/runs';
        $isApifyStart = fn (Request $request): bool => $request->url() === $apifyStartUrl;
        $apifyInput = Http::recorded($isApifyStart)->first()[0]->data();
        $this->assertSame('relevancy_monthly_grouped', $apifyInput['sorting']);
        $this->assertSame([['url' => 'https://www.facebook.com/mimarca']], $apifyInput['startUrls']);

        $carousel = resolve(KnowledgeSourceService::class)->find($brand, $researchRun->knowledge_source_ids[1]);
        $this->assertSame('meta_ad', $carousel->type);
        $this->assertSame('https://www.facebook.com/ads/library/?id=3', $carousel->payload['url']);
        $this->assertSame(30, $carousel->payload['days_running']);
        $openAiRequests = $this->recordedOpenAiRequests();
        $this->assertSame(
            ['https://cdn.example/card-1.jpg', 'https://cdn.example/card-2.jpg'],
            $this->sentImageUrls($openAiRequests[2]),
        );
        $analysisInput = $this->decodeOpenAiText($openAiRequests[3]['input']);
        $this->assertSame('Sustratos.', $analysisInput['brand']['brand_offer_description']);

        $this->assertSame('Sustratos con envío gratis.', $brand->brand_offer_description);
        $this->assertSame('Fotos reales.', $brand->brand_visual_style_description);

        $this->getJson('/api/research-runs/meta-ads/status')->assertOk()
            ->assertJsonPath('data.last_completed.id', $researchRun->id);
        $metaAdsInsights = $this->getJson('/api/knowledge-insights/meta-ads')->assertOk()->json('data');
        $metrics = $metaAdsInsights['analysis']['payload']['metrics'];
        $this->assertSame(30, $metrics['longest_running_days']);
        $this->assertSame(20, $metrics['formats']['video']['average_days_running']);
        $this->assertEquals(['facebook' => 2, 'instagram' => 2], $metrics['platforms']);
        $this->assertCount(2, $metaAdsInsights['insights']);
        $this->assertSame($researchRun->knowledge_source_ids, array_column($metaAdsInsights['ads'], 'id'));
    }


    // Una página sin anuncios no es un error: la investigación termina bien y deja un análisis que lo dice, sin
    // consultar al modelo ni tocar la marca.
    #[Test]
    public function completes_with_a_no_ads_analysis_when_the_page_has_no_ads(): void
    {
        $pageWithoutAds = [[
            'inputUrl' => 'https://www.facebook.com/mimarca',
            'pageInfo' => ['page' => ['name' => 'Mi marca', 'id' => '1']],
            'isResultComplete' => true,
            'results' => [],
            'totalCount' => 0,
        ]];
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($pageWithoutAds),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchMetaAdsJob($researchRun->id))->handle();

        $this->assertSame('completed', $researchRun->fresh()->status);
        $this->assertSame([], $this->recordedOpenAiRequests());
        $this->assertSame('Sustratos.', $this->brand->fresh()->brand_offer_description);
        $noAdsSummary = 'Esta página no tiene anuncios en la Biblioteca de anuncios de Meta.';
        $this->getJson('/api/knowledge-insights/meta-ads')->assertOk()
            ->assertJsonPath('data.analysis.body', $noAdsSummary)
            ->assertJsonPath('data.analysis.payload.metrics.ads_count', 0)
            ->assertJsonPath('data.ads', []);
    }


    private function createResearchRun(): ResearchRun
    {
        return resolve(ResearchRunService::class)->create($this->brand, ['type' => 'meta_ads']);
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


    // Una imagen activa desde hace 10 días, un video activo desde hace 20 y un carrusel que corrió 30 días y ya
    // terminó, con la forma en que los devuelve Apify.
    private function apifyAds(): array
    {
        return [
            [
                'adArchiveID' => '1',
                'isActive' => true,
                'startDate' => strtotime('2026-09-14 00:00:00 UTC'),
                'endDate' => strtotime('2026-09-16 00:00:00 UTC'),
                'publisherPlatform' => ['FACEBOOK', 'INSTAGRAM'],
                'snapshot' => [
                    'displayFormat' => 'IMAGE',
                    'body' => ['text' => 'Envío gratis en sustratos'],
                    'images' => [['originalImageUrl' => 'https://cdn.example/image.jpg']],
                ],
            ],
            [
                'adArchiveID' => '2',
                'isActive' => true,
                'startDate' => strtotime('2026-09-04 00:00:00 UTC'),
                'endDate' => strtotime('2026-09-16 00:00:00 UTC'),
                'publisherPlatform' => ['INSTAGRAM'],
                'snapshot' => [
                    'displayFormat' => 'VIDEO',
                    'body' => ['text' => 'Mirá cómo trasplantar'],
                    'videos' => [[
                        'videoHdUrl' => 'https://cdn.example/video.mp4',
                        'videoPreviewImageUrl' => 'https://cdn.example/video-cover.jpg',
                    ]],
                ],
            ],
            [
                'adArchiveID' => '3',
                'isActive' => false,
                'startDate' => strtotime('2026-08-01 00:00:00 UTC'),
                'endDate' => strtotime('2026-08-31 00:00:00 UTC'),
                'publisherPlatform' => ['FACEBOOK'],
                'snapshot' => [
                    'displayFormat' => 'CAROUSEL',
                    'body' => ['text' => 'Nuestros sustratos'],
                    'cards' => [
                        ['originalImageUrl' => 'https://cdn.example/card-1.jpg'],
                        ['originalImageUrl' => 'https://cdn.example/card-2.jpg'],
                    ],
                ],
            ],
        ];
    }


    // Respuesta de la transcripción de un anuncio, con la cantidad indicada de entradas numeradas.
    private function transcription(int $entriesCount): array
    {
        $images = [];
        for ($imageNumber = 1; $imageNumber <= $entriesCount; $imageNumber++) {
            $images[] = ['transcription' => null, 'description' => "Imagen {$imageNumber}"];
        }

        return ['images' => $images];
    }


    // Respuesta del análisis final con los ocho campos en null, salvo los indicados.
    private function analysis(array $brandValues): array
    {
        $brand = [
            'brand_offer_description' => null,
            'brand_customers_description' => null,
            'brand_visual_style_description' => null,
            'brand_tone_of_voice_description' => null,
            'brand_differentiators_description' => null,
            'brand_customers_needs_description' => null,
            'brand_communication_topics_description' => null,
            'brand_content_opportunities_description' => null,
        ];

        return [
            'brand' => [...$brand, ...$brandValues],
            'summary' => 'Anuncia sustratos con envío gratis.',
            'insights' => ['El video es el que más corre.', 'Nunca muestra precios.'],
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

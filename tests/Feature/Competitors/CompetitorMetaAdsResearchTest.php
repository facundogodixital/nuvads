<?php

namespace Tests\Feature\Competitors;

use Tests\TestCase;
use App\Models\Competitor;
use App\Services\UserService;
use Illuminate\Support\Sleep;
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
use App\Jobs\Research\Competitors\ResearchCompetitorMetaAdsJob;


class CompetitorMetaAdsResearchTest extends TestCase
{

    use RefreshDatabase;

    private Competitor $competitor;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Sleep::fake();
        $this->travelTo('2026-09-24 12:00:00');
        config()->set('services.apify.api_key', 'testing-key');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchCompetitorMetaAdsJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchCompetitorMetaAdsJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $this->competitor = resolve(CompetitorService::class)->create($brand, [
            'name' => 'Vivero Sur',
            'meta_ads_url' => 'https://www.facebook.com/viverosur',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // Descarta el ítem de la página, transcribe cada anuncio con su portada, analiza con los días corriendo, mezcla los
    // campos y encola el cruce; la pantalla lee el análisis y los anuncios.
    #[Test]
    public function analyzes_the_ads_and_merges_the_competitor_fields(): void
    {
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($this->apifyItems()),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($this->transcription()))
                ->push($this->openAiResponse($this->transcription()))
                ->push($this->openAiResponse($this->analysis())),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchCompetitorMetaAdsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertCount(2, $researchRun->competitor_source_ids);
        $openAiRequests = $this->recordedOpenAiRequests();
        $this->assertSame(['https://cdn.example/video-cover.jpg'], $this->sentImageUrls($openAiRequests[1]));
        $analysisInput = $this->decodeOpenAiText($openAiRequests[2]['input']);
        $this->assertSame(20, $analysisInput['metrics']['longest_running_days']);
        $competitorOffer = $this->competitor->fresh()->competitor_offer_description;
        $this->assertSame('Envío gratis en compras grandes.', $competitorOffer);
        Queue::assertPushedOn('research_queue', ResearchBrandCompetitionJob::class);

        $metaAdsInsights = $this->getJson("/api/competitors/{$this->competitor->id}/insights/meta-ads")
            ->assertOk()
            ->json('data');
        $this->assertSame(2, $metaAdsInsights['analysis']['payload']['metrics']['ads_count']);
        $this->assertSame([10, 20], array_column(array_column($metaAdsInsights['ads'], 'payload'), 'days_running'));
    }


    private function createResearchRun(): CompetitorResearchRun
    {
        return resolve(CompetitorResearchRunService::class)->create($this->competitor, 'meta_ads');
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


    // Un anuncio de imagen con 10 días corriendo, uno de video con 20 y el ítem de la página, sin adArchiveID.
    private function apifyItems(): array
    {
        return [
            [
                'adArchiveID' => '1',
                'isActive' => true,
                'startDate' => strtotime('2026-09-14 12:00:00 UTC'),
                'endDate' => strtotime('2026-09-16 00:00:00 UTC'),
                'publisherPlatform' => ['FACEBOOK', 'INSTAGRAM'],
                'snapshot' => [
                    'displayFormat' => 'IMAGE',
                    'body' => ['text' => 'Envío gratis'],
                    'images' => [['originalImageUrl' => 'https://cdn.example/image.jpg']],
                ],
            ],
            [
                'adArchiveID' => '2',
                'isActive' => true,
                'startDate' => strtotime('2026-09-04 12:00:00 UTC'),
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
            ['pageName' => 'Vivero Sur'],
        ];
    }


    private function transcription(): array
    {
        return ['images' => [['transcription' => 'ENVÍO GRATIS', 'description' => 'Plantas sobre fondo verde.']]];
    }


    private function analysis(): array
    {
        return [
            'matches_competitor' => true,
            'competitor' => [
                'competitor_offer_description' => 'Envío gratis en compras grandes.',
                'competitor_customers_description' => null,
                'competitor_strengths_description' => null,
                'competitor_weaknesses_description' => null,
                'competitor_communication_description' => null,
                'competitor_differentiators_description' => null,
            ],
            'summary' => 'Anuncia envíos gratis y tutoriales.',
            'insights' => [],
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

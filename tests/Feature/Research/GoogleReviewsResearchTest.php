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
use GuzzleHttp\Promise\PromiseInterface;
use App\Services\KnowledgeInsightService;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\GoogleReviews\ResearchGoogleReviewsJob;


class GoogleReviewsResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;
    private int $apifyReviewsCount = 0;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Sleep::fake();
        $this->travelTo('2026-09-24 12:00:00');
        config()->set('services.apify.api_key', 'testing-key');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchGoogleReviewsJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchGoogleReviewsJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Mi marca',
            'google_maps_url' => 'https://maps.app.goo.gl/mimarca',
            'brand_offer_description' => 'Sustratos.',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // El enlace de Google Maps y la cantidad de reseñas se toman de la marca y la configuración y quedan congelados
    // en la ejecución, que se encola en research_queue.
    #[Test]
    public function starts_with_saved_google_maps_url_and_reviews_limit(): void
    {
        $this->postJson('/api/research-runs', ['type' => 'google_reviews'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.input.url', 'https://maps.app.goo.gl/mimarca')
            ->assertJsonPath('data.input.reviews_limit', config('research.google_reviews.reviews_limit'));

        Queue::assertPushedOn('research_queue', ResearchGoogleReviewsJob::class);
    }


    // Pide a Apify las reseñas más nuevas y guarda cada una como fuente, reemplazando las de corridas anteriores. Con
    // más de 200 reseñas con texto hay dos tandas: el modelo no puede sumar IDs ajenos y los temas de las tandas se
    // unifican. Con 305 reseñas con texto hacen falta 3 menciones (el 1%), salvo en las quejas, que alcanzan con 2 y
    // muestran su peso en mentions_share. Las reseñas con texto se parten en cuatro tramos de tiempo, y un dolor que
    // no aparece en el último queda resolved. Cada conclusión apunta a las reseñas de sus temas, y lo mezclado se
    // guarda en la marca. La pantalla de reseñas lee métricas, análisis, dolores, fortalezas, conclusiones y las
    // reseñas destacadas.
    #[Test]
    public function analyzes_the_reviews_and_merges_the_brand_fields(): void
    {
        $previousReview = resolve(KnowledgeSourceService::class)->create($this->brand, [
            'type' => 'google_review', 'title' => 'Reseña vieja', 'status' => 'ready',
        ]);
        // 241 elogios y dos quejas recientes, dos menciones al estacionamiento, 60 quejas viejas y 5 reseñas sin texto.
        $apifyReviews = [
            ...$this->apifyReviews(241, 'Excelente la atención de Marta', 5, '2026-09-20'),
            ...$this->apifyReviews(2, 'Los precios son altos', 3, '2026-09-10'),
            ...$this->apifyReviews(2, 'Tienen estacionamiento', 4, '2026-09-01'),
            ...$this->apifyReviews(60, 'Mucha demora en el delivery', 2, '2025-06-30'),
            ...$this->apifyReviews(5, null, 5, '2025-06-01'),
        ];
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($apifyReviews),
            'https://api.openai.com/v1/responses' => $this->fakeOpenAiResponse(...),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchGoogleReviewsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertCount(310, $researchRun->knowledge_source_ids);
        $this->assertNull(resolve(KnowledgeSourceService::class)->find($brand, $previousReview->id));
        $apifyStartUrl = 'https://api.apify.com/v2/actors/compass~google-maps-reviews-scraper/runs';
        $isApifyStart = fn (Request $request): bool => $request->url() === $apifyStartUrl;
        $apifyInput = Http::recorded($isApifyStart)->first()[0]->data();
        $this->assertSame('newest', $apifyInput['reviewsSort']);
        $this->assertSame([['url' => 'https://maps.app.goo.gl/mimarca']], $apifyInput['startUrls']);

        $this->assertSame('La atención de Marta.', $brand->brand_customers_valued_aspects_description);
        $this->assertSame('Sustratos.', $brand->brand_offer_description);

        $this->getJson('/api/research-runs/google-reviews/status')->assertOk()
            ->assertJsonPath('data.last_completed.id', $researchRun->id);
        $googleReviewsInsights = $this->getJson('/api/knowledge-insights/google-reviews')->assertOk()->json('data');
        $this->assertSame([], $googleReviewsInsights['analysis']['payload']['facts']);

        [$pain, $minorPain] = $googleReviewsInsights['pains'];
        $this->assertSame('Demora en el delivery', $pain['body']);
        $this->assertSame(60, $pain['payload']['mentions_count']);
        $this->assertSame('resolved', $pain['payload']['trend']);
        $this->assertCount(4, $googleReviewsInsights['metrics']['payload']['time_ranges']);
        $this->assertSame(0.007, $minorPain['payload']['mentions_share']);
        $this->assertSame(241, $googleReviewsInsights['strengths'][0]['payload']['mentions_count']);

        [$painInsight, $metricsInsight] = $googleReviewsInsights['insights'];
        $this->assertSame($pain['knowledge_source_ids'], $painInsight['knowledge_source_ids']);
        $this->assertSame($researchRun->knowledge_source_ids, $metricsInsight['knowledge_source_ids']);
        $highlightedKnowledgeSourceIds = array_column($googleReviewsInsights['reviews'], 'id');
        $this->assertEqualsCanonicalizing($pain['payload']['highlight_ids'], $painInsight['payload']['highlight_ids']);
        $this->assertContains($pain['payload']['highlight_ids'][0], $highlightedKnowledgeSourceIds);
    }


    // Sin reseñas, o solo con estrellas, no hay nada para analizar: la investigación termina vacía con un mensaje que
    // lo dice, sin guardar reseñas ni consultar al modelo, y las reseñas y el análisis anteriores siguen vigentes.
    #[Test]
    #[DataProvider('reviewsWithNothingToAnalyze')]
    public function ends_empty_when_there_is_nothing_to_analyze(bool $hasStarOnlyReviews): void
    {
        $previousReview = resolve(KnowledgeSourceService::class)->create($this->brand, [
            'type' => 'google_review', 'title' => 'Reseña anterior', 'status' => 'ready',
        ]);
        $previousAnalysis = resolve(KnowledgeInsightService::class)->create($this->brand, [
            'type' => 'google_reviews_brand_analysis', 'body' => 'Análisis anterior.', 'status' => 'active',
            'level' => 1,
        ]);
        $apifyReviews = $hasStarOnlyReviews ? $this->apifyReviews(3, null, 5, '2026-08-01') : [];
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($apifyReviews),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchGoogleReviewsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $knowledgeSourceIds = resolve(KnowledgeSourceService::class)->list($this->brand)->modelKeys();
        $this->assertSame('empty', $researchRun->status);
        $this->assertNotNull($researchRun->status_message);
        $this->assertSame([], $this->recordedOpenAiRequests());
        $this->assertSame([$previousReview->id], $knowledgeSourceIds);
        $this->assertSame('active', $previousAnalysis->fresh()->status);
    }


    public static function reviewsWithNothingToAnalyze(): array
    {
        return [
            'no reviews' => [false],
            'only stars' => [true],
        ];
    }


    private function createResearchRun(): ResearchRun
    {
        return resolve(ResearchRunService::class)->create($this->brand, ['type' => 'google_reviews']);
    }


    private function recordedOpenAiRequests(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)->map(fn (array $pair): Request => $pair[0])->values()->all();
    }


    // Simula al modelo según la consulta: en cada tanda arma los temas leyendo los textos, en la unificación agrupa
    // los temas con el mismo nombre, y en el análisis final devuelve una conclusión sobre el primer dolor y otra sobre
    // las métricas.
    private function fakeOpenAiResponse(Request $request): PromiseInterface
    {
        $input = $this->decodeOpenAiText($request['input']);
        $isBatchRequest = str_contains($request['instructions'], 'Tu tarea es encontrar los temas');
        $isUnifyRequest = str_contains($request['instructions'], 'Tu tarea es unificar');

        if ($isBatchRequest) {
            return Http::response($this->openAiResponse($this->batchTopics($input['reviews'])));
        }

        if ($isUnifyRequest) {
            $groups = [];
            foreach ($input as $category => $topicNamesByKey) {
                $groups[$category] = [];
                foreach (array_unique($topicNamesByKey) as $topicName) {
                    $keys = array_keys($topicNamesByKey, $topicName, true);
                    $groups[$category][] = ['topic' => $topicName, 'keys' => $keys];
                }
            }
            return Http::response($this->openAiResponse($groups));
        }

        return Http::response($this->openAiResponse([
            'matches_brand' => true,
            'brand' => [
                'brand_offer_description' => null,
                'brand_customers_description' => null,
                'brand_customers_faq_description' => null,
                'brand_tone_of_voice_description' => null,
                'brand_differentiators_description' => null,
                'brand_customers_needs_description' => null,
                'brand_content_opportunities_description' => null,
                'brand_customers_valued_aspects_description' => 'La atención de Marta.',
            ],
            'summary' => 'Valoran la atención; la demora quedó atrás.',
            'insights' => [
                ['body' => 'La demora ya no aparece.', 'topic_keys' => ['pains_1']],
                ['body' => 'El puntaje subió.', 'topic_keys' => []],
            ],
        ]));
    }


    // Los temas de una tanda según el texto de cada reseña. La queja por la demora suma un ID que no es de la tanda,
    // que el service tiene que descartar.
    private function batchTopics(array $reviews): array
    {
        $reviewIdsWithText = fn (string $text): array => array_column(
            array_filter($reviews, fn (array $review): bool => str_contains($review['text'], $text)), 'id',
        );
        $topics = fn (string $topic, array $reviewIds): array => $reviewIds === []
            ? []
            : [['topic' => $topic, 'review_ids' => $reviewIds, 'highlight_id' => $reviewIds[0]]];
        $delayReviewIds = $reviewIdsWithText('demora');
        if ($delayReviewIds !== []) {
            $delayReviewIds[] = 999999;
        }

        return [
            'pains' => [
                ...$topics('Demora en el delivery', $delayReviewIds),
                ...$topics('Precios altos', $reviewIdsWithText('precios')),
            ],
            'strengths' => $topics('Atención de Marta', $reviewIdsWithText('Marta')),
            'facts' => $topics('Hay estacionamiento', $reviewIdsWithText('estacionamiento')),
            'profiles' => [],
            'products' => [],
            'staff' => [],
        ];
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


    // Reseñas con la forma en que las devuelve Apify, con los datos de la ficha repetidos en cada una.
    private function apifyReviews(int $count, ?string $text, int $stars, string $publishedAt): array
    {
        $apifyReviews = [];
        for ($reviewNumber = 1; $reviewNumber <= $count; $reviewNumber++) {
            $this->apifyReviewsCount++;
            $apifyReviews[] = [
                'reviewId' => "review{$this->apifyReviewsCount}",
                'reviewUrl' => "https://www.google.com/maps/reviews/review{$this->apifyReviewsCount}",
                'name' => "Cliente {$this->apifyReviewsCount}",
                'isLocalGuide' => false,
                'reviewerNumberOfReviews' => 3,
                'text' => $text,
                'stars' => $stars,
                'likesCount' => 0,
                'publishedAtDate' => "{$publishedAt}T15:00:00.000Z",
                'responseFromOwnerText' => '¡Gracias!',
                'responseFromOwnerDate' => "{$publishedAt}T18:00:00.000Z",
                'reviewDetailedRating' => [],
                'reviewContext' => [],
                'title' => 'Mi marca',
                'totalScore' => 4.8,
                'reviewsCount' => 619,
            ];
        }

        return $apifyReviews;
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}

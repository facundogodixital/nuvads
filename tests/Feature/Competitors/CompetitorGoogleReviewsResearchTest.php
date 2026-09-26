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
use GuzzleHttp\Promise\PromiseInterface;
use App\Services\CompetitorSourceService;
use App\Services\CompetitorResearchRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\Competitors\ResearchBrandCompetitionJob;
use App\Jobs\Research\Competitors\ResearchCompetitorGoogleReviewsJob;


class CompetitorGoogleReviewsResearchTest extends TestCase
{

    use RefreshDatabase;

    private Competitor $competitor;
    private int $apifyReviewsCount = 0;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Sleep::fake();
        config()->set('services.apify.api_key', 'testing-key');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchCompetitorGoogleReviewsJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchCompetitorGoogleReviewsJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $this->competitor = resolve(CompetitorService::class)->create($brand, [
            'name' => 'Vivero Sur',
            'google_maps_url' => 'https://maps.app.goo.gl/viverosur',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // Reemplaza las reseñas anteriores, unifica dos tandas sin IDs ajenos y deja una queja chica con su peso; mezcla
    // los campos, encola el cruce y la pantalla lee métricas, quejas, elogios y destacadas.
    #[Test]
    public function analyzes_the_reviews_and_merges_the_competitor_fields(): void
    {
        $previousReview = resolve(CompetitorSourceService::class)->create($this->competitor, [
            'type' => 'google_review', 'title' => 'Reseña vieja', 'status' => 'ready',
        ]);
        // 205 elogios, 3 quejas y 2 reseñas sin texto.
        $apifyReviews = [
            ...$this->apifyReviews(205, 'Muy buena variedad de plantas', 5),
            ...$this->apifyReviews(3, 'Tardan mucho en responder', 2),
            ...$this->apifyReviews(2, null, 4),
        ];
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($apifyReviews),
            'https://api.openai.com/v1/responses' => $this->fakeOpenAiResponse(...),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchCompetitorGoogleReviewsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertCount(210, $researchRun->competitor_source_ids);
        $this->assertSoftDeleted($previousReview);
        $this->assertCount(4, $this->recordedOpenAiRequests());
        $competitorWeaknesses = $this->competitor->fresh()->competitor_weaknesses_description;
        $this->assertSame('Tarda en responder consultas.', $competitorWeaknesses);
        Queue::assertPushedOn('research_queue', ResearchBrandCompetitionJob::class);

        $googleReviewsInsights = $this->getJson("/api/competitors/{$this->competitor->id}/insights/google-reviews")
            ->assertOk()
            ->json('data');
        $this->assertSame(4.7, $googleReviewsInsights['analysis']['payload']['metrics']['google_total_score']);
        $pain = $googleReviewsInsights['pains'][0];
        $this->assertSame('Demora en responder', $pain['body']);
        $this->assertCount(3, $pain['competitor_source_ids']);
        $this->assertSame(0.014, $pain['payload']['mentions_share']);
        $this->assertSame(205, $googleReviewsInsights['strengths'][0]['payload']['mentions_count']);
        $this->assertCount(1, $googleReviewsInsights['insights']);
        $highlightedReviewIds = array_column($googleReviewsInsights['reviews'], 'id');
        $this->assertContains($pain['payload']['highlight_ids'][0], $highlightedReviewIds);
    }


    // Reseñas con solo estrellas no tienen nada para analizar: la investigación termina vacía con un mensaje, sin
    // consultar al modelo, y las reseñas anteriores siguen vigentes.
    #[Test]
    public function ends_empty_when_the_reviews_have_no_text(): void
    {
        $previousReview = resolve(CompetitorSourceService::class)->create($this->competitor, [
            'type' => 'google_review', 'title' => 'Reseña anterior', 'status' => 'ready',
        ]);
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::response($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($this->apifyReviews(3, null, 5)),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchCompetitorGoogleReviewsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('empty', $researchRun->status);
        $this->assertNotNull($researchRun->status_message);
        $this->assertSame([], $this->recordedOpenAiRequests());
        $this->assertNotSoftDeleted($previousReview);
        Queue::assertNotPushed(ResearchBrandCompetitionJob::class);
    }


    private function createResearchRun(): CompetitorResearchRun
    {
        return resolve(CompetitorResearchRunService::class)->create($this->competitor, 'google_reviews');
    }


    private function recordedOpenAiRequests(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)->map(fn (array $pair): Request => $pair[0])->values()->all();
    }


    // Simula al modelo según la consulta: en cada tanda arma los temas leyendo los textos, en la unificación agrupa
    // los temas con el mismo nombre, y en el análisis final devuelve los campos y una conclusión.
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
            'matches_competitor' => true,
            'competitor' => [
                'competitor_offer_description' => null,
                'competitor_customers_description' => null,
                'competitor_strengths_description' => 'La variedad de plantas.',
                'competitor_weaknesses_description' => 'Tarda en responder consultas.',
                'competitor_communication_description' => null,
                'competitor_differentiators_description' => null,
            ],
            'summary' => 'Elogian la variedad; unos pocos se quejan de la demora.',
            'insights' => ['La demora en responder es un caso aislado.'],
        ]));
    }


    // Los temas de una tanda según el texto de cada reseña. La queja suma un ID que no es de la tanda, que el service
    // tiene que descartar.
    private function batchTopics(array $reviews): array
    {
        $reviewIdsWithText = fn (string $text): array => array_column(
            array_filter($reviews, fn (array $review): bool => str_contains($review['text'], $text)), 'id',
        );
        $topics = fn (string $topic, array $reviewIds): array => $reviewIds === []
            ? []
            : [['topic' => $topic, 'review_ids' => $reviewIds, 'highlight_id' => $reviewIds[0]]];
        $delayReviewIds = $reviewIdsWithText('Tardan');
        if ($delayReviewIds !== []) {
            $delayReviewIds[] = 999999;
        }

        return [
            'pains' => $topics('Demora en responder', $delayReviewIds),
            'strengths' => $topics('Variedad de plantas', $reviewIdsWithText('variedad')),
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
    private function apifyReviews(int $count, ?string $text, int $stars): array
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
                'publishedAtDate' => '2026-09-10T15:00:00.000Z',
                'responseFromOwnerText' => null,
                'responseFromOwnerDate' => null,
                'title' => 'Vivero Sur',
                'totalScore' => 4.7,
                'reviewsCount' => 812,
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

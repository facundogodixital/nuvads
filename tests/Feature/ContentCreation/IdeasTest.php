<?php

namespace Tests\Feature\ContentCreation;

use Tests\TestCase;
use App\Models\Idea;
use App\Models\Brand;
use App\Models\ContentType;
use App\Helpers\SystemHelper;
use App\Services\IdeaService;
use App\Services\UserService;
use App\Services\BrandService;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeInsight;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use App\Services\ContentTypeService;
use Illuminate\Support\Facades\Http;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\ContentTypeSeeder;
use App\Services\KnowledgeSourceService;
use App\Services\KnowledgeInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\ContentTypeInputs\GoogleReviewsInput;
use App\Services\ContentTypeInputs\GoogleReviewStrengthsInput;


class IdeasTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;
    private ContentType $customerReviews;


    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.openai.api_key', 'testing-key');
        // El controller sube el límite de tiempo de PHP: en los tests no se toca el del proceso.
        $this->mock(SystemHelper::class)->shouldReceive('setTimeLimit');

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
        $this->seed(ContentTypeSeeder::class);
        $this->customerReviews = resolve(ContentTypeService::class)->findOneByKey('customer_reviews');
    }


    // Las entradas sortean, hasta su tope, entre las reseñas de 4 o 5 estrellas con texto que no están en ideas, o
    // entre todas las que sirven si ya están todas en ideas. Un grupo sin ninguna reseña que sirva no se manda.
    #[Test]
    public function inputs_send_only_usable_reviews_within_their_caps(): void
    {
        $usableKnowledgeSourceIds = [];
        for ($reviewNumber = 1; $reviewNumber <= 22; $reviewNumber++) {
            $usableKnowledgeSourceIds[] = $this->createGoogleReview(5, "Excelente atención {$reviewNumber}")->id;
        }
        $usedKnowledgeSource = $this->createGoogleReview(4, 'Me asesoraron muy bien');
        $reusedKnowledgeSource = $this->createGoogleReview(5, 'Tienen de todo');
        $threeStarsKnowledgeSource = $this->createGoogleReview(3, 'Me asesoraron, pero tardaron');
        $withoutTextKnowledgeSource = $this->createGoogleReview(5, null);
        resolve(IdeaService::class)->create($this->brand, [
            'content_type_id' => $this->customerReviews->id,
            'title' => 'Te asesoran y tienen de todo',
            'angle' => null,
            'knowledge_insight_ids' => [],
            'knowledge_source_ids' => [$usedKnowledgeSource->id, $reusedKnowledgeSource->id],
        ]);
        $attentionKnowledgeSourceIds = array_slice($usableKnowledgeSourceIds, 0, 10);
        $attentionStrength = $this->createStrength('Buena atención', $attentionKnowledgeSourceIds);
        $adviceStrength = $this->createStrength(
            'Asesoramiento', [$usableKnowledgeSourceIds[10], $usedKnowledgeSource->id],
        );
        $varietyStrength = $this->createStrength(
            'Variedad', [$reusedKnowledgeSource->id, $threeStarsKnowledgeSource->id],
        );
        $this->createStrength('Rapidez', [$threeStarsKnowledgeSource->id, $withoutTextKnowledgeSource->id]);
        // Una marca cuya única reseña que sirve ya está en una idea.
        $secondBrand = resolve(BrandService::class)->create($this->brand->client, ['name' => 'Segunda marca']);
        $secondBrandKnowledgeSource = $this->createGoogleReview(5, 'Volvería siempre', 'Ana', $secondBrand);
        resolve(IdeaService::class)->create($secondBrand, [
            'content_type_id' => $this->customerReviews->id,
            'title' => 'Vuelven siempre',
            'angle' => null,
            'knowledge_insight_ids' => [],
            'knowledge_source_ids' => [$secondBrandKnowledgeSource->id],
        ]);

        $reviewsMaterial = resolve(GoogleReviewsInput::class)->getMaterial($this->brand);
        $strengthsMaterial = resolve(GoogleReviewStrengthsInput::class)->getMaterial($this->brand);
        $secondBrandReviewsMaterial = resolve(GoogleReviewsInput::class)->getMaterial($secondBrand);

        $this->assertCount(20, $reviewsMaterial->knowledgeSourceIds);
        $this->assertSame([], array_diff($reviewsMaterial->knowledgeSourceIds, $usableKnowledgeSourceIds));
        $this->assertSame([$secondBrandKnowledgeSource->id], $secondBrandReviewsMaterial->knowledgeSourceIds);
        $sentKnowledgeInsightIds = [$attentionStrength->id, $adviceStrength->id, $varietyStrength->id];
        $this->assertEqualsCanonicalizing($sentKnowledgeInsightIds, $strengthsMaterial->knowledgeInsightIds);
        // Ocho de Buena atención, la que no se usó de Asesoramiento y la ya usada de Variedad.
        $this->assertCount(10, $strengthsMaterial->knowledgeSourceIds);
        $this->assertNotContains($usedKnowledgeSource->id, $strengthsMaterial->knowledgeSourceIds);
        $this->assertContains($reusedKnowledgeSource->id, $strengthsMaterial->knowledgeSourceIds);
        $this->assertSame([], array_diff($strengthsMaterial->knowledgeSourceIds, [
            ...$attentionKnowledgeSourceIds, $usableKnowledgeSourceIds[10], $reusedKnowledgeSource->id,
        ]));
    }


    // Manda al modelo la receta, el perfil de la marca y las reseñas; de la respuesta quita IDs y ángulos ajenos y
    // descarta ideas sin título o sin reseñas mandadas. Las ideas sugeridas viajan con sus reseñas y no se guardan.
    #[Test]
    public function proposes_ideas_keeping_only_what_was_sent_to_the_model(): void
    {
        resolve(BrandService::class)->update($this->brand, [
            'competitors_strengths_description' => 'Los competidores envían en el día.',
        ]);
        $mariaKnowledgeSource = $this->createGoogleReview(5, 'Me atendieron re bien', 'María González');
        $juanKnowledgeSource = $this->createGoogleReview(4, 'Muy buen asesoramiento', 'Juan');
        $strength = $this->createStrength('Buena atención', [$mariaKnowledgeSource->id, $juanKnowledgeSource->id]);
        $otherBrand = resolve(BrandService::class)->create(ClientFactory::new()->create(), ['name' => 'Otra marca']);
        $otherBrandKnowledgeSource = $this->createGoogleReview(5, 'Reseña de otra marca', 'Ana', $otherBrand);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openAiResponse(['ideas' => [
            [
                'title' => 'Lo que más repiten: que te atienden bien',
                'review_ids' => [$mariaKnowledgeSource->id, $otherBrandKnowledgeSource->id],
                'insight_id' => $strength->id,
                'angle' => null,
            ],
            [
                'title' => 'Una idea sin respaldo',
                'review_ids' => [$otherBrandKnowledgeSource->id],
                'insight_id' => null,
                'angle' => null,
            ],
            ['title' => ' ', 'review_ids' => [$juanKnowledgeSource->id], 'insight_id' => null, 'angle' => null],
            [
                'title' => 'Te eligen por el asesoramiento',
                'review_ids' => [(string) $juanKnowledgeSource->id],
                'insight_id' => 999999,
                'angle' => 'Un ángulo inventado',
            ],
        ]]))]);

        $response = $this->postJson("/api/content-types/{$this->customerReviews->id}/suggested-ideas");

        $response->assertOk()->assertExactJson(['data' => [
            [
                'content_type_id' => $this->customerReviews->id,
                'title' => 'Lo que más repiten: que te atienden bien',
                'angle' => null,
                'knowledge_insight_ids' => [$strength->id],
                'knowledge_source_ids' => [$mariaKnowledgeSource->id],
                'reviews' => [[
                    'id' => $mariaKnowledgeSource->id,
                    'name' => 'María',
                    'stars' => 5,
                    'date' => '2026-09-20',
                    'text' => 'Me atendieron re bien',
                ]],
            ],
            [
                'content_type_id' => $this->customerReviews->id,
                'title' => 'Te eligen por el asesoramiento',
                'angle' => null,
                'knowledge_insight_ids' => [],
                'knowledge_source_ids' => [$juanKnowledgeSource->id],
                'reviews' => [[
                    'id' => $juanKnowledgeSource->id,
                    'name' => 'Juan',
                    'stars' => 4,
                    'date' => '2026-09-20',
                    'text' => 'Muy buen asesoramiento',
                ]],
            ],
        ]]);
        Http::assertSent(function (Request $request): bool {
            $requestHasBrandProfile = str_contains($request['input'], 'Los competidores envían en el día.');
            $requestHasRecipe = str_contains($request['instructions'], $this->customerReviews->instructions);
            $requestHasReviews = str_contains($request['input'], 'Me atendieron re bien')
                && str_contains($request['input'], 'Muy buen asesoramiento');

            return $requestHasRecipe && $requestHasBrandProfile && $requestHasReviews;
        });
        $this->assertSame(0, Idea::query()->count());
    }


    // Un tipo que no existe responde 404 y uno sin material, su código, sin llamar al modelo. Si el modelo falla, la
    // respuesta trae un mensaje propio y no el detalle técnico de OpenAI.
    #[Test]
    public function answers_suggested_ideas_failures_with_their_codes(): void
    {
        // OpenAI corta la respuesta antes de terminarla: el helper lo informa con su propio error.
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'status' => 'incomplete', 'output' => [], 'incomplete_details' => ['reason' => 'max_output_tokens'],
        ])]);
        $customerReviewsUrl = "/api/content-types/{$this->customerReviews->id}/suggested-ideas";

        $this->postJson('/api/content-types/999999/suggested-ideas')->assertNotFound()
            ->assertJsonPath('code', 'not_found');
        $this->postJson($customerReviewsUrl)->assertUnprocessable()
            ->assertJsonPath('code', 'content_type_unavailable')
            ->assertJsonPath('message', 'Analiza tus reseñas de Google para usar este tipo.');
        Http::assertNothingSent();

        $this->createGoogleReview(5, 'Me atendieron re bien');
        $this->postJson($customerReviewsUrl)->assertStatus(502)
            ->assertJsonPath('code', 'idea_generation_failed')
            ->assertJsonPath('message', 'No pudimos generar las ideas. Vuelve a intentarlo.');
    }


    // Elegir una idea la guarda en la marca del pedido, en chosen y con el modelo de las ideas. Sin guardar nada,
    // rechaza en su campo un tipo que no existe, un ángulo que no es del tipo y las reseñas o conclusiones ajenas.
    #[Test]
    public function saves_the_chosen_idea_in_the_request_brand_and_rejects_foreign_references(): void
    {
        $secondBrand = resolve(BrandService::class)->create($this->brand->client, ['name' => 'Segunda marca']);
        $knowledgeSource = $this->createGoogleReview(5, 'Me atendieron re bien', 'María González', $secondBrand);
        $strength = $this->createStrength('Buena atención', [$knowledgeSource->id], $secondBrand);
        $firstBrandKnowledgeSource = $this->createGoogleReview(5, 'Reseña de la primera marca');
        $firstBrandStrength = $this->createStrength('Atención', [$firstBrandKnowledgeSource->id]);
        $chosenIdea = [
            'content_type_id' => $this->customerReviews->id,
            'title' => 'Lo que más repiten: que te atienden bien',
            'angle' => null,
            'knowledge_insight_ids' => [$strength->id],
            'knowledge_source_ids' => [$knowledgeSource->id],
        ];
        $this->withHeader('X-Brand-Id', (string) $secondBrand->id);

        $this->postJson('/api/ideas', [...$chosenIdea, 'content_type_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors(['content_type_id']);
        $this->postJson('/api/ideas', [...$chosenIdea, 'angle' => 'Explicá en pasos cortos cómo hacer algo.'])
            ->assertUnprocessable()->assertJsonValidationErrors(['angle']);
        $this->postJson('/api/ideas', [...$chosenIdea, 'knowledge_insight_ids' => [$firstBrandStrength->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['knowledge_insight_ids']);
        $this->postJson('/api/ideas', [...$chosenIdea, 'knowledge_source_ids' => [$firstBrandKnowledgeSource->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['knowledge_source_ids']);
        $ideaId = $this->postJson('/api/ideas', $chosenIdea)->assertCreated()
            ->assertJsonPath('data.brand_id', $secondBrand->id)
            ->assertJsonPath('data.status', 'chosen')
            ->assertJsonPath('data.model', config('content.ideas.model'))
            ->json('data.id');

        $this->assertSame([$ideaId], Idea::query()->pluck('id')->all());
    }


    // La lista trae solo las ideas guardadas de la marca del pedido que tienen el estado pedido, de la más nueva a la
    // más vieja.
    #[Test]
    public function lists_the_request_brand_ideas_with_the_requested_status_from_newest_to_oldest(): void
    {
        $ideaService = resolve(IdeaService::class);
        $secondBrand = resolve(BrandService::class)->create($this->brand->client, ['name' => 'Segunda marca']);
        $ideaAttributes = [
            'content_type_id' => $this->customerReviews->id,
            'title' => 'Lo que más repiten: que te atienden bien',
            'angle' => null,
            'knowledge_insight_ids' => [],
            'knowledge_source_ids' => [],
        ];
        $olderIdea = $ideaService->create($this->brand, $ideaAttributes);
        $ideaService->create($secondBrand, $ideaAttributes);
        $ideaWithAnotherStatus = $ideaService->create($this->brand, $ideaAttributes);
        // Un estado cualquiera distinto de chosen: status es un texto abierto.
        $ideaWithAnotherStatus->update(['status' => 'another_status']);
        $newerIdea = $ideaService->create($this->brand, $ideaAttributes);

        $response = $this->getJson('/api/ideas?status=chosen');

        $response->assertOk();
        $this->assertSame([$newerIdea->id, $olderIdea->id], $response->json('data.*.id'));
    }


    private function createGoogleReview(
        int $stars,
        ?string $text,
        string $authorName = 'María González',
        ?Brand $brand = null,
    ): KnowledgeSource {
        return resolve(KnowledgeSourceService::class)->create($brand ?? $this->brand, [
            'type' => 'google_review',
            'title' => $text ?? "{$stars} estrellas, sin texto",
            'status' => 'ready',
            'payload' => [
                'text' => $text,
                'stars' => $stars,
                'published_at' => '2026-09-20',
                'author' => ['name' => $authorName],
            ],
        ]);
    }


    // Un elogio vigente de las reseñas, con tantas menciones como reseñas.
    private function createStrength(string $topic, array $knowledgeSourceIds, ?Brand $brand = null): KnowledgeInsight
    {
        return resolve(KnowledgeInsightService::class)->create($brand ?? $this->brand, [
            'type' => 'google_reviews_strength',
            'body' => $topic,
            'status' => 'active',
            'level' => 1,
            'payload' => ['mentions_count' => count($knowledgeSourceIds)],
            'knowledge_source_ids' => $knowledgeSourceIds,
        ]);
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}

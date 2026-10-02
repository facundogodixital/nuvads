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
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use App\Services\ContentTypeService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\ContentTypeSeeder;
use App\Services\KnowledgeSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;


class PiecesTest extends TestCase
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


    // Manda al modelo receta, reseñas enteras e indicaciones. El guión sigue la idea sin borradas, con los datos de
    // cada reseña; un corte literal queda y uno desordenado vuelve entero; la placa única es la más fuerte.
    #[Test]
    public function writes_the_suggested_piece_keeping_the_customers_words(): void
    {
        // Más de 300 caracteres: al proponer ideas se habría cortado.
        $repeatedSentences = str_repeat('Todo impecable y bien embalado. ', 10);
        $longText = "Pedí un lunes y el miércoles ya lo tenía en casa. {$repeatedSentences}Volveré a comprar.";
        $marcelaKnowledgeSource = $this->createGoogleReview(5, $longText, 'Marcela Pérez');
        $deletedKnowledgeSource = $this->createGoogleReview(5, 'Rapidísimo el envío.', 'Lucía');
        $juanKnowledgeSource = $this->createGoogleReview(4, 'Llegó antes de lo que me dijeron. Muy contento.', 'Juan');
        $idea = $this->createIdea([
            $juanKnowledgeSource->id, $deletedKnowledgeSource->id, $marcelaKnowledgeSource->id,
        ]);
        // Una investigación nueva de Google borró una de las reseñas de la idea.
        resolve(KnowledgeSourceService::class)->deleteByTypeExceptIds(
            $this->brand, 'google_review', [$marcelaKnowledgeSource->id, $juanKnowledgeSource->id],
        );
        // El modelo devuelve un corte literal con estrellas, la reseña borrada y un corte con fragmentos desordenados.
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openAiResponse([
            'reviews' => [
                [
                    'id' => $juanKnowledgeSource->id,
                    'text' => 'Llegó antes de lo que me dijeron... Muy contento.',
                    'stars' => 1,
                ],
                ['id' => $deletedKnowledgeSource->id, 'text' => 'Rapidísimo el envío.'],
                [
                    'id' => $marcelaKnowledgeSource->id,
                    'text' => 'Volveré a comprar... Pedí un lunes y el miércoles ya lo tenía en casa.',
                ],
            ],
            'strongest_review_id' => $marcelaKnowledgeSource->id,
            'closing_texts' => ['¿Querés el tuyo esta semana? Escribinos.'],
            'copies' => ['No lo decimos nosotros: lo dicen quienes ya recibieron su pedido.'],
        ]))]);

        $response = $this->postJson("/api/ideas/{$idea->id}/suggested-piece", [
            'instructions' => 'Que suene más cercano.',
        ]);

        $marcelaReview = [
            'id' => $marcelaKnowledgeSource->id,
            'name' => 'Marcela',
            'stars' => 5,
            'date' => '2026-09-20',
            'text' => $longText,
        ];
        $juanReview = [
            'id' => $juanKnowledgeSource->id,
            'name' => 'Juan',
            'stars' => 4,
            'date' => '2026-09-20',
            'text' => 'Llegó antes de lo que me dijeron... Muy contento.',
        ];
        $response->assertOk()
            ->assertJsonPath('data.carousel_script', [$juanReview, $marcelaReview])
            ->assertJsonPath('data.single_script', [$marcelaReview]);
        Http::assertSent(function (Request $request) use ($longText): bool {
            $requestHasRecipe = str_contains($request['instructions'], $this->customerReviews->instructions);
            $requestHasWholeReview = str_contains($request['input'], $longText);
            $requestHasUserInstructions = str_contains($request['input'], 'Que suene más cercano.');

            return $requestHasRecipe && $requestHasWholeReview && $requestHasUserInstructions;
        });
    }


    // Una idea de una sola reseña trae solo la placa única con esa reseña y sus copies, sin carrusel ni textos de placa
    // final aunque el modelo los mande. Un ID repetido en la idea cuenta una sola vez.
    #[Test]
    public function writes_only_the_single_image_for_an_idea_with_one_review(): void
    {
        $knowledgeSource = $this->createGoogleReview(5, 'Me atendieron re bien', 'María González');
        $idea = $this->createIdea([$knowledgeSource->id, $knowledgeSource->id]);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openAiResponse([
            'reviews' => [['id' => $knowledgeSource->id, 'text' => 'Me atendieron re bien']],
            'strongest_review_id' => $knowledgeSource->id,
            'closing_texts' => ['Te esperamos.'],
            'copies' => ['Así nos cuentan nuestros clientes.', 'Gracias, María.'],
        ]))]);

        $response = $this->postJson("/api/ideas/{$idea->id}/suggested-piece");

        $response->assertOk()
            ->assertJsonPath('data.closing_texts', [])
            ->assertJsonPath('data.single_script', [[
                'id' => $knowledgeSource->id,
                'name' => 'María',
                'stars' => 5,
                'date' => '2026-09-20',
                'text' => 'Me atendieron re bien',
            ]])
            ->assertJsonPath('data.copies', ['Así nos cuentan nuestros clientes.', 'Gracias, María.']);
        // El carrusel viene en null, no ausente.
        $this->assertArrayHasKey('carousel_script', $response->json('data'));
        $this->assertNull($response->json('data.carousel_script'));
    }


    // Sin llamar al modelo: 404 con una idea inexistente o de otra marca, y 422 con una idea sin sus reseñas. Sin
    // copies, sin placa final o con error del helper: 502 con mensaje propio. Con error del proveedor: 500.
    #[Test]
    public function answers_suggested_piece_failures_with_their_codes(): void
    {
        $mariaKnowledgeSource = $this->createGoogleReview(5, 'Me atendieron re bien');
        $juanKnowledgeSource = $this->createGoogleReview(4, 'Muy buen asesoramiento', 'Juan');
        $deletedKnowledgeSource = $this->createGoogleReview(5, 'Rapidísimo el envío');
        $idea = $this->createIdea([$mariaKnowledgeSource->id, $juanKnowledgeSource->id]);
        $ideaWithoutReviews = $this->createIdea([$deletedKnowledgeSource->id]);
        resolve(KnowledgeSourceService::class)->deleteByTypeExceptIds(
            $this->brand, 'google_review', [$mariaKnowledgeSource->id, $juanKnowledgeSource->id],
        );
        $secondBrand = resolve(BrandService::class)->create($this->brand->client, ['name' => 'Segunda marca']);
        $secondBrandKnowledgeSource = $this->createGoogleReview(5, 'Volvería siempre', 'Ana', $secondBrand);
        $secondBrandIdea = $this->createIdea([$secondBrandKnowledgeSource->id], $secondBrand);
        $writtenPiece = [
            'reviews' => [
                ['id' => $mariaKnowledgeSource->id, 'text' => 'Me atendieron re bien'],
                ['id' => $juanKnowledgeSource->id, 'text' => 'Muy buen asesoramiento'],
            ],
            'strongest_review_id' => $mariaKnowledgeSource->id,
            'closing_texts' => ['Te esperamos.'],
            'copies' => ['Así nos cuentan nuestros clientes.'],
        ];
        Http::fake(['https://api.openai.com/v1/responses' => Http::sequence()
            ->push($this->openAiResponse([...$writtenPiece, 'copies' => []]))
            ->push($this->openAiResponse([...$writtenPiece, 'closing_texts' => [' ']]))
            // OpenAI corta la respuesta antes de terminarla: el helper lo informa con su propio error.
            ->push([
                'status' => 'incomplete', 'output' => [], 'incomplete_details' => ['reason' => 'max_output_tokens'],
            ])
            ->push(['error' => ['message' => 'The server had an error while processing your request.']], 500),
        ]);
        $ideaUrl = "/api/ideas/{$idea->id}/suggested-piece";
        $failureMessage = 'No pudimos escribir la pieza. Vuelve a intentarlo.';

        $this->postJson('/api/ideas/999999/suggested-piece')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->postJson("/api/ideas/{$secondBrandIdea->id}/suggested-piece")->assertNotFound()
            ->assertJsonPath('code', 'not_found');
        $this->postJson("/api/ideas/{$ideaWithoutReviews->id}/suggested-piece")->assertUnprocessable()
            ->assertJsonPath('code', 'idea_material_missing')
            ->assertJsonPath('message', 'Las reseñas de esta idea ya no están en tu marca. Busca otras ideas.');
        Http::assertNothingSent();

        $this->postJson($ideaUrl)->assertStatus(502)->assertJsonPath('code', 'piece_generation_failed')
            ->assertJsonPath('message', $failureMessage);
        $this->postJson($ideaUrl)->assertStatus(502)->assertJsonPath('code', 'piece_generation_failed')
            ->assertJsonPath('message', $failureMessage);
        $this->postJson($ideaUrl)->assertStatus(502)->assertJsonPath('code', 'piece_generation_failed')
            ->assertJsonPath('message', $failureMessage);
        $this->postJson($ideaUrl)->assertStatus(500)->assertJsonPath('code', 'internal_error');
    }


    private function createGoogleReview(
        int $stars,
        string $text,
        string $authorName = 'María González',
        ?Brand $brand = null,
    ): KnowledgeSource {
        return resolve(KnowledgeSourceService::class)->create($brand ?? $this->brand, [
            'type' => 'google_review',
            'title' => 'Reseña de Google',
            'status' => 'ready',
            'payload' => [
                'text' => $text,
                'stars' => $stars,
                'published_at' => '2026-09-20',
                'author' => ['name' => $authorName],
            ],
        ]);
    }


    // Una idea guardada de Reseñas de clientes con esas reseñas, en ese orden.
    private function createIdea(array $knowledgeSourceIds, ?Brand $brand = null): Idea
    {
        return resolve(IdeaService::class)->create($brand ?? $this->brand, [
            'content_type_id' => $this->customerReviews->id,
            'title' => 'Tus compras llegan rápido',
            'angle' => null,
            'knowledge_insight_ids' => [],
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

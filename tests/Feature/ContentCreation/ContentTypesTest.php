<?php

namespace Tests\Feature\ContentCreation;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\ContentType;
use App\Services\UserService;
use App\Services\BrandService;
use App\Models\KnowledgeSource;
use Database\Factories\UserFactory;
use App\Services\ContentTypeService;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\ContentTypeSeeder;
use App\Services\KnowledgeSourceService;
use App\Services\KnowledgeInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;


class ContentTypesTest extends TestCase
{

    use RefreshDatabase;


    // Los tipos activos traen los datos de su tarjeta y si la marca los puede usar: hace falta material en una entrada
    // que sostenga ideas, no alcanza el puntaje. Si no, dicen qué le falta a esa marca, o "Disponible pronto".
    #[Test]
    public function lists_active_content_types_with_card_fields_and_availability(): void
    {
        $user = UserFactory::new()->owner()->create();
        $brandService = resolve(BrandService::class);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $brand = $brandService->create($user->client, ['name' => 'Mi marca']);
        $brandWithoutReviews = $brandService->create($user->client, ['name' => 'Marca sin reseñas']);
        $brandWithLowReviews = $brandService->create($user->client, ['name' => 'Marca con reseñas de 3 estrellas']);
        $this->createGoogleReview($brand, 5);
        // Reseñas ya analizadas, con puntaje en la ficha, pero ninguna de 4 o 5 estrellas.
        $this->createGoogleReview($brandWithLowReviews, 3);
        resolve(KnowledgeInsightService::class)->create($brandWithLowReviews, [
            'type' => 'google_reviews_metrics',
            'body' => 'Métricas de las reseñas de Google.',
            'status' => 'active',
            'level' => 1,
            'payload' => ['google_total_score' => 3.2, 'google_reviews_count' => 14],
        ]);
        $this->createRotatedContentType('customer_reviews');
        $customerReviews = $this->createContentType(
            'customer_reviews', ['google_review_score', 'whatsapp_questions', 'google_reviews'],
        );
        $educational = $this->createContentType('educational', ['whatsapp_questions', 'audio_insights']);
        $this->withToken($credentials['token']);

        $response = $this->getJson('/api/content-types');
        $withoutReviewsResponse = $this->withHeader('X-Brand-Id', (string) $brandWithoutReviews->id)
            ->getJson('/api/content-types');
        $lowReviewsResponse = $this->withHeader('X-Brand-Id', (string) $brandWithLowReviews->id)
            ->getJson('/api/content-types');

        $soonMessage = 'Disponible pronto';
        $cardFields = ['id', 'key', 'name', 'description'];
        $analyzeMessage = 'Analiza tus reseñas de Google para usar este tipo.';
        $notEnoughMessage = 'Tus reseñas de Google todavía no alcanzan: hacen falta reseñas de 4 o 5 estrellas con '
            .'texto.';
        $response->assertOk()->assertExactJson(['data' => [
            [...$customerReviews->only($cardFields), 'is_available' => true, 'unavailable_reason' => null],
            [...$educational->only($cardFields), 'is_available' => false, 'unavailable_reason' => $soonMessage],
        ]]);
        $withoutReviewsResponse->assertJsonPath('data.0.is_available', false)
            ->assertJsonPath('data.0.unavailable_reason', $analyzeMessage);
        $lowReviewsResponse->assertJsonPath('data.0.is_available', false)
            ->assertJsonPath('data.0.unavailable_reason', $notEnoughMessage);
    }


    // Un tipo rotado conserva su key: la búsqueda por key devuelve el tipo activo, y ninguno si no queda uno activo.
    #[Test]
    public function finds_only_the_active_content_type_of_a_key(): void
    {
        $this->createRotatedContentType('educational');
        $educational = $this->createContentType('educational');
        $this->createRotatedContentType('customer_reviews');
        $contentTypeService = resolve(ContentTypeService::class);

        $foundEducational = $contentTypeService->findOneByKey('educational');
        $foundCustomerReviews = $contentTypeService->findOneByKey('customer_reviews');

        $this->assertTrue($foundEducational->is($educational));
        $this->assertNull($foundCustomerReviews);
    }


    // El seeder carga Reseñas de clientes y Educativo; correrlo de nuevo no duplica ni pisa los tipos que ya existen.
    #[Test]
    public function seeds_the_two_content_types_without_duplicating_or_overwriting(): void
    {
        $this->seed(ContentTypeSeeder::class);
        ContentType::query()->where('key', 'educational')->update(['name' => 'Educativo editado']);

        $this->seed(ContentTypeSeeder::class);

        $contentTypes = ContentType::query()->orderBy('id')->get();
        $this->assertSame(['customer_reviews', 'educational'], $contentTypes->pluck('key')->all());
        $this->assertSame('Educativo editado', $contentTypes->firstWhere('key', 'educational')->name);
    }


    // La base no admite dos tipos activos con la misma key.
    #[Test]
    public function rejects_two_active_content_types_with_the_same_key(): void
    {
        $this->createContentType('educational');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->createContentType('educational');
    }


    private function createGoogleReview(Brand $brand, int $stars): KnowledgeSource
    {
        return resolve(KnowledgeSourceService::class)->create($brand, [
            'type' => 'google_review',
            'title' => 'Buena atención',
            'status' => 'ready',
            'payload' => [
                'text' => 'Buena atención',
                'stars' => $stars,
                'published_at' => '2026-09-20',
                'author' => ['name' => 'María González'],
            ],
        ]);
    }


    private function createContentType(string $key, array $inputs = ['google_reviews']): ContentType
    {
        return resolve(ContentTypeService::class)->create([
            'key' => $key,
            'name' => "Nombre de {$key}",
            'description' => "Bajada de {$key}",
            'instructions' => 'Receta del tipo para el modelo.',
            'inputs' => $inputs,
        ]);
    }


    // Crea un tipo ya rotado, como quedará al cambiarlo: baja lógica, con el momento del borrado en deleted_at_ts.
    private function createRotatedContentType(string $key): ContentType
    {
        $deletedAt = now();
        $contentType = $this->createContentType($key);
        $contentType->forceFill(['deleted_at' => $deletedAt, 'deleted_at_ts' => $deletedAt->getTimestamp()])->save();

        return $contentType;
    }

}

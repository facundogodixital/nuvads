<?php

namespace Tests\Feature\ContentCreation;

use Tests\TestCase;
use App\Models\ContentType;
use App\Services\UserService;
use App\Services\BrandService;
use Database\Factories\UserFactory;
use App\Services\ContentTypeService;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\ContentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\UniqueConstraintViolationException;


class ContentTypesTest extends TestCase
{

    use RefreshDatabase;


    // El listado trae solo los tipos activos, por orden de alta, y de cada uno solo lo que muestra su tarjeta: la
    // receta, las entradas, los ángulos, las composiciones y las fechas no salen al frontend.
    #[Test]
    public function lists_active_content_types_with_card_fields_only(): void
    {
        $user = UserFactory::new()->owner()->create();
        resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->createRotatedContentType('customer_reviews');
        $customerReviews = $this->createContentType('customer_reviews');
        $educational = $this->createContentType('educational');

        $response = $this->withToken($credentials['token'])->getJson('/api/content-types');

        $response->assertOk()->assertExactJson(['data' => [
            $customerReviews->only(['id', 'key', 'name', 'description']),
            $educational->only(['id', 'key', 'name', 'description']),
        ]]);
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


    private function createContentType(string $key): ContentType
    {
        return resolve(ContentTypeService::class)->create([
            'key' => $key,
            'name' => "Nombre de {$key}",
            'description' => "Bajada de {$key}",
            'instructions' => 'Receta del tipo para el modelo.',
            'inputs' => ['google_reviews'],
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

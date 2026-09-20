<?php

namespace Tests\Feature\Brands;

use Mockery;
use Tests\TestCase;
use RuntimeException;
use App\Models\Client;
use App\Services\BrandService;
use App\Services\ClientService;
use App\Repositories\BrandRepository;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;


class BrandCreationTest extends TestCase
{

    use RefreshDatabase;


    // Crear un cliente debe persistir su marca inicial con timestamps y ambas relaciones disponibles.
    #[Test]
    public function creates_client_with_default_brand(): void
    {
        $client = resolve(ClientService::class)->create(['login_identifier' => 'new-client']);

        $brand = $client->brands()->sole();
        $this->assertSame('Tu marca', $brand->name);
        $this->assertTrue($brand->client->is($client));
        $this->assertNotNull($brand->created_at);
        $this->assertNotNull($brand->updated_at);
        $this->assertNull($brand->deleted_at);
    }


    // Un fallo de persistencia de la marca debe deshacer también la creación del cliente.
    #[Test]
    public function rolls_back_client_when_brand_creation_fails(): void
    {
        $clientExistedBeforeFailure = false;
        $brandRepository = Mockery::mock(BrandRepository::class);
        $brandRepository->shouldReceive('create')->once()->andReturnUsing(
            function (Client $client, string $name) use (&$clientExistedBeforeFailure): never {
                $clientExistedBeforeFailure = Client::query()->whereKey($client->id)->exists();
                throw new RuntimeException('Simulated brand persistence failure');
            },
        );
        $this->instance(BrandRepository::class, $brandRepository);

        try {
            resolve(ClientService::class)->create(['login_identifier' => 'failed-client']);
            $this->fail('Expected brand creation to fail');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated brand persistence failure', $exception->getMessage());
        }

        $this->assertTrue($clientExistedBeforeFailure);
        $this->assertDatabaseCount('brands', 0);
        $this->assertDatabaseCount('clients', 0);
    }


    // Cada cliente puede tener varias marcas; sus relaciones no deben incluir las de otro cliente ni las dadas de baja.
    #[Test]
    public function keeps_multiple_brands_associated_with_their_client_and_supports_soft_deletes(): void
    {
        $client = ClientFactory::new()->create();
        $brandService = resolve(BrandService::class);
        $otherClient = ClientFactory::new()->create();

        $firstBrand = $brandService->create($client, 'Primera marca');
        $secondBrand = $brandService->create($client, 'Segunda marca');
        $otherBrand = $brandService->create($otherClient, 'Otra marca');

        $this->assertSame([$firstBrand->id, $secondBrand->id], $client->brands()->orderBy('id')->pluck('id')->all());
        $this->assertSame([$otherBrand->id], $otherClient->brands->modelKeys());

        $firstBrand->delete();

        $this->assertSoftDeleted($firstBrand);
        $this->assertSame([$secondBrand->id], $client->brands()->pluck('id')->all());
    }

}

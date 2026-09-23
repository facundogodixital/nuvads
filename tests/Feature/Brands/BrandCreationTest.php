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


    // Crear un cliente debe persistir su marca inicial asociada a ese cliente.
    #[Test]
    public function creates_client_with_default_brand(): void
    {
        $client = resolve(ClientService::class)->create(['login_identifier' => 'new-client']);

        $brand = $client->brands()->sole();
        $this->assertSame('Tu marca', $brand->name);
        $this->assertTrue($brand->client->is($client));
    }


    // La creación recibe todos los atributos y siempre conserva el cliente indicado por el servicio.
    #[Test]
    public function creates_brand_with_source_attributes(): void
    {
        $client = ClientFactory::new()->create();
        $otherClient = ClientFactory::new()->create();

        $brand = resolve(BrandService::class)->create($client, [
            'name' => 'Mi marca',
            'client_id' => $otherClient->id,
            'website_url' => 'https://example.com',
            'instagram_username' => 'mi.marca',
            'google_maps_url' => 'https://maps.app.goo.gl/example',
        ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'client_id' => $client->id,
            'website_url' => 'https://example.com',
            'instagram_username' => 'mi.marca',
            'google_maps_url' => 'https://maps.app.goo.gl/example',
        ]);
    }


    // Un fallo de persistencia de la marca debe deshacer también la creación del cliente.
    #[Test]
    public function rolls_back_client_when_brand_creation_fails(): void
    {
        $clientExistedBeforeFailure = false;
        $brandRepository = Mockery::mock(BrandRepository::class);
        $brandRepository->shouldReceive('create')->once()->andReturnUsing(
            function (Client $client, array $attributes) use (&$clientExistedBeforeFailure): never {
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

}

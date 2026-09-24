<?php

namespace Tests\Feature\Brands;

use Tests\TestCase;
use App\Services\UserService;
use App\Services\BrandService;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;


class BrandSelectionTest extends TestCase
{

    use RefreshDatabase;


    // Cada pedido trabaja con la marca de X-Brand-Id. Sin header, o con una marca de otro cliente, trabaja con la
    // primera marca del cliente, sin tocar la ajena. auth/me lista todas las marcas del cliente.
    #[Test]
    public function works_with_the_brand_sent_in_the_header_only_within_the_client(): void
    {
        $user = UserFactory::new()->owner()->create();
        $otherUser = UserFactory::new()->owner()->create();
        $brandService = resolve(BrandService::class);
        $firstBrand = $brandService->create($user->client, ['name' => 'Primera marca']);
        $secondBrand = $brandService->create($user->client, ['name' => 'Segunda marca']);
        $otherBrand = $brandService->create($otherUser->client, ['name' => 'Otra marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);

        $this->getJson('/api/auth/me')->assertOk()
            ->assertJsonPath('data.brand.id', $firstBrand->id)
            ->assertJsonPath('data.brands.*.id', [$firstBrand->id, $secondBrand->id]);
        $this->withHeader('X-Brand-Id', (string) $secondBrand->id)
            ->patchJson('/api/brand', ['brand_offer_description' => 'Anuncios'])->assertOk()
            ->assertJsonPath('data.id', $secondBrand->id);
        $this->withHeader('X-Brand-Id', (string) $otherBrand->id)
            ->patchJson('/api/brand', ['brand_offer_description' => 'Ajena'])->assertOk()
            ->assertJsonPath('data.id', $firstBrand->id);

        $this->assertSame('Anuncios', $secondBrand->fresh()->brand_offer_description);
        $this->assertNull($otherBrand->fresh()->brand_offer_description);
    }

}

<?php

namespace Tests\Feature\Brands;

use Tests\TestCase;
use App\Services\UserService;
use App\Services\BrandService;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;


class BrandSourcesTest extends TestCase
{

    use RefreshDatabase;


    // Guardar un panel no debe borrar otros enlaces ni permitir modificar una marca ajena.
    #[Test]
    public function saves_reads_and_clears_sources_only_for_authenticated_brand(): void
    {
        $user = UserFactory::new()->owner()->create();
        $otherUser = UserFactory::new()->owner()->create();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $otherBrand = resolve(BrandService::class)->create($otherUser->client, ['name' => 'Otra marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);

        $this->withToken($credentials['token'])->patchJson('/api/brand', [
            'website_url' => 'https://example.com',
            'google_maps_url' => 'https://maps.app.goo.gl/example',
            'instagram_username' => '@Mi.Marca',
            'brand_id' => $otherBrand->id,
            'client_id' => $otherUser->client_id,
            'brand' => $otherBrand->id,
            'name' => 'Cambio no autorizado',
        ])->assertOk()->assertJsonPath('data.instagram_username', 'mi.marca');

        $this->patchJson('/api/brand', ['website_url' => 'https://example.org'])->assertOk();
        $this->getJson('/api/brand')->assertOk()
            ->assertJsonPath('data.id', $brand->id)
            ->assertJsonPath('data.client_id', $user->client_id)
            ->assertJsonStructure(['data' => ['created_at', 'updated_at', 'deleted_at']])
            ->assertJsonPath('data.name', 'Mi marca')
            ->assertJsonPath('data.website_url', 'https://example.org')
            ->assertJsonPath('data.instagram_username', 'mi.marca')
            ->assertJsonPath('data.google_maps_url', 'https://maps.app.goo.gl/example');

        $this->patchJson('/api/brand', ['instagram_username' => ''])->assertOk();
        $this->getJson('/api/brand')->assertOk()->assertJsonPath('data.instagram_username', null);
        $this->assertNull($brand->fresh()->instagram_username);
        $this->assertNull($otherBrand->fresh()->website_url);
        $this->assertSame($user->client_id, $brand->fresh()->client_id);
    }


    // Los usuarios, arrobas y enlaces de perfil se guardan con una representación consistente.
    #[Test]
    #[DataProvider('instagramProfiles')]
    public function normalizes_instagram_profiles(string $input): void
    {
        $user = UserFactory::new()->owner()->create();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);

        $this->withToken($credentials['token'])->patchJson('/api/brand', [
            'instagram_username' => $input,
        ])->assertOk()->assertJsonPath('data.instagram_username', 'mi.marca');

        $this->assertSame('mi.marca', $brand->fresh()->instagram_username);
    }


    public static function instagramProfiles(): array
    {
        return [
            ['mi.marca'],
            [' @Mi.Marca '],
            ['https://www.instagram.com/Mi.Marca/?igsh=example'],
            ['instagram.com/mi.marca/'],
        ];
    }


    // Entradas inválidas se rechazan antes de guardar y no alteran enlaces ya persistidos.
    #[Test]
    #[DataProvider('invalidSources')]
    public function rejects_invalid_sources(string $field, mixed $value): void
    {
        $user = UserFactory::new()->owner()->create();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $brand->update(['website_url' => 'https://example.com']);
        $credentials = resolve(UserService::class)->createApiToken($user);

        $this->withToken($credentials['token'])->patchJson('/api/brand', [$field => $value])
            ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors($field);

        $this->assertSame('https://example.com', $brand->fresh()->website_url);
        $this->assertNull($brand->fresh()->instagram_username);
    }


    public static function invalidSources(): array
    {
        return [
            ['website_url', 'javascript:alert(1)'],
            ['google_maps_url', 'not a url'],
            ['website_url', 'https://example.com/'.str_repeat('x', 2048)],
            ['instagram_username', ['invalid']],
            ['instagram_username', 'https://evil.example/mi.marca'],
            ['instagram_username', 'https://instagram.com/p/abc123/'],
            ['instagram_username', '@@mi.marca'],
        ];
    }


    // Los enlaces privados de cada marca requieren autenticación para leerlos y modificarlos.
    #[Test]
    public function requires_authentication(): void
    {
        $this->getJson('/api/brand')->assertUnauthorized();
        $this->patchJson('/api/brand', ['website_url' => null])->assertUnauthorized();
    }

}

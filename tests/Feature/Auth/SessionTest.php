<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Services\UserService;
use Illuminate\Routing\Route;
use App\Services\BrandService;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;


class SessionTest extends TestCase
{

    use RefreshDatabase;


    // Las páginas entregan la entrada de Vue; la API rechaza sin redirigir, incluso si se pide HTML.
    #[Test]
    public function serves_frontend_and_protects_api_without_redirects(): void
    {
        $this->get('/')->assertOk()->assertViewIs('app');
        $this->get('/lalala?tab=details')->assertOk()->assertViewIs('app');
        $this->get('/login')->assertOk()->assertViewIs('app');
        $this->get('/api/auth/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->get('/api/missing')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->get('/auth/missing')->assertNotFound();
    }


    // Toda ruta de la API, salvo el canje del código, rechaza con 401 a quien no presenta un Bearer token.
    // Recorre las rutas registradas, así cubre también los endpoints que se agreguen después.
    #[Test]
    public function requires_access_token_on_every_api_route(): void
    {
        $isProtectedApiRoute = function (Route $route): bool {
            $isApiRoute = str_starts_with($route->uri(), 'api/');
            $isLoginCodeExchange = $route->uri() === 'api/auth/exchange';

            return $isApiRoute && !$isLoginCodeExchange;
        };
        $apiRoutes = array_filter($this->app['router']->getRoutes()->getRoutes(), $isProtectedApiRoute);

        $this->assertNotEmpty($apiRoutes);
        foreach ($apiRoutes as $route) {
            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->json($route->methods()[0], $uri)->assertUnauthorized();
        }
    }


    // Una sesión web válida no sustituye al Bearer token de la API.
    #[Test]
    public function rejects_web_session_without_access_token(): void
    {
        $user = UserFactory::new()->owner()->create();

        $this->actingAs($user)->getJson('/api/auth/me')->assertUnauthorized();
    }


    // El contexto tipado pertenece al token; los campos enviados no pueden suplantar usuario ni cliente ni marca.
    #[Test]
    public function resolves_user_client_and_brand_from_token_without_cookies(): void
    {
        $user = UserFactory::new()->owner()->create();
        $otherUser = UserFactory::new()->owner()->create();
        $deletedBrand = resolve(BrandService::class)->create($user->client, ['name' => 'Marca anterior']);
        $deletedBrand->delete();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $otherBrand = resolve(BrandService::class)->create($otherUser->client, ['name' => 'Otra marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);

        $this->withToken($credentials['token'])->getJson('/api/auth/me?'.http_build_query([
            'user' => $otherUser->id,
            'brand' => $otherBrand->id,
            'client' => $otherUser->client_id,
        ]))->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.client.id', $user->client_id)
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonPath('data.brand.name', 'Mi marca')
            ->assertJsonPath('data.brand.client_id', $user->client_id)
            ->assertJsonPath('data.client.timezone', $user->client->timezone)
            ->assertJsonPath('data.user.is_owner', true)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.api_token_hash')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertHeaderMissing('Set-Cookie');
    }


    // El token se guarda como hash y deja de funcionar después de sus 24 horas de vigencia.
    #[Test]
    public function expires_access_token_after_twenty_four_hours(): void
    {
        $user = UserFactory::new()->owner()->create();
        resolve(BrandService::class)->create($user->client, ['name' => 'Tu marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $storedUser = $user->fresh();

        $this->assertSame(hash('sha256', $credentials['token']), $storedUser->api_token_hash);
        $this->withToken($credentials['token'])->getJson('/api/auth/me')->assertOk();
        $this->travel(24)->hours();
        $this->travel(1)->seconds();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }


    // Un nuevo acceso reemplaza al anterior; el logout revoca la credencial vigente del usuario.
    #[Test]
    public function replaces_previous_token_and_revokes_current_token_on_logout(): void
    {
        $user = UserFactory::new()->owner()->create();
        resolve(BrandService::class)->create($user->client, ['name' => 'Tu marca']);
        $firstLogin = resolve(UserService::class)->createApiToken($user);
        $currentLogin = resolve(UserService::class)->createApiToken($user);

        $this->withToken($firstLogin['token'])->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($currentLogin['token'])->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertNull($user->fresh()->api_token_hash);
        $this->assertNull($user->fresh()->api_token_expires_at);
    }


    // Deshabilitar la cuenta corta de inmediato el acceso de un token existente.
    // Las demás combinaciones de usuario y cliente bloqueados las cubre UserAccountAccessTest.
    #[Test]
    public function rejects_access_when_account_is_disabled(): void
    {
        $user = UserFactory::new()->owner()->create();
        $credentials = resolve(UserService::class)->createApiToken($user);
        $user->update(['is_enabled' => false]);

        $this->withToken($credentials['token'])->getJson('/api/auth/me')
            ->assertForbidden()->assertJsonPath('code', 'account_disabled');
    }

}

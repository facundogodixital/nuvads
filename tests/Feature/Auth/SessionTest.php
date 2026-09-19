<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Services\UserService;
use Database\Factories\UserFactory;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
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


    // Una sesión web válida no sustituye al Bearer token de la API.
    #[Test]
    public function rejects_web_session_without_access_token(): void
    {
        $user = UserFactory::new()->owner()->create();

        $this->actingAs($user)->getJson('/api/auth/me')->assertUnauthorized();
    }


    // El contexto tipado pertenece al token; los campos enviados no pueden suplantar usuario ni cliente.
    #[Test]
    public function resolves_user_and_client_from_token_without_cookies(): void
    {
        $user = UserFactory::new()->owner()->create();
        $otherUser = UserFactory::new()->owner()->create();
        $credentials = resolve(UserService::class)->createApiToken($user);

        $this->withToken($credentials['token'])->getJson('/api/auth/me?'.http_build_query([
            'user' => $otherUser->id,
            'client' => $otherUser->client_id,
            'authenticated_user' => $otherUser->id,
            'authenticated_client' => $otherUser->client_id,
        ]))->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.client.id', $user->client_id)
            ->assertHeaderMissing('Set-Cookie');
    }


    // El token se guarda como hash y deja de funcionar después de sus 24 horas de vigencia.
    #[Test]
    public function expires_access_token_after_twenty_four_hours(): void
    {
        $user = UserFactory::new()->owner()->create();
        $credentials = resolve(UserService::class)->createApiToken($user);
        $storedUser = $user->fresh();

        $this->assertSame(hash('sha256', $credentials['token']), $storedUser->api_token_hash);
        $this->withToken($credentials['token'])->getJson('/api/auth/me')->assertOk();
        $this->travel(24)->hours();
        $this->travel(1)->seconds();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }


    // Revocar en la base impide la siguiente petición aunque el navegador conserve su credencial.
    #[Test]
    public function rejects_token_revoked_in_database(): void
    {
        $user = UserFactory::new()->owner()->create();
        $credentials = resolve(UserService::class)->createApiToken($user);
        $user->update(['api_token_hash' => null, 'api_token_expires_at' => null]);

        $this->withToken($credentials['token'])->getJson('/api/auth/me')->assertUnauthorized();
    }


    // Un nuevo acceso reemplaza al anterior; el logout revoca la credencial vigente del usuario.
    #[Test]
    public function replaces_previous_token_and_revokes_current_token_on_logout(): void
    {
        $user = UserFactory::new()->owner()->create();
        $firstLogin = resolve(UserService::class)->createApiToken($user);
        $currentLogin = resolve(UserService::class)->createApiToken($user);

        $this->withToken($firstLogin['token'])->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($currentLogin['token'])->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertNull($user->fresh()->api_token_hash);
        $this->assertNull($user->fresh()->api_token_expires_at);
    }


    // Bloquear o dar de baja usuario o cliente corta inmediatamente el acceso de un token existente.
    #[Test]
    #[DataProvider('disabledAccounts')]
    public function rejects_access_when_account_is_disabled(array $userAttributes, array $clientAttributes): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->owner()->for($client)->create();
        $credentials = resolve(UserService::class)->createApiToken($user);
        $user->forceFill($userAttributes)->save();
        $client->forceFill($clientAttributes)->save();

        $response = $this->withToken($credentials['token'])->getJson('/api/auth/me');
        $userWasDeleted = isset($userAttributes['deleted_at']);
        if ($userWasDeleted) {
            $response->assertUnauthorized();
            return;
        }
        $response->assertForbidden()->assertJsonPath('code', 'account_disabled');
    }


    public static function disabledAccounts(): array
    {
        return [
            'disabled user' => [['is_enabled' => false], []],
            'disabled client' => [[], ['is_enabled' => false]],
            'deleted user' => [['deleted_at' => '2026-01-01 00:00:00'], []],
            'deleted client' => [[], ['deleted_at' => '2026-01-01 00:00:00']],
        ];
    }


    // El logout también requiere Bearer y nunca redirige al login desde la API.
    #[Test]
    public function requires_authentication_for_logout(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

}

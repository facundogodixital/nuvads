<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Database\Factories\UserFactory;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;


class SessionTest extends TestCase
{

    use RefreshDatabase;


    // Con una sesión válida, la página debe recibir el nombre, email e identificador de cuenta del usuario actual.
    #[Test]
    public function presents_authenticated_user_and_client_identifier(): void
    {
        $user = UserFactory::new()->owner()->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('page.user', [
                'name' => $user->name,
                'email' => $user->email,
                'login_identifier' => $user->client->login_identifier,
            ]);
    }


    // El logout debe cerrar el acceso, descartar los datos privados de sesión y renovar el token CSRF.
    #[Test]
    public function logs_out_and_invalidates_session_data_and_csrf_token(): void
    {
        $user = UserFactory::new()->owner()->create();

        $response = $this->actingAs($user)->withSession(['private_data' => 'value', '_token' => 'old-token'])
            ->post('/auth/logout');

        $response->assertRedirect('/')->assertSessionMissing('private_data');
        $this->assertGuest();
        $this->assertNotEmpty(session()->token());
        $this->assertNotSame('old-token', session()->token());
    }


    // Si el usuario o cliente está bloqueado o dado de baja, debe cerrar la sesión, limpiar sus datos y avisar.
    #[Test]
    #[DataProvider('disabledAccounts')]
    public function ends_existing_session_when_account_access_is_revoked(
        array $userAttributes,
        array $clientAttributes,
    ): void {
        $client = ClientFactory::new()->create($clientAttributes);
        $user = UserFactory::new()->owner()->for($client)->create($userAttributes);

        $this->actingAs($user)->withSession(['private_data' => 'value'])
            ->get('/')
            ->assertRedirect('/')
            ->assertSessionHas('auth_error', 'El acceso a esta cuenta está deshabilitado.')
            ->assertSessionMissing('private_data');

        $this->assertGuest();
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


    // Un usuario autenticado que intenta iniciar otro acceso con Google debe volver al inicio conservando su sesión.
    #[Test]
    public function prevents_authenticated_users_from_restarting_google_login(): void
    {
        $user = UserFactory::new()->owner()->create();

        $this->actingAs($user)->get('/auth/google/redirect')->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }


    // Pedir el logout por JSON sin sesión debe devolver 401 con el código unauthenticated.
    #[Test]
    public function requires_authentication_for_logout(): void
    {
        $this->postJson('/auth/logout')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

}

<?php

namespace Tests\Feature\Auth;

use Mockery;
use App\Models\User;
use RuntimeException;
use App\Models\Client;
use App\Services\UserService;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Http;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;


class GoogleRegistrationTest extends GoogleOAuthTestCase
{

    use RefreshDatabase;


    // El primer acceso con Google debe crear cliente y titular vinculados, e iniciar una sesión con ID renovado.
    #[Test]
    public function registers_client_and_owner_and_starts_session(): void
    {
        Http::fake();
        $this->queueGoogleProfile([
            'sub' => 'google-owner',
            'name' => 'New Owner',
            'email_verified' => true,
            'email' => 'pepito.perez@lala.co.uk',
        ]);

        $this->withSession(['state' => 'valid-state']);
        $previousSessionId = session()->getId();

        $response = $this->get('/auth/google/callback?state=valid-state&code=valid-code');

        $response->assertRedirect('/');
        $user = User::query()->sole();
        $client = Client::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertTrue($user->is_owner);
        $this->assertSame($client->id, $user->client_id);
        $this->assertSame('google-owner', $user->google_id);
        $this->assertSame('pepito.perez@lala.co.uk', $user->email);
        $this->assertSame('pepito-perez-lala', $client->login_identifier);
        $this->assertSame('AR', $client->country_code);
        $this->assertSame('America/Argentina/Buenos_Aires', $client->timezone);
        $response->assertSessionMissing('state');
        Http::assertNothingSent();
    }


    // Un acceso posterior debe reutilizar la cuenta sin duplicarla ni cambiar su email o identificador.
    #[Test]
    public function logs_in_existing_owner_without_changing_account_or_creating_duplicates(): void
    {
        Http::fake();
        $user = UserFactory::new()->owner()->create(['google_id' => 'existing-owner']);
        $identifier = $user->client->login_identifier;
        $this->queueGoogleProfile([
            'sub' => 'existing-owner',
            'name' => 'Changed Name',
            'email_verified' => true,
            'email' => 'changed@example.com',
        ]);

        $this->withSession(['state' => 'valid-state'])
            ->get('/auth/google/callback?state=valid-state&code=valid-code')
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame($identifier, $user->client->fresh()->login_identifier);
        Http::assertNothingSent();
    }


    // Las cuentas bloqueadas o dadas de baja y los usuarios no titulares deben recibir 403, sin sesión ni altas nuevas.
    #[Test]
    #[DataProvider('blockedAccounts')]
    public function rejects_owners_without_account_access(array $userAttributes, array $clientAttributes): void
    {
        $client = ClientFactory::new()->create($clientAttributes);
        $user = UserFactory::new()->owner()->for($client)->create($userAttributes);
        $this->queueGoogleProfile([
            'sub' => $user->google_id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => true,
        ]);

        $this->withSession(['state' => 'valid-state'])
            ->getJson('/auth/google/callback?state=valid-state&code=valid-code')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_disabled');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('clients', 1);
    }


    public static function blockedAccounts(): array
    {
        return [
            'disabled user' => [['is_enabled' => false], []],
            'disabled client' => [[], ['is_enabled' => false]],
            'deleted user' => [['deleted_at' => '2026-01-01 00:00:00'], []],
            'deleted client' => [[], ['deleted_at' => '2026-01-01 00:00:00']],
            'non-owner' => [['is_owner' => false], []],
        ];
    }


    // Simula un fallo al guardar al titular: debe deshacer el cliente ya creado para evitar un registro incompleto.
    #[Test]
    public function rolls_back_client_when_owner_creation_fails(): void
    {
        $this->queueGoogleProfile([
            'sub' => 'new-owner',
            'name' => 'New Owner',
            'email_verified' => true,
            'email' => 'owner@example.com',
        ]);
        $clientExistedBeforeFailure = false;
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('findOneByGoogleId')->once()->with('new-owner')->andReturnNull();
        $userService->shouldReceive('create')->once()->andReturnUsing(
            function (Client $client) use (&$clientExistedBeforeFailure): never {
                $clientExistedBeforeFailure = Client::query()->whereKey($client->id)->exists();
                throw new RuntimeException('Simulated persistence failure');
            },
        );
        $this->instance(UserService::class, $userService);

        $this->withSession(['state' => 'valid-state'])
            ->getJson('/auth/google/callback?state=valid-state&code=valid-code')
            ->assertInternalServerError()
            ->assertJsonPath('code', 'internal_error');

        // Fuera de la petición: el handler no debe convertir un fallo de esta aserción en el 500 esperado.
        $this->assertTrue($clientExistedBeforeFailure);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('clients', 0);
    }

}

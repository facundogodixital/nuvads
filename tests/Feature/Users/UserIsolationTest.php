<?php

namespace Tests\Feature\Users;

use Tests\TestCase;
use App\Services\UserService;
use Database\Factories\UserFactory;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class UserIsolationTest extends TestCase
{

    use RefreshDatabase;


    // Con usuarios de dos clientes, las búsquedas y listados deben devolver únicamente los del cliente solicitado.
    #[Test]
    public function finds_and_lists_only_users_of_requested_client(): void
    {
        $client = ClientFactory::new()->create();
        $ownUser = UserFactory::new()->for($client)->create();
        $otherUser = UserFactory::new()->create();
        $userService = resolve(UserService::class);

        $this->assertNull($userService->find($client, $otherUser->id));
        $this->assertSame($ownUser->id, $userService->find($client, $ownUser->id)->id);
        $this->assertSame([$ownUser->id], $userService->list($client)->modelKeys());
    }


    // Intentar modificar un usuario ajeno debe fallar como no encontrado y conservar sus datos originales.
    #[Test]
    public function refuses_to_update_user_of_another_client(): void
    {
        $client = ClientFactory::new()->create();
        $otherUser = UserFactory::new()->create(['name' => 'Original Name']);
        $userService = resolve(UserService::class);

        $this->expectException(ModelNotFoundException::class);
        try {
            $userService->update($client, $otherUser->id, ['name' => 'Changed Name']);
        } finally {
            $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'name' => 'Original Name']);
        }
    }


    // Cambiar datos de un usuario propio debe funcionar, pero un client_id recibido no debe trasladarlo a otra cuenta.
    #[Test]
    public function keeps_user_in_current_client_when_updating_attributes(): void
    {
        $user = UserFactory::new()->create();
        $otherClient = ClientFactory::new()->create();
        $userService = resolve(UserService::class);

        $updatedUser = $userService->update($user->client, $user->id, [
            'name' => 'Updated Name',
            'client_id' => $otherClient->id,
        ]);

        $this->assertSame('Updated Name', $updatedUser->name);
        $this->assertSame($user->client_id, $updatedUser->client_id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'client_id' => $user->client_id]);
    }


    // Al crear un usuario, debe prevalecer el cliente explícito del service sobre cualquier client_id de los atributos.
    #[Test]
    public function creates_user_under_explicit_client_regardless_of_attributes(): void
    {
        $client = ClientFactory::new()->create();
        $otherClient = ClientFactory::new()->create();
        $userService = resolve(UserService::class);

        $user = $userService->create($client, ['name' => 'New User', 'client_id' => $otherClient->id]);

        $this->assertSame($client->id, $user->client_id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'client_id' => $client->id]);
    }


    // Un usuario con baja lógica debe quedar fuera de las búsquedas y listados de su cliente.
    #[Test]
    public function excludes_soft_deleted_users_from_client_queries(): void
    {
        $user = UserFactory::new()->create(['deleted_at' => '2026-01-01 00:00:00']);
        $userService = resolve(UserService::class);

        $this->assertNull($userService->find($user->client, $user->id));
        $this->assertCount(0, $userService->list($user->client));
    }

}

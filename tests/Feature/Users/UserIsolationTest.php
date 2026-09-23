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


    // Las búsquedas, listados y cambios de un cliente no alcanzan a los usuarios de otro: el cambio ajeno falla
    // como no encontrado y conserva los datos originales.
    #[Test]
    public function isolates_find_list_and_update_by_client(): void
    {
        $client = ClientFactory::new()->create();
        $ownUser = UserFactory::new()->for($client)->create();
        $otherUser = UserFactory::new()->create(['name' => 'Original Name']);
        $userService = resolve(UserService::class);

        $this->assertNull($userService->find($client, $otherUser->id));
        $this->assertSame($ownUser->id, $userService->find($client, $ownUser->id)->id);
        $this->assertSame([$ownUser->id], $userService->list($client)->modelKeys());

        $this->expectException(ModelNotFoundException::class);
        try {
            $userService->update($client, $otherUser->id, ['name' => 'Changed Name']);
        } finally {
            $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'name' => 'Original Name']);
        }
    }


    // El cliente explícito del service manda: un client_id recibido en los atributos no crea ni traslada usuarios
    // a otra cuenta.
    #[Test]
    public function keeps_user_under_explicit_client_on_create_and_update(): void
    {
        $client = ClientFactory::new()->create();
        $otherClient = ClientFactory::new()->create();
        $userService = resolve(UserService::class);

        $user = $userService->create($client, ['name' => 'New User', 'client_id' => $otherClient->id]);
        $updatedUser = $userService->update($client, $user->id, [
            'name' => 'Updated Name',
            'client_id' => $otherClient->id,
        ]);

        $this->assertSame($client->id, $user->client_id);
        $this->assertSame('Updated Name', $updatedUser->name);
        $this->assertSame($client->id, $updatedUser->client_id);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'client_id' => $client->id]);
    }

}

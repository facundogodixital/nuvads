<?php

namespace Tests\Unit\Users;

use App\Models\User;
use App\Models\Client;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;


class UserAccountAccessTest extends TestCase
{


    // Solo permite el acceso con un cliente asociado y ambos habilitados, sin baja lógica.
    #[Test]
    #[DataProvider('accountStates')]
    public function checks_user_and_client_access(
        array $userAttributes,
        ?array $clientAttributes,
        bool $canAccess,
    ): void {
        // El formato explícito evita consultar la conexión de Eloquent para convertir las fechas.
        $user = (new User())->setDateFormat('Y-m-d H:i:s')->forceFill($userAttributes);
        $client = null;
        $hasClientAttributes = $clientAttributes !== null;
        if ($hasClientAttributes) {
            $client = (new Client())->setDateFormat('Y-m-d H:i:s')->forceFill($clientAttributes);
        }
        $user->setRelation('client', $client);

        $this->assertSame($canAccess, $user->isAccountAccessEnabled());
    }


    public static function accountStates(): array
    {
        $enabled = ['is_enabled' => true];
        $disabled = ['is_enabled' => false];
        $deleted = ['is_enabled' => true, 'deleted_at' => '2026-01-01 00:00:00'];

        return [
            'enabled account' => [$enabled, $enabled, true],
            'disabled user' => [$disabled, $enabled, false],
            'disabled client' => [$enabled, $disabled, false],
            'deleted user' => [$deleted, $enabled, false],
            'deleted client' => [$enabled, $deleted, false],
            'missing client' => [$enabled, null, false],
        ];
    }

}

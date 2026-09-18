<?php

namespace Tests\Feature;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;


#[Group('smoke')]
class SmokeTest extends TestCase
{


    // Comprueba que Laravel puede arrancar y responder correctamente a su ruta de salud.
    #[Test]
    public function application_boots_and_responds_to_health_check(): void
    {
        $this->get('/up')->assertOk();
    }


    // Una visita sin sesión debe recibir la página inicial sin datos de un usuario autenticado.
    #[Test]
    public function presents_login_page_to_guests(): void
    {
        $this->get('/')->assertOk()
            ->assertViewHas('page.user')
            ->assertViewHas('page.user', fn (mixed $user): bool => $user === null);
        $this->assertGuest();
    }

}

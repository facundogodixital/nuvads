<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Services\LoginCodeService;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;


class LoginCodeTest extends TestCase
{

    use RefreshDatabase;


    // El código solo funciona con el secreto del navegador original y solo puede canjearse una vez.
    #[Test]
    public function binds_code_to_browser_and_consumes_it_once(): void
    {
        $user = UserFactory::new()->owner()->create();
        $verifier = str_repeat('b', 64);
        $code = resolve(LoginCodeService::class)->create($user, hash('sha256', $verifier));

        $this->postJson('/api/auth/exchange', ['code' => $code, 'verifier' => str_repeat('a', 64)])
            ->assertUnauthorized()->assertJsonPath('code', 'login_code_invalid');
        $credentials = $this->postJson('/api/auth/exchange', ['code' => $code, 'verifier' => $verifier])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data');
        $this->assertSame(hash('sha256', $credentials['token']), $user->fresh()->api_token_hash);
        $this->postJson('/api/auth/exchange', ['code' => $code, 'verifier' => $verifier])->assertUnauthorized();
        $this->withToken($credentials['token'])->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('data.user.id', $user->id);
    }


    // Un código que supera su minuto de vigencia no debe generar un token.
    #[Test]
    public function rejects_expired_code(): void
    {
        $user = UserFactory::new()->owner()->create();
        $verifier = str_repeat('b', 64);
        $code = resolve(LoginCodeService::class)->create($user, hash('sha256', $verifier));
        $this->travel(61)->seconds();

        $this->postJson('/api/auth/exchange', ['code' => $code, 'verifier' => $verifier])->assertUnauthorized();
        $this->assertNull($user->fresh()->api_token_hash);
    }


    // Si se deshabilita la cuenta entre Google y el canje, no se debe emitir una credencial.
    #[Test]
    public function checks_account_again_before_issuing_access_token(): void
    {
        $user = UserFactory::new()->owner()->create();
        $verifier = str_repeat('b', 64);
        $code = resolve(LoginCodeService::class)->create($user, hash('sha256', $verifier));
        $user->update(['is_enabled' => false]);

        $this->postJson('/api/auth/exchange', ['code' => $code, 'verifier' => $verifier])
            ->assertForbidden()->assertJsonPath('code', 'account_disabled');
        $this->assertNull($user->fresh()->api_token_hash);
    }

}

<?php

namespace Tests\Feature\Auth;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\Attributes\DataProvider;


class GoogleOAuthTest extends GoogleOAuthTestCase
{


    // Al iniciar el acceso, debe redirigir a Google con la configuración esperada y guardar el mismo state en sesión.
    #[Test]
    public function redirects_to_google_and_stores_state(): void
    {
        $response = $this->get('/auth/google/redirect?challenge='.str_repeat('a', 64));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $parameters);

        $this->assertSame('accounts.google.com', parse_url($location, PHP_URL_HOST));
        $this->assertSame('testing-client', $parameters['client_id']);
        $this->assertSame(config('services.google.redirect'), $parameters['redirect_uri']);
        $this->assertNotEmpty($parameters['state']);
        $response->assertSessionHas('state', $parameters['state']);
    }


    // Al cancelar con state válido, debe consumirlo y mostrar el aviso en el login, sin iniciar sesión.
    #[Test]
    public function handles_cancellation_without_contacting_google(): void
    {
        $response = $this->withSession(['state' => 'valid-state'])
            ->get('/auth/google/callback?state=valid-state&error=access_denied')
            ->assertRedirect('/login?error=google_access_denied')
            ->assertSessionMissing('state');

        $this->followRedirects($response)->assertOk()->assertViewIs('app');
        $this->assertGuest();
    }


    // Un state distinto al guardado en sesión corta el acceso con 419 antes de consultar a Google,
    // tanto al volver con código como al cancelar.
    #[Test]
    #[DataProvider('invalidStates')]
    public function rejects_invalid_state_before_contacting_google(string $query): void
    {
        $this->withSession(['state' => 'expected'])->getJson('/auth/google/callback?'.$query)
            ->assertStatus(419)
            ->assertJsonPath('code', 'google_session_expired')
            ->assertSessionMissing('state');

        $this->assertGuest();
    }


    public static function invalidStates(): array
    {
        return [
            'mismatched code state' => ['state=wrong&code=code'],
            'mismatched cancellation state' => ['state=wrong&error=access_denied'],
        ];
    }


    // Una identidad sin sub o con email no verificado debe rechazarse con 422, sin iniciar sesión.
    #[Test]
    #[DataProvider('invalidProfiles')]
    public function rejects_invalid_google_profiles(array $profile): void
    {
        $this->queueGoogleProfile($profile);

        $this->withSession(['state' => 'valid-state'])
            ->getJson('/auth/google/callback?state=valid-state&code=valid-code')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'google_profile_invalid');

        $this->assertGuest();
    }


    public static function invalidProfiles(): array
    {
        $profile = ['sub' => 'google-id', 'name' => 'Owner', 'email' => 'owner@example.com', 'email_verified' => true];

        return [
            'unverified email' => [array_replace($profile, ['email_verified' => false])],
            'missing identity' => [array_replace($profile, ['sub' => null])],
        ];
    }


    // Si Google falla o no responde, debe devolver 502 con google_unavailable sin exponer la respuesta del proveedor.
    #[Test]
    #[DataProvider('googleFailures')]
    public function reports_google_failures_as_unavailable(Response|ConnectException $failure): void
    {
        $this->googleResponses->append($failure);

        $this->withSession(['state' => 'valid-state'])
            ->getJson('/auth/google/callback?state=valid-state&code=valid-code')
            ->assertStatus(502)
            ->assertJsonPath('code', 'google_unavailable')
            ->assertDontSee('secret-provider-content');

        $this->assertGuest();
    }


    public static function googleFailures(): array
    {
        $tokenRequest = new Request('POST', 'https://www.googleapis.com/oauth2/v4/token');

        return [
            'server failure' => [new Response(503, [], 'secret-provider-content')],
            'connection failure' => [new ConnectException('Connection failed', $tokenRequest)],
        ];
    }

}

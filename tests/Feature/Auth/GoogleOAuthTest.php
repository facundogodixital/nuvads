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


    // Un state ausente en sesión o distinto al recibido debe cortar el acceso con 419 antes de consultar a Google.
    #[Test]
    #[DataProvider('invalidStates')]
    public function rejects_invalid_state_before_contacting_google(string $query, array $session): void
    {
        $this->withSession($session)->getJson('/auth/google/callback?'.$query)
            ->assertStatus(419)
            ->assertJsonPath('code', 'google_session_expired')
            ->assertSessionMissing('state');

        $this->assertGuest();
    }


    public static function invalidStates(): array
    {
        return [
            'mismatched code state' => ['state=wrong&code=code', ['state' => 'expected']],
            'missing session state' => ['state=wrong&code=code', []],
            'mismatched cancellation state' => ['state=wrong&error=access_denied', ['state' => 'expected']],
        ];
    }


    // Un callback incompleto o con tipos inválidos debe devolver 422 e identificar el campo incorrecto.
    #[Test]
    #[DataProvider('invalidCallbacks')]
    public function validates_callback_input(array $parameters, string $invalidField): void
    {
        $this->getJson('/auth/google/callback?'.http_build_query($parameters))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors($invalidField);

        $this->assertGuest();
    }


    public static function invalidCallbacks(): array
    {
        return [
            'missing state' => [['code' => 'code'], 'state'],
            'missing code' => [['state' => 'state'], 'code'],
            'array state' => [['state' => ['state'], 'code' => 'code'], 'state'],
        ];
    }


    // Una identidad incompleta o un email inválido o no verificado deben rechazarse con 422, sin iniciar sesión.
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
            'invalid email' => [array_replace($profile, ['email' => 'invalid'])],
            'missing identity' => [array_replace($profile, ['sub' => null])],
            'missing email' => [array_replace($profile, ['email' => null])],
        ];
    }


    // Si Google responde con un error de servidor, debe devolver 502 sin exponer el contenido de esa respuesta.
    #[Test]
    public function handles_google_server_failure_without_exposing_provider_response(): void
    {
        $this->googleResponses->append(new Response(503, [], 'secret-provider-content'));

        $this->withSession(['state' => 'valid-state'])
            ->getJson('/auth/google/callback?state=valid-state&code=valid-code')
            ->assertStatus(502)
            ->assertJsonPath('code', 'google_unavailable')
            ->assertDontSee('secret-provider-content');

        $this->assertGuest();
    }


    // Si no se puede conectar con Google, debe comunicar google_unavailable con estado 502.
    #[Test]
    public function handles_google_connection_failure(): void
    {
        $request = new Request('POST', 'https://www.googleapis.com/oauth2/v4/token');
        $this->googleResponses->append(new ConnectException('Connection failed', $request));

        $this->withSession(['state' => 'valid-state'])
            ->getJson('/auth/google/callback?state=valid-state&code=valid-code')
            ->assertStatus(502)
            ->assertJsonPath('code', 'google_unavailable');
    }

}

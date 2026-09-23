<?php

namespace Tests\Feature\Clients;

use Tests\TestCase;
use App\Services\ClientService;
use Illuminate\Support\Facades\Http;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;


class ClientSignupTest extends TestCase
{

    use RefreshDatabase;


    // Debe saltar identificadores ocupados, también los de clientes dados de baja, agregando el siguiente sufijo.
    #[Test]
    public function derives_identifier_from_email_and_skips_reserved_identifiers(): void
    {
        ClientFactory::new()->create(['login_identifier' => 'pepito-perez-lala']);
        ClientFactory::new()->create([
            'login_identifier' => 'pepito-perez-lala-2',
            'deleted_at' => '2026-01-01 00:00:00',
        ]);
        $clientService = resolve(ClientService::class);

        $identifier = $clientService->getAvailableLoginIdentifier('pepito.perez@lala.co.uk');

        $this->assertSame('pepito-perez-lala-3', $identifier);
    }


    // El alta usa la ubicación válida o los valores por defecto; Argentina conserva su zona acordada y una zona
    // horaria de otro país se descarta.
    #[Test]
    #[DataProvider('signupLocations')]
    public function chooses_signup_country_and_timezone(array $location, string $countryCode, string $timezone): void
    {
        Http::fake(['https://ipapi.co/8.8.8.8/json/' => Http::response($location)]);
        $clientService = resolve(ClientService::class);

        $attributes = $clientService->getSignupClientAttributes('owner@example.com', '8.8.8.8');

        $this->assertSame([
            'timezone' => $timezone,
            'country_code' => $countryCode,
            'name' => 'owner',
        ], $attributes);
    }


    public static function signupLocations(): array
    {
        return [
            'Argentina keeps agreed timezone' => [
                ['country_code' => 'AR', 'timezone' => 'America/Argentina/Cordoba'],
                'AR',
                'America/Argentina/Buenos_Aires',
            ],
            'foreign timezone' => [
                ['country_code' => 'US', 'timezone' => 'America/Los_Angeles'], 'US', 'America/Los_Angeles',
            ],
            'country without timezone' => [['country_code' => 'ES'], 'ES', 'Europe/Madrid'],
            'timezone of another country' => [
                ['country_code' => 'ES', 'timezone' => 'America/New_York'], 'ES', 'Europe/Madrid',
            ],
            'unknown location' => [[], 'AR', 'America/Argentina/Buenos_Aires'],
        ];
    }


    // Si falla la geolocalización, debe usar Argentina y su zona horaria por defecto.
    #[Test]
    public function uses_default_location_when_provider_cannot_be_reached(): void
    {
        Http::fake(['https://ipapi.co/8.8.8.8/json/' => Http::failedConnection()]);
        $clientService = resolve(ClientService::class);

        $attributes = $clientService->getSignupClientAttributes('owner@example.com', '8.8.8.8');

        $this->assertSame('AR', $attributes['country_code']);
        $this->assertSame('America/Argentina/Buenos_Aires', $attributes['timezone']);
    }

}

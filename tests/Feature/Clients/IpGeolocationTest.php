<?php

namespace Tests\Feature\Clients;

use Tests\TestCase;
use App\Helpers\IpGeolocationHelper;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;


class IpGeolocationTest extends TestCase
{


    // Las IP ausentes, inválidas o privadas deben devolver ubicación desconocida sin hacer peticiones externas.
    #[Test]
    #[DataProvider('unusableAddresses')]
    public function skips_lookup_for_missing_invalid_or_private_addresses(?string $ipAddress): void
    {
        Http::fake();

        $location = resolve(IpGeolocationHelper::class)->getLocationInfo($ipAddress);

        $this->assertSame(['country_code' => null, 'timezone' => null], $location);
        Http::assertNothingSent();
    }


    public static function unusableAddresses(): array
    {
        return [[null], ['invalid'], ['127.0.0.1'], ['192.168.1.5'], ['::1']];
    }


    // Conserva países conocidos y zonas del mismo país; descarta respuestas fallidas y datos inválidos.
    #[Test]
    #[DataProvider('providerResponses')]
    public function validates_location_responses(array|string $body, int $status, array $expectedLocation): void
    {
        Http::fake(['https://ipapi.co/8.8.8.8/json/' => Http::response($body, $status)]);

        $location = resolve(IpGeolocationHelper::class)->getLocationInfo('8.8.8.8');

        $this->assertSame($expectedLocation, $location);
        Http::assertSentCount(1);
    }


    public static function providerResponses(): array
    {
        $unavailable = ['country_code' => null, 'timezone' => null];
        $spainWithoutTimezone = ['country_code' => 'ES', 'timezone' => null];

        return [
            'valid location' => [
                ['country_code' => 'ES', 'timezone' => 'Europe/Madrid'], 200,
                ['country_code' => 'ES', 'timezone' => 'Europe/Madrid'],
            ],
            'server failure' => [[], 503, $unavailable],
            'provider error' => [['error' => true], 200, $unavailable],
            'invalid JSON' => ['not-json', 200, $unavailable],
            'unknown country' => [['country_code' => 'ZZ'], 200, $unavailable],
            'invalid country type' => [['country_code' => []], 200, $unavailable],
            'missing timezone' => [['country_code' => 'ES'], 200, $spainWithoutTimezone],
            'invalid timezone' => [
                ['country_code' => 'ES', 'timezone' => 'Invalid/Timezone'], 200, $spainWithoutTimezone,
            ],
            'timezone of another country' => [
                ['country_code' => 'ES', 'timezone' => 'America/New_York'], 200, $spainWithoutTimezone,
            ],
        ];
    }

}

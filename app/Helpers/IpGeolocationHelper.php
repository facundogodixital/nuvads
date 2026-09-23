<?php

namespace App\Helpers;

use DateTimeZone;
use RuntimeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;


class IpGeolocationHelper
{


    /**
     * Devuelve el país y su zona horaria por IP; cada dato no disponible queda en null.
     *
     * @return array{country_code: ?string, timezone: ?string}
     */
    public function getLocationInfo(?string $ipAddress): array
    {
        $location = ['country_code' => null, 'timezone' => null];
        $isPublicIp = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
        if (!$isPublicIp) {
            return $location;
        }

        try {
            $response = Http::acceptJson()->connectTimeout(1)->timeout(2)->get("https://ipapi.co/{$ipAddress}/json/");
        } catch (ConnectionException $exception) {
            // Sin respuesta se usan los valores por defecto; el fallo queda registrado en el log.
            report($exception);
            return $location;
        }

        if (!$response->successful()) {
            report(new RuntimeException("ipapi respondió {$response->status()}: {$response->body()}"));
            return $location;
        }

        $attributes = $response->json();
        $hasLocation = is_array($attributes) && empty($attributes['error']);
        if (!$hasLocation) {
            report(new RuntimeException("ipapi no devolvió una ubicación: {$response->body()}"));
            return $location;
        }

        $timezone = $attributes['timezone'] ?? null;
        $countryCode = $attributes['country_code'] ?? null;
        $hasKnownCountry = is_string($countryCode) && array_key_exists($countryCode, config('country_timezones'));
        if (!$hasKnownCountry) {
            return $location;
        }

        $location['country_code'] = $countryCode;
        $hasValidTimezone = is_string($timezone) && in_array(
            $timezone,
            DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),
            true,
        );
        if (!$hasValidTimezone) {
            return $location;
        }

        // Una zona válida de otro país no sirve como ubicación del cliente.
        $timezoneLocation = (new DateTimeZone($timezone))->getLocation();
        $timezoneMatchesCountry = $timezoneLocation !== false && $timezoneLocation['country_code'] === $countryCode;
        if ($timezoneMatchesCountry) {
            $location['timezone'] = $timezone;
        }

        return $location;
    }

}

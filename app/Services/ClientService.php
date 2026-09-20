<?php

namespace App\Services;

use Throwable;
use App\Models\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Helpers\IpGeolocationHelper;
use App\Repositories\ClientRepository;
use Illuminate\Database\Eloquent\Collection;


class ClientService
{

    private ClientRepository $clientRepository;


    public function __construct(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }


    public function create(array $attributes): Client
    {
        // Todo cliente nace con una marca; ambas escrituras deben confirmarse juntas.
        DB::beginTransaction();
        try {
            $client = $this->clientRepository->create($attributes);
            resolve(BrandService::class)->create($client, 'Tu marca');
            DB::commit();

            return $client;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    public function update(int $clientId, array $attributes): Client
    {
        return $this->clientRepository->update($clientId, $attributes);
    }


    public function find(int $clientId): ?Client
    {
        return $this->clientRepository->find($clientId);
    }


    public function list(): Collection
    {
        return $this->clientRepository->list();
    }


    public function findOneByLoginIdentifier(string $loginIdentifier): ?Client
    {
        return $this->clientRepository->findOneByLoginIdentifier($loginIdentifier);
    }


    /**
     * Devuelve el nombre, país y zona horaria para crear el cliente, con los valores predeterminados aplicados.
     *
     * @return array{name: string, country_code: string, timezone: string}
     */
    public function getSignupClientAttributes(string $email, ?string $ipAddress): array
    {
        $locationInfo = resolve(IpGeolocationHelper::class)->getLocationInfo($ipAddress);
        $detectedTimezone = $locationInfo['timezone'];
        $countryCode = $locationInfo['country_code'] ?? 'AR';
        $defaultTimezone = config("country_timezones.{$countryCode}");

        // Argentina conserva la zona acordada, aunque el proveedor devuelva otra del país.
        $isArgentina = $countryCode === 'AR';
        $timezone = $isArgentina ? $defaultTimezone : ($detectedTimezone ?? $defaultTimezone);

        return [
            'timezone' => $timezone,
            'country_code' => $countryCode,
            'name' => Str::before($email, '@'),
        ];
    }


    public function getAvailableLoginIdentifier(string $email): string
    {
        [$localPart, $domain] = explode('@', $email, 2);
        $domainName = explode('.', $domain, 2)[0];
        $baseIdentifier = Str::slug(str_replace('.', '-', "{$localPart}-{$domainName}"));

        $suffix = 2;
        $loginIdentifier = $baseIdentifier;

        while ($this->findOneByLoginIdentifier($loginIdentifier) !== null) {
            $loginIdentifier = "{$baseIdentifier}-{$suffix}";
            $suffix++;
        }

        return $loginIdentifier;
    }

}

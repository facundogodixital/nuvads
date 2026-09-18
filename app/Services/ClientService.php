<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Str;
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
        return $this->clientRepository->create($attributes);
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

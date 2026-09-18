<?php

namespace App\Services;

use App\Models\Client;
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

}

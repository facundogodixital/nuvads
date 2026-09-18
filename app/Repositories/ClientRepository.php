<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;


class ClientRepository
{


    public function create(array $attributes): Client
    {
        return Client::query()->create($attributes);
    }


    public function update(int $clientId, array $attributes): Client
    {
        $client = Client::query()->findOrFail($clientId);
        $client->fill($attributes);
        $client->save();

        return $client;
    }


    public function find(int $clientId): ?Client
    {
        return Client::query()->find($clientId);
    }


    public function list(): Collection
    {
        return Client::query()->orderBy('id')->get();
    }

}

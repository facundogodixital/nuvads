<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;


class UserRepository
{


    public function create(Client $client, array $attributes): User
    {
        $attributes['client_id'] = $client->id;

        return User::query()->create($attributes);
    }


    public function update(Client $client, int $userId, array $attributes): User
    {
        $attributes['client_id'] = $client->id;
        $user = User::query()->where('client_id', $client->id)->findOrFail($userId);

        $user->fill($attributes);
        $user->save();

        return $user;
    }


    public function find(Client $client, int $userId): ?User
    {
        return User::query()->where('client_id', $client->id)->find($userId);
    }


    public function list(Client $client): Collection
    {
        return User::query()->where('client_id', $client->id)->orderBy('id')->get();
    }

}

<?php

namespace App\Services;

use App\Models\User;
use App\Models\Client;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;


class UserService
{

    private UserRepository $userRepository;


    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }


    public function create(Client $client, array $attributes): User
    {
        return $this->userRepository->create($client, $attributes);
    }


    public function update(Client $client, int $userId, array $attributes): User
    {
        return $this->userRepository->update($client, $userId, $attributes);
    }


    public function find(Client $client, int $userId): ?User
    {
        return $this->userRepository->find($client, $userId);
    }


    public function list(Client $client): Collection
    {
        return $this->userRepository->list($client);
    }

}

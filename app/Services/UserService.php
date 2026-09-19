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


    public function findOneByGoogleId(string $googleId): ?User
    {
        return $this->userRepository->findOneByGoogleId($googleId);
    }


    public function createApiToken(User $user): array
    {
        $expiresAt = now()->addHours(24);
        $token = bin2hex(random_bytes(32));

        $this->userRepository->update($user->client, $user->id, [
            'api_token_expires_at' => $expiresAt,
            'api_token_hash' => hash('sha256', $token),
        ]);

        // La credencial original se entrega una sola vez; la base conserva únicamente su hash.
        return ['token' => $token, 'expires_at' => $expiresAt->toISOString()];
    }


    public function findOneByApiToken(string $token): ?User
    {
        return $this->userRepository->findOneByApiTokenHash(hash('sha256', $token));
    }


    public function revokeApiToken(User $user): int
    {
        return $this->userRepository->revokeApiToken($user->id, $user->api_token_hash);
    }

}

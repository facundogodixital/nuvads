<?php

namespace App\Http\Controllers\API;

use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use App\Services\LoginCodeService;
use App\Http\Requests\AuthenticatedRequest;
use App\Http\Requests\Auth\ExchangeLoginCodeRequest;


class SessionController extends ApiController
{


    public function create(ExchangeLoginCodeRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $credentials = resolve(LoginCodeService::class)->exchange($attributes['code'], $attributes['verifier']);
        return $this->respond($credentials);
    }


    public function find(AuthenticatedRequest $request): JsonResponse
    {
        return $this->respond([
            'user' => $request->user,
            'brand' => $request->brand,
            'client' => $request->client,
        ]);
    }


    public function delete(AuthenticatedRequest $request): JsonResponse
    {
        resolve(UserService::class)->revokeApiToken($request->user);
        return $this->respond();
    }

}

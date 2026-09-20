<?php

namespace App\Http\Controllers\API;

use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use App\Services\LoginCodeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthenticatedRequest;
use App\Http\Requests\Auth\ExchangeLoginCodeRequest;


class SessionController extends Controller
{


    public function create(ExchangeLoginCodeRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $credentials = resolve(LoginCodeService::class)->exchange($attributes['code'], $attributes['verifier']);

        return response()->json(['data' => $credentials])->header('Cache-Control', 'no-store');
    }


    public function find(AuthenticatedRequest $request): JsonResponse
    {
        $user = $request->user;
        $brand = $request->brand;
        $client = $request->client;

        return response()->json(['data' => [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'client' => ['id' => $client->id, 'name' => $client->name],
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
        ]])->header('Cache-Control', 'no-store');
    }


    public function delete(AuthenticatedRequest $request): JsonResponse
    {
        resolve(UserService::class)->revokeApiToken($request->user);

        return response()->json(['data' => []]);
    }

}

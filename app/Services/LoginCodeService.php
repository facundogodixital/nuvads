<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Cache;


class LoginCodeService
{


    public function create(User $user, string $challenge): string
    {
        $code = bin2hex(random_bytes(32));
        $cacheKey = 'login-code:'.hash('sha256', $code);

        Cache::put($cacheKey, [
            'user_id' => $user->id,
            'challenge' => $challenge,
            'client_id' => $user->client_id,
        ], 60);

        return $code;
    }


    public function exchange(string $code, string $verifier): array
    {
        $cacheKey = 'login-code:'.hash('sha256', $code);
        $lock = Cache::lock($cacheKey.':lock', 10);
        if (!$lock->get()) {
            throw new ApiException(401, 'login_code_invalid', 'El intento de acceso ya está siendo procesado.');
        }

        try {
            $loginCode = Cache::get($cacheKey);
            if ($loginCode === null) {
                throw new ApiException(401, 'login_code_invalid', 'El acceso expiró. Vuelve a iniciar sesión.');
            }

            $hasMatchingVerifier = hash_equals($loginCode['challenge'], hash('sha256', $verifier));
            if (!$hasMatchingVerifier) {
                throw new ApiException(401, 'login_code_invalid', 'No se pudo verificar el intento de acceso.');
            }

            // Se consume antes de emitir el token; ni un reintento ni un canje concurrente pueden reutilizarlo.
            Cache::forget($cacheKey);
        } finally {
            $lock->release();
        }

        $client = resolve(ClientService::class)->find($loginCode['client_id']);
        $userService = resolve(UserService::class);
        $user = $client === null ? null : $userService->find($client, $loginCode['user_id']);
        $accountIsEnabled = $user !== null && $user->isAccountAccessEnabled();
        if (!$accountIsEnabled) {
            throw new ApiException(403, 'account_disabled', 'El acceso a esta cuenta está deshabilitado.');
        }

        return $userService->createApiToken($user);
    }

}

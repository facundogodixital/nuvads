<?php

namespace App\Services;

use Throwable;
use App\Models\User;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Helpers\GoogleOAuthHelper;
use Illuminate\Support\Facades\DB;


class GoogleAuthService
{


    public function getRedirectUrl(Request $request): string
    {
        return resolve(GoogleOAuthHelper::class)->getRedirectUrl($request);
    }


    public function findOrCreateUser(Request $request): User
    {
        $userService = resolve(UserService::class);
        $googleUser = resolve(GoogleOAuthHelper::class)->getAuthenticatedUser($request);

        $user = $userService->findOneByGoogleId($googleUser->googleId);
        if ($user !== null) {
            $isOwner = $user->is_owner;
            $hasAccountAccess = $user->isAccountAccessEnabled();
            if (!$isOwner || !$hasAccountAccess) {
                throw new ApiException(403, 'account_disabled', 'El acceso a esta cuenta está deshabilitado.');
            }
            return $user;
        }

        $clientService = resolve(ClientService::class);

        // Nombre, país y zona horaria iniciales del cliente.
        $signupAttributes = $clientService->getSignupClientAttributes($googleUser->email, $request->ip());
        $signupAttributes['login_identifier'] = $clientService->getAvailableLoginIdentifier($googleUser->email);

        // Cliente y titular se confirman juntos; un fallo no debe dejar una cuenta incompleta.
        DB::beginTransaction();
        try {
            $client = $clientService->create($signupAttributes);
            $user = $userService->create($client, [
                'is_owner' => true,
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'google_id' => $googleUser->googleId,
            ]);
            DB::commit();

            return $user;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

}

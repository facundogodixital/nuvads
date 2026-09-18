<?php

namespace App\Services;

use Throwable;
use App\Models\User;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Helpers\GoogleOAuthHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;


class GoogleAuthService
{


    public function getRedirectUrl(Request $request): string
    {
        return resolve(GoogleOAuthHelper::class)->getRedirectUrl($request);
    }


    public function findOrCreateUser(Request $request): User
    {
        $userService = resolve(UserService::class);
        $clientService = resolve(ClientService::class);
        $googleUser = resolve(GoogleOAuthHelper::class)->getAuthenticatedUser($request);

        while (true) {
            $user = $userService->findOneByGoogleId($googleUser->googleId);
            if ($user !== null) {
                if (!$user->is_owner || !$user->isAccountAccessEnabled()) {
                    throw new ApiException(403, 'account_disabled', 'El acceso a esta cuenta está deshabilitado.');
                }
                return $user;
            }

            $loginIdentifier = $clientService->getAvailableLoginIdentifier($googleUser->email);

            // Cliente y titular se confirman juntos; un fallo no debe dejar una cuenta incompleta.
            DB::beginTransaction();
            try {
                $client = $clientService->create(['login_identifier' => $loginIdentifier]);
                $user = $userService->create($client, [
                    'is_owner' => true,
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->googleId,
                ]);
                DB::commit();

                return $user;
            } catch (UniqueConstraintViolationException $exception) {
                DB::rollBack();

                // Otro registro puede haber reservado el identificador o creado al titular en paralelo.
                $googleUserExists = $userService->findOneByGoogleId($googleUser->googleId) !== null;
                $identifierIsTaken = $clientService->findOneByLoginIdentifier($loginIdentifier) !== null;
                if (!$googleUserExists && !$identifierIsTaken) {
                    throw $exception;
                }
            } catch (Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }
        }
    }

}

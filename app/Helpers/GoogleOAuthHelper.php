<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\DTO\GoogleAuthenticatedUserDTO;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Laravel\Socialite\Two\InvalidStateException;


class GoogleOAuthHelper
{


    public function getRedirectUrl(Request $request): string
    {
        return $this->getProvider($request)->redirect()->getTargetUrl();
    }


    public function getAuthenticatedUser(Request $request): GoogleAuthenticatedUserDTO
    {
        $googleUser = $this->getGoogleUser($request);
        return $this->getValidatedUser($googleUser);
    }


    private function getGoogleUser(Request $request): GoogleUser
    {
        // La cancelación no pasa por user(), por eso validamos y consumimos su state aquí.
        if ($request->filled('error')) {
            $state = $request->session()->pull('state');
            $hasMatchingState = is_string($state) && hash_equals($state, $request->string('state')->toString());
            if (!$hasMatchingState) {
                throw new ApiException(
                    419,
                    'google_session_expired',
                    'El intento de acceso expiró. Volvé a intentarlo.',
                );
            }
            throw new ApiException(400, 'google_access_denied', 'No se completó el acceso con Google.');
        }

        // Socialite valida y consume state antes de intercambiar el código por la identidad de Google.
        try {
            return $this->getProvider($request)->user();
        } catch (InvalidStateException $exception) {
            // El usuario ve un mensaje propio; la excepción original queda como previous y llega al log.
            throw new ApiException(
                419, 'google_session_expired', 'El intento de acceso expiró. Volvé a intentarlo.', $exception,
            );
        } catch (GuzzleException $exception) {
            throw new ApiException(
                502, 'google_unavailable', 'No pudimos completar el acceso con Google.', $exception,
            );
        }
    }


    private function getValidatedUser(GoogleUser $googleUser): GoogleAuthenticatedUserDTO
    {
        $validator = Validator::make([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'email_verified' => $googleUser->user['email_verified'] ?? false,
        ], [
            'name' => ['nullable', 'string', 'max:255'],
            'google_id' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'email_verified' => ['required', 'accepted'],
        ]);

        if ($validator->fails()) {
            throw new ApiException(422, 'google_profile_invalid', 'Google no devolvió un perfil con email verificado.');
        }

        $attributes = $validator->validated();
        return new GoogleAuthenticatedUserDTO(
            googleId: $attributes['google_id'],
            email: $attributes['email'],
            name: $attributes['name'] ?? $attributes['email'],
        );
    }


    private function getProvider(Request $request): GoogleProvider
    {
        return Socialite::buildProvider(GoogleProvider::class, config('services.google'))->setRequest($request);
    }

}

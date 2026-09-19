<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ApiException;
use App\Services\LoginCodeService;
use App\Services\GoogleAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\GoogleCallbackRequest;
use App\Http\Requests\Auth\GoogleRedirectRequest;


class GoogleAuthController extends Controller
{


    public function redirect(GoogleRedirectRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $request->session()->put('login_challenge', $attributes['challenge']);
        $redirectUrl = resolve(GoogleAuthService::class)->getRedirectUrl($request);

        return redirect()->away($redirectUrl);
    }


    public function callback(GoogleCallbackRequest $request): RedirectResponse
    {
        $user = resolve(GoogleAuthService::class)->findOrCreateUser($request);

        $challenge = $request->session()->pull('login_challenge');
        if (!is_string($challenge)) {
            throw new ApiException(419, 'google_session_expired', 'El intento de acceso expiró. Vuelve a intentarlo.');
        }

        $code = resolve(LoginCodeService::class)->create($user, $challenge);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // El fragmento no se envía al servidor al cargar Vue; nunca transporta el token de acceso.
        return redirect('/login/callback#'.http_build_query(['code' => $code]))
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

}

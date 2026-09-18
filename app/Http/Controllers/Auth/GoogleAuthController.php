<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Services\GoogleAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\GoogleCallbackRequest;


class GoogleAuthController extends Controller
{


    public function redirect(Request $request): RedirectResponse
    {
        $redirectUrl = resolve(GoogleAuthService::class)->getRedirectUrl($request);

        return redirect()->away($redirectUrl);
    }


    public function callback(GoogleCallbackRequest $request): RedirectResponse
    {
        $user = resolve(GoogleAuthService::class)->findOrCreateUser($request);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

}

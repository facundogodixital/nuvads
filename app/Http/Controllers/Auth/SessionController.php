<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;


class SessionController extends Controller
{


    public function find(Request $request): View
    {
        $userData = null;
        $user = $request->user('web');
        if ($user !== null) {
            $client = $user->client;
            $userData = [
                'name' => $user->name,
                'email' => $user->email,
                'login_identifier' => $client->login_identifier,
            ];
        }

        return view('app', [
            'page' => [
                'user' => $userData,
                'csrfToken' => csrf_token(),
                'error' => $request->session()->get('auth_error', ''),
            ],
        ]);
    }


    public function delete(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

}

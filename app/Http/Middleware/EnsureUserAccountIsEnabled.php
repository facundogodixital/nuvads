<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class EnsureUserAccountIsEnabled
{


    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user === null) {
            return $next($request);
        }

        $accountAccessIsEnabled = $user->isAccountAccessEnabled();
        if (!$accountAccessIsEnabled) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new ApiException(403, 'account_disabled', 'El acceso a esta cuenta está deshabilitado.');
        }

        return $next($request);
    }

}

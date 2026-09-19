<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;


class AuthenticateAccessToken
{


    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $hasValidTokenFormat = is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
        if (!$hasValidTokenFormat) {
            throw new AuthenticationException();
        }

        $user = resolve(UserService::class)->findOneByApiToken($token);
        if ($user === null) {
            throw new AuthenticationException();
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('authenticated_user', $user);

        return $next($request);
    }

}

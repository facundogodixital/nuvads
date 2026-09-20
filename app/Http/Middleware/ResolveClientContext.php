<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;


class ResolveClientContext
{


    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accountIsEnabled = $user->isAccountAccessEnabled();
        if (!$accountIsEnabled) {
            throw new ApiException(403, 'account_disabled', 'El acceso a esta cuenta está deshabilitado.');
        }

        // Por ahora cada cliente opera con una única marca activa.
        $brand = $user->client->brands->sole();

        $request->attributes->set('authenticated_brand', $brand);
        $request->attributes->set('authenticated_client', $user->client);

        return $next($request);
    }

}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;


class ResolveClientContextMiddleware
{


    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accountIsEnabled = $user->isAccountAccessEnabled();
        if (!$accountIsEnabled) {
            throw new ApiException(403, 'account_disabled', 'El acceso a esta cuenta está deshabilitado.');
        }

        // Cada pestaña del frontend manda en X-Brand-Id la marca que está mostrando. Sin header, o con una marca que
        // no es del cliente, se usa la primera marca del cliente.
        $clientBrands = $user->client->brands->sortBy('id');
        $requestedBrandId = (int) $request->header('X-Brand-Id');
        $brand = $clientBrands->firstWhere('id', $requestedBrandId) ?? $clientBrands->firstOrFail();

        $request->attributes->set('brand', $brand);
        $request->attributes->set('client', $user->client);

        return $next($request);
    }

}

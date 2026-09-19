<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;


class HandleGoogleAuthErrors
{


    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Laravel adjunta la excepción a la respuesta después de reportarla en el pipeline.
        $exception = $response->exception ?? null;
        $shouldRedirect = $exception !== null && !$request->expectsJson();
        if (!$shouldRedirect) {
            return $response;
        }

        $request->session()->forget(['state', 'login_challenge']);
        $code = 'google_unavailable';
        if ($exception instanceof ApiException) {
            $code = $exception->errorCode;
        } elseif ($exception instanceof ValidationException) {
            $code = 'google_response_invalid';
        }

        return redirect('/login?'.http_build_query(['error' => $code]));
    }

}

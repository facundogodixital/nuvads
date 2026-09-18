<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Exceptions\Handler as LaravelHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;


class Handler extends LaravelHandler
{

    // Filtros propios de Sentry. Todavía no hay exclusiones de negocio.
    protected array $sentryDontReport = [];

    protected array $sentryDontReportCodes = [];


    public function register(): void
    {
        $this->reportable([$this, 'reportToSentry']);
        $this->renderable([$this, 'renderAuthenticationError']);
        $this->renderable([$this, 'renderJson']);
    }


    protected function renderAuthenticationError(Throwable $exception, Request $request): ?RedirectResponse
    {
        $isGoogleFlow = $request->is('auth/google/*');
        $isDisabledAccount = $exception instanceof ApiException && $exception->errorCode === 'account_disabled';
        $isAuthenticationError = $isGoogleFlow || $isDisabledAccount;
        $shouldReturnJson = $this->shouldReturnJson($request, $exception);

        if (!$isAuthenticationError || $shouldReturnJson) {
            return null;
        }

        $message = 'No pudimos completar el acceso. Vuelve a intentarlo.';
        if ($exception instanceof ApiException) {
            $message = $exception->getMessage();
        } elseif ($exception instanceof ValidationException) {
            $message = 'La respuesta de acceso no es válida. Vuelve a intentarlo.';
        }

        return redirect()->route('home')->with('auth_error', $message);
    }


    protected function reportToSentry(Throwable $exception): void
    {
        // Laravel aplica primero sus exclusiones nativas de reporte.
        $shouldReport = $this->shouldReportToSentry($exception);
        $sentryIsAvailable = $this->container->bound('sentry');

        if (!$shouldReport || !$sentryIsAvailable) {
            return;
        }

        try {
            $this->container->make('sentry')->captureException($exception);
        } catch (Throwable $reportingException) {
            // Un fallo de Sentry no debe impedir que Laravel registre el error original.
            $this->container->make(LoggerInterface::class)->error(
                'No se pudo enviar la excepción a Sentry.',
                ['exception' => $reportingException],
            );
        }
    }


    protected function shouldReportToSentry(Throwable $exception): bool
    {
        foreach ($this->sentryDontReport as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return false;
            }
        }

        if ($exception instanceof ApiException) {
            return !in_array($exception->errorCode, $this->sentryDontReportCodes, true);
        }

        return true;
    }


    protected function renderJson(Throwable $exception, Request $request): ?JsonResponse
    {
        $shouldReturnJson = $this->shouldReturnJson($request, $exception);
        $hasExplicitResponse = $exception instanceof HttpResponseException;

        if (!$shouldReturnJson || $hasExplicitResponse) {
            return null;
        }

        $status = 500;
        $headers = [];
        $data = [
            'code' => 'internal_error',
            'message' => 'Ocurrió un error inesperado.',
        ];

        if ($exception instanceof ApiException) {
            $status = $exception->httpStatus;
            $data = [
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ];
        } elseif ($exception instanceof ValidationException) {
            if ($exception->response !== null) {
                return null;
            }

            $status = $exception->status;
            $data = [
                'code' => 'validation_failed',
                'message' => 'Revisá los campos indicados.',
                'errors' => $exception->errors(),
            ];
        } elseif ($exception instanceof AuthenticationException) {
            $status = 401;
            $data = [
                'code' => 'unauthenticated',
                'message' => 'Debés iniciar sesión.',
            ];
        } elseif ($exception instanceof HttpExceptionInterface) {
            // Laravel ya convirtió aquí los errores de autorización, modelos y CSRF.
            $status = $exception->getStatusCode();
            $headers = $exception->getHeaders();
            $data = $this->httpErrorData($status);
        }

        $showDebug = $this->container->environment('local') && config('app.debug');

        if ($showDebug) {
            $trace = [];

            foreach ($exception->getTrace() as $frame) {
                $trace[] = Arr::except($frame, ['args', 'object']);
            }

            $data['debug'] = [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $trace,
            ];
        }

        return new JsonResponse($data, $status, $headers);
    }


    protected function httpErrorData(int $status): array
    {
        // Estos mensajes son públicos; no se expone el mensaje interno de la excepción.
        [$code, $message] = match ($status) {
            400 => ['bad_request', 'La solicitud no es válida.'],
            401 => ['unauthenticated', 'Debés iniciar sesión.'],
            403 => ['forbidden', 'No tenés permiso para realizar esta operación.'],
            404 => ['not_found', 'No se encontró el recurso solicitado.'],
            405 => ['method_not_allowed', 'El método HTTP no está permitido.'],
            409 => ['conflict', 'La solicitud entra en conflicto con el estado actual del recurso.'],
            419 => ['session_expired', 'La sesión expiró. Volvé a cargar la página.'],
            422 => ['validation_failed', 'Revisá los campos indicados.'],
            429 => ['too_many_requests', 'Realizaste demasiadas solicitudes. Intentá nuevamente más tarde.'],
            default => $status >= 500
                ? ['internal_error', 'Ocurrió un error inesperado.']
                : ['http_error', 'No se pudo completar la solicitud.'],
        };

        return ['code' => $code, 'message' => $message];
    }

}

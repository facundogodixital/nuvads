<?php

namespace Tests\Feature\Errors;

use Tests\TestCase;
use RuntimeException;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;


class JsonErrorTest extends TestCase
{


    // Un error de negocio debe conservar su estado HTTP, código y mensaje público en el JSON acordado.
    #[Test]
    public function presents_business_errors_using_their_status_code_and_message(): void
    {
        Route::get('/_tests/error', function (): never {
            throw new ApiException(409, 'account_conflict', 'La cuenta ya existe.');
        });

        $this->getJson('/_tests/error')->assertConflict()->assertExactJson([
            'code' => 'account_conflict',
            'message' => 'La cuenta ya existe.',
        ]);
    }


    // Un fallo de validación debe devolver 422 y los mensajes por campo dentro de errors.
    #[Test]
    public function includes_field_errors_only_for_validation_failures(): void
    {
        Route::get('/_tests/error', function (): never {
            throw ValidationException::withMessages(['email' => ['El email es obligatorio.']]);
        });

        $this->getJson('/_tests/error')->assertUnprocessable()->assertExactJson([
            'code' => 'validation_failed',
            'message' => 'Revisá los campos indicados.',
            'errors' => ['email' => ['El email es obligatorio.']],
        ]);
    }


    // Los errores HTTP deben conservar estado y cabeceras, traducir el código y ocultar detalles internos.
    #[Test]
    #[DataProvider('httpErrors')]
    public function preserves_http_error_status_and_headers(int $status, string $code): void
    {
        Route::get('/_tests/error', function () use ($status): never {
            throw new HttpException($status, 'private-details', null, ['Retry-After' => '60']);
        });

        $this->getJson('/_tests/error')
            ->assertStatus($status)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('code', $code)
            ->assertJsonMissingPath('errors')
            ->assertJsonMissingPath('debug')
            ->assertDontSee('private-details');
    }


    public static function httpErrors(): array
    {
        return [
            [403, 'forbidden'],
            [404, 'not_found'],
            [419, 'session_expired'],
            [429, 'too_many_requests'],
            [503, 'internal_error'],
        ];
    }


    // Fuera del entorno local, una excepción inesperada debe mostrar un error genérico aunque debug esté activado.
    #[Test]
    public function hides_unexpected_exception_details_outside_local_environment(): void
    {
        config(['app.debug' => true]);
        Route::get('/_tests/error', function (): never {
            throw new RuntimeException('private-database-details');
        });

        $this->getJson('/_tests/error')->assertInternalServerError()->assertExactJson([
            'code' => 'internal_error',
            'message' => 'Ocurrió un error inesperado.',
        ]);
    }


    // En local con debug, debe incluir el diagnóstico técnico, quitando argumentos y objetos de cada entrada del trace.
    #[Test]
    public function provides_local_debug_information_without_trace_arguments(): void
    {
        $this->app->instance('env', 'local');
        config(['app.debug' => true]);
        Route::get('/_tests/error', function (): never {
            throw new RuntimeException('private-database-details');
        });

        $response = $this->getJson('/_tests/error')->assertInternalServerError()
            ->assertJsonPath('debug.exception', RuntimeException::class)
            ->assertJsonStructure(['debug' => ['exception', 'file', 'line', 'trace']]);

        $this->assertNotEmpty($response->json('debug.trace'));
        foreach ($response->json('debug.trace') as $frame) {
            $this->assertArrayNotHasKey('args', $frame);
            $this->assertArrayNotHasKey('object', $frame);
        }
    }

}

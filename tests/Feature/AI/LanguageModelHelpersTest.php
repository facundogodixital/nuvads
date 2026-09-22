<?php

namespace Tests\Feature\AI;

use Tests\TestCase;
use App\Helpers\OpenAIHelper;
use App\Helpers\DeepSeekHelper;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;


class LanguageModelHelpersTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-token', 'services.deepseek.api_key' => 'test-token']);
    }


    // Ambos proveedores devuelven el texto final y mantienen separados instrucciones y contenido.
    #[Test]
    #[DataProvider('providers')]
    public function generates_text(string $helperClass, string $provider, string $endpoint): void
    {
        $payload = $this->getResponsePayload($provider, 'Respuesta final');
        Http::fake([$endpoint => Http::response($payload)]);

        $text = resolve($helperClass)->generateText('selected-model', 'Resume el contenido.', 'Contenido.', 800);

        $this->assertSame('Respuesta final', $text);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($provider, $endpoint): bool {
            $this->assertSame('POST', $request->method());
            $this->assertSame($endpoint, $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-token'));
            $this->assertSame('selected-model', $request['model']);
            if ($provider === 'openai') {
                $this->assertSame(false, $request['store']);
                $this->assertSame(800, $request['max_output_tokens']);
                $this->assertSame('Contenido.', $request['input']);
                $this->assertSame('Resume el contenido.', $request['instructions']);
                $this->assertSame('text', $request['text']['format']['type']);
            } else {
                $this->assertSame(false, $request['stream']);
                $this->assertSame(800, $request['max_tokens']);
                $this->assertSame('text', $request['response_format']['type']);
                $this->assertSame([
                    ['role' => 'system', 'content' => 'Resume el contenido.'],
                    ['role' => 'user', 'content' => 'Contenido.'],
                ], $request['messages']);
            }

            return true;
        });
    }


    // El modo JSON devuelve campos y nulos; no agrega un límite de tokens si no se indicó.
    #[Test]
    #[DataProvider('providers')]
    public function generates_json(string $helperClass, string $provider, string $endpoint): void
    {
        $payload = $this->getResponsePayload($provider, '{"name":"Up!","history":null}');
        Http::fake([$endpoint => Http::response($payload)]);

        $data = resolve($helperClass)->generateJson('selected-model', 'Extrae la marca.', 'Contenido.');

        $this->assertSame(['name' => 'Up!', 'history' => null], $data);
        Http::assertSent(function (Request $request) use ($provider): bool {
            if ($provider === 'openai') {
                $this->assertArrayNotHasKey('max_output_tokens', $request->data());
                $this->assertStringContainsString('JSON', $request['instructions']);
                $this->assertStringContainsString('JSON', $request['input']);
                $this->assertSame('json_object', $request['text']['format']['type']);
            } else {
                $this->assertArrayNotHasKey('max_tokens', $request->data());
                $this->assertStringContainsString('JSON', $request['messages'][0]['content']);
                $this->assertSame('json_object', $request['response_format']['type']);
            }

            return true;
        });
    }


    // Se rechazan objetos truncados, bloques Markdown y raíces que no sean objetos JSON.
    #[Test]
    #[DataProvider('invalidJsonResponses')]
    public function rejects_invalid_json(string $helperClass, string $provider, string $endpoint, string $text): void
    {
        Http::fake([$endpoint => Http::response($this->getResponsePayload($provider, $text))]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/JSON/');

        resolve($helperClass)->generateJson('selected-model', 'Extrae campos.', 'Contenido.');
    }


    public static function invalidJsonResponses(): array
    {
        $cases = [];
        foreach (self::providers() as $provider) {
            foreach (['{"name":', '```json {"name":"Up!"} ```', '[]', 'null', '42'] as $text) {
                $cases[] = [...$provider, $text];
            }
        }

        return $cases;
    }


    // Los fallos HTTP se traducen con el estado y el mensaje del proveedor, sin reintentar el consumo.
    #[Test]
    #[DataProvider('providers')]
    public function reports_provider_errors_with_status_and_message(
        string $helperClass,
        string $provider,
        string $endpoint,
    ): void {
        Http::fake([$endpoint => Http::response(['error' => ['message' => 'Model not found']], 429)]);

        try {
            resolve($helperClass)->generateText('selected-model', 'Resume.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame("{$provider}_request_failed", $exception->errorCode);
            $this->assertStringContainsString('429: Model not found', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }


    // Las respuestas incompletas se descartan aunque el fragmento recibido parezca JSON válido.
    #[Test]
    #[DataProvider('providers')]
    public function rejects_incomplete_responses(string $helperClass, string $provider, string $endpoint): void
    {
        $payload = $this->getResponsePayload($provider, '{"name":"Up!"}');
        if ($provider === 'openai') {
            $payload['status'] = 'incomplete';
        } else {
            $payload['choices'][0]['finish_reason'] = 'length';
        }
        Http::fake([$endpoint => Http::response($payload)]);

        try {
            resolve($helperClass)->generateJson('selected-model', 'Extrae campos.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame("{$provider}_response_incomplete", $exception->errorCode);
        }
    }


    // Un rechazo del proveedor no se presenta como texto generado ni como un error de JSON.
    #[Test]
    #[DataProvider('providers')]
    public function rejects_refusals(string $helperClass, string $provider, string $endpoint): void
    {
        $payload = $this->getResponsePayload($provider, '');
        if ($provider === 'openai') {
            $payload['output'][1]['content'] = [['type' => 'refusal', 'refusal' => 'Rejected']];
        } else {
            $payload['choices'][0]['finish_reason'] = 'content_filter';
        }
        Http::fake([$endpoint => Http::response($payload)]);

        try {
            resolve($helperClass)->generateText('selected-model', 'Resume.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame("{$provider}_response_refused", $exception->errorCode);
        }
    }


    // La falta de clave se detecta antes de enviar contenido al proveedor.
    #[Test]
    #[DataProvider('providers')]
    public function rejects_missing_credentials(string $helperClass, string $provider, string $endpoint): void
    {
        Http::fake();
        config(["services.{$provider}.api_key" => null]);

        try {
            resolve($helperClass)->generateText('selected-model', 'Resume.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame("{$provider}_not_configured", $exception->errorCode);
        }

        Http::assertNothingSent();
    }


    // Un límite inválido falla en la entrada sin consumir una llamada externa.
    #[Test]
    #[DataProvider('providers')]
    public function rejects_invalid_token_limits(string $helperClass, string $provider, string $endpoint): void
    {
        Http::fake();

        try {
            resolve($helperClass)->generateText('selected-model', 'Resume.', 'Contenido.', 0);
            $this->fail('Se esperaba una excepción.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('maxOutputTokens', $exception->errors());
        }

        Http::assertNothingSent();
    }


    // Un fallo de red se convierte en una excepción controlada de la integración.
    #[Test]
    #[DataProvider('providers')]
    public function handles_connection_failures(string $helperClass, string $provider, string $endpoint): void
    {
        Http::fake([$endpoint => Http::failedConnection()]);

        try {
            resolve($helperClass)->generateText('selected-model', 'Resume.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame("{$provider}_unavailable", $exception->errorCode);
        }
    }


    public static function providers(): array
    {
        return [
            [OpenAIHelper::class, 'openai', 'https://api.openai.com/v1/responses'],
            [DeepSeekHelper::class, 'deepseek', 'https://api.deepseek.com/chat/completions'],
        ];
    }


    // El proveedor debe entregar un mensaje con contenido, no HTML, JSON escalar ni estructuras incompletas.
    #[Test]
    #[DataProvider('malformedResponses')]
    public function rejects_malformed_responses(
        string $helperClass,
        string $provider,
        string $endpoint,
        string $body,
    ): void {
        Http::fake([$endpoint => Http::response($body)]);

        try {
            resolve($helperClass)->generateText('selected-model', 'Resume.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame("{$provider}_response_invalid", $exception->errorCode);
        }
    }


    public static function malformedResponses(): array
    {
        $cases = [];
        foreach (self::providers() as $provider) {
            foreach (['<html>Error</html>', 'null', '42', '{}'] as $body) {
                $cases[] = [...$provider, $body];
            }
        }

        $cases[] = [...self::providers()[0], '{"status":"completed","output":[]}'];
        $cases[] = [...self::providers()[1], '{"choices":[{"finish_reason":"stop",'
            .'"message":{"role":"assistant","content":" "}}]}'];

        return $cases;
    }


    // Texto e imágenes viajan juntos como contenido del usuario; las instrucciones quedan separadas.
    #[Test]
    #[DataProvider('imageOutputFormats')]
    public function sends_image_references(string $method, string $outputFormat): void
    {
        $imageUrls = ['https://example.com/logo.png', 'https://example.com/banner.webp'];
        $payload = $this->getResponsePayload('openai', '{"style":"natural"}');
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($payload)]);

        $result = resolve(OpenAIHelper::class)->{$method}(
            model: 'vision-model',
            instructions: 'Describe el estilo.',
            input: 'Marca de jardinería.',
            imageUrls: $imageUrls,
        );

        if ($outputFormat === 'json_object') {
            $this->assertSame(['style' => 'natural'], $result);
        } else {
            $this->assertSame('{"style":"natural"}', $result);
        }
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($imageUrls, $outputFormat): bool {
            $this->assertStringStartsWith('Describe el estilo.', $request['instructions']);
            $this->assertSame($outputFormat, $request['text']['format']['type']);
            $expectedInputText = $outputFormat === 'json_object'
                ? "Marca de jardinería.\nDevuelve únicamente un objeto JSON válido, sin bloques Markdown."
                : 'Marca de jardinería.';
            $this->assertSame([[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $expectedInputText],
                    ['type' => 'input_image', 'image_url' => $imageUrls[0]],
                    ['type' => 'input_image', 'image_url' => $imageUrls[1]],
                ],
            ]], $request['input']);

            return true;
        });
    }


    public static function imageOutputFormats(): array
    {
        return [['generateText', 'text'], ['generateJson', 'json_object']];
    }


    // Las referencias deben ser una lista de URLs HTTP o HTTPS antes de llamar al proveedor.
    #[Test]
    #[DataProvider('invalidImageUrls')]
    public function rejects_invalid_image_references(array $imageUrls): void
    {
        Http::fake();

        try {
            resolve(OpenAIHelper::class)->generateText(
                model: 'vision-model',
                instructions: 'Describe el estilo.',
                input: 'Marca de jardinería.',
                imageUrls: $imageUrls,
            );
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        Http::assertNothingSent();
    }


    public static function invalidImageUrls(): array
    {
        return [
            [['file:///tmp/logo.png']],
            [['/tmp/logo.png']],
            [['']],
            [[null]],
            [['logo' => 'https://example.com/logo.png']],
        ];
    }


    private function getResponsePayload(string $provider, string $text): array
    {
        if ($provider === 'openai') {
            return [
                'status' => 'completed',
                'output' => [
                    ['type' => 'reasoning', 'summary' => []],
                    [
                        'type' => 'message',
                        'role' => 'assistant',
                        'status' => 'completed',
                        'content' => [['type' => 'output_text', 'text' => $text]],
                    ],
                ],
            ];
        }

        return ['choices' => [[
            'finish_reason' => 'stop',
            'message' => ['role' => 'assistant', 'content' => $text],
        ]]];
    }

}

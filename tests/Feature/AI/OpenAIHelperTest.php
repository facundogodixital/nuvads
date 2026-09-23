<?php

namespace Tests\Feature\AI;

use Tests\TestCase;
use App\Helpers\OpenAIHelper;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;


class OpenAIHelperTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();

        config(['services.openai.api_key' => 'test-token']);
    }


    // Separa instrucciones y contenido, pide un objeto JSON sin almacenarlo y devuelve ese JSON decodificado.
    #[Test]
    public function generates_json_with_separated_instructions_and_content(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(
            self::completedResponse('{"name":"Up!","history":null}'),
        )]);

        $data = resolve(OpenAIHelper::class)->generateJson('selected-model', 'Extrae la marca.', 'Contenido.');

        $this->assertSame(['name' => 'Up!', 'history' => null], $data);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $this->assertSame('POST', $request->method());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-token'));
            $this->assertSame('selected-model', $request['model']);
            $this->assertFalse($request['store']);
            $this->assertSame('json_object', $request['text']['format']['type']);
            $this->assertStringStartsWith('Extrae la marca.', $request['instructions']);
            $this->assertStringStartsWith('Contenido.', $request['input']);
            $this->assertArrayNotHasKey('max_output_tokens', $request->data());

            return true;
        });
    }


    // Una respuesta que no sirve para seguir se rechaza con su propio código, en vez de llegar como datos.
    #[Test]
    #[DataProvider('unusableResponses')]
    public function rejects_unusable_responses(array|string $body, string $errorCode): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($body)]);

        try {
            resolve(OpenAIHelper::class)->generateJson('selected-model', 'Extrae la marca.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
        }
    }


    public static function unusableResponses(): array
    {
        $incomplete = [...self::completedResponse('{"name":"Up!"}'), 'status' => 'incomplete'];
        $refusal = self::completedResponse('');
        $refusal['output'][1]['content'] = [['type' => 'refusal', 'refusal' => 'Rejected']];

        return [
            'truncated JSON' => [self::completedResponse('{"name":'), 'openai_json_invalid'],
            'JSON list instead of object' => [self::completedResponse('[]'), 'openai_json_invalid'],
            'incomplete status' => [$incomplete, 'openai_response_incomplete'],
            'refusal' => [$refusal, 'openai_response_refused'],
            'HTML body' => ['<html>Error</html>', 'openai_response_invalid'],
        ];
    }


    // Un fallo HTTP del proveedor se informa con su estado y su mensaje, sin reintentar el consumo.
    #[Test]
    public function reports_provider_errors_with_status_and_message(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(
            ['error' => ['message' => 'Model not found']], 429,
        )]);

        try {
            resolve(OpenAIHelper::class)->generateJson('selected-model', 'Extrae la marca.', 'Contenido.');
            $this->fail('Se esperaba una excepción.');
        } catch (ApiException $exception) {
            $this->assertSame('openai_request_failed', $exception->errorCode);
            $this->assertStringContainsString('429: Model not found', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }


    private static function completedResponse(string $text): array
    {
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

}

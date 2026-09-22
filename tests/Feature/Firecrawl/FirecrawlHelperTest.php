<?php

namespace Tests\Feature\Firecrawl;

use Tests\TestCase;
use App\Exceptions\ApiException;
use App\Helpers\FirecrawlHelper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;


class FirecrawlHelperTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();

        config(['services.firecrawl.api_key' => 'test-token']);
    }


    // Conserva el JSON byte por byte, incluidos campos adicionales, y pide los formatos acordados.
    #[Test]
    public function preserves_raw_json_and_requests_branding(): void
    {
        $rawJson = '{ "success": true, "data": {"markdown":"", "metadata":{"title":"Up!"},'
            .' "images":[], "links":[], "branding":null, "extra":"preserved"} }';
        Http::fake(['https://api.firecrawl.dev/v2/scrape' => Http::response($rawJson)]);

        $result = resolve(FirecrawlHelper::class)->scrapeWebsite('https://example.com');

        $this->assertSame($rawJson, $result);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $isPost = $request->method() === 'POST';
            $hasToken = $request->hasHeader('Authorization', 'Bearer test-token');
            $hasExpectedInput = $request->data() === [
                'url' => 'https://example.com',
                'formats' => ['markdown', 'branding', 'images', 'links'],
            ];

            return $isPost && $hasToken && $hasExpectedInput;
        });
    }


    // Los errores externos se comunican sin exponer el cuerpo recibido ni repetir la petición.
    #[Test]
    #[DataProvider('invalidResponses')]
    public function rejects_invalid_responses(string $body, int $status, string $errorCode, string $detail): void
    {
        Http::fake(['https://api.firecrawl.dev/v2/scrape' => Http::response($body, $status)]);

        try {
            resolve(FirecrawlHelper::class)->scrapeWebsite('https://example.com');
            $this->fail('Se esperaba un error de Firecrawl.');
        } catch (ApiException $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
            $this->assertStringContainsString($detail, $exception->getMessage());
        }

        Http::assertSentCount(1);
    }


    public static function invalidResponses(): array
    {
        return [
            ['provider-error-body', 429, 'firecrawl_request_failed', '429: provider-error-body'],
            ['{"error":"Rate limit"}', 500, 'firecrawl_request_failed', '500: Rate limit'],
            ['not-json', 200, 'firecrawl_response_invalid', 'not-json'],
            ['{"success":false,"error":"Blocked page"}', 200, 'firecrawl_response_invalid', 'Blocked page'],
            ['{"success":true,"data":{"markdown":42}}', 200, 'firecrawl_response_invalid', 'data.markdown'],
        ];
    }


    // La ausencia de credenciales impide enviar solicitudes al proveedor.
    #[Test]
    public function rejects_missing_credentials(): void
    {
        Http::fake();
        config(['services.firecrawl.api_key' => null]);

        try {
            resolve(FirecrawlHelper::class)->scrapeWebsite('https://example.com');
            $this->fail('Se esperaba un error de configuración.');
        } catch (ApiException $exception) {
            $this->assertSame('firecrawl_not_configured', $exception->errorCode);
        }

        Http::assertNothingSent();
    }


    // Solo se admiten URLs HTTP o HTTPS antes de realizar la llamada externa.
    #[Test]
    public function rejects_invalid_urls(): void
    {
        Http::fake();

        try {
            resolve(FirecrawlHelper::class)->scrapeWebsite('file:///etc/passwd');
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('url', $exception->errors());
        }

        Http::assertNothingSent();
    }


    // Un fallo de conexión se traduce a un error seguro de la integración.
    #[Test]
    public function handles_connection_failures(): void
    {
        Http::fake(['https://api.firecrawl.dev/v2/scrape' => Http::failedConnection()]);

        try {
            resolve(FirecrawlHelper::class)->scrapeWebsite('https://example.com');
            $this->fail('Se esperaba un error de conexión.');
        } catch (ApiException $exception) {
            $this->assertSame('firecrawl_unavailable', $exception->errorCode);
        }
    }

}

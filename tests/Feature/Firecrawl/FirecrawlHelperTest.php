<?php

namespace Tests\Feature\Firecrawl;

use Tests\TestCase;
use App\Exceptions\ApiException;
use App\Helpers\FirecrawlHelper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
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


    // Un error del proveedor, una respuesta inutilizable o una página con error se informan con su propio código,
    // sin repetir la petición.
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
        $pageWithError = '{"success":true,"data":{"markdown":"","metadata":{"statusCode":404},"images":[],"links":[]}}';

        return [
            'provider error' => ['{"error":"Rate limit"}', 500, 'firecrawl_request_failed', '500: Rate limit'],
            'blocked page' => [
                '{"success":false,"error":"Blocked page"}', 200, 'firecrawl_response_invalid', 'Blocked page',
            ],
            'page with error status' => [$pageWithError, 200, 'firecrawl_page_failed', '404'],
        ];
    }

}

<?php

namespace Tests\Feature\Apify;

use Tests\TestCase;
use App\DTO\ApifyRunDto;
use App\Helpers\ApifyHelper;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;


class ApifyHelperTest extends TestCase
{


    protected function setUp(): void
    {
        parent::setUp();

        config(['services.apify.api_key' => 'test-token']);
    }


    // Cada actor recibe su entrada específica y devuelve la ejecución sin esperar los resultados.
    #[Test]
    #[DataProvider('actorInputs')]
    public function starts_actors_asynchronously(string $method, string $actor, array $arguments, array $input): void
    {
        $url = "https://api.apify.com/v2/actors/{$actor}/runs";
        Http::fake([$url => Http::response(['data' => [
            'id' => 'run123', 'status' => 'READY', 'defaultDatasetId' => 'dataset123',
        ]], 201)]);

        $run = resolve(ApifyHelper::class)->{$method}(...$arguments);

        $this->assertInstanceOf(ApifyRunDto::class, $run);
        $this->assertSame('run123', $run->id);
        $this->assertSame('READY', $run->status);
        $this->assertSame('dataset123', $run->datasetId);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === $url
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->data() === $input);
    }


    public static function actorInputs(): array
    {
        $websiteUrl = 'https://example.com';
        $facebookUrl = 'https://www.facebook.com/example';
        $mapsUrl = 'https://www.google.com/maps/place/example';

        return [
            ['startWebsiteContentCrawler', 'apify~website-content-crawler', [[$websiteUrl], 20], [
                'startUrls' => [['url' => $websiteUrl]], 'maxCrawlPages' => 20,
            ]],
            ['startWebsiteContentCrawler', 'apify~website-content-crawler', [[$websiteUrl]], [
                'startUrls' => [['url' => $websiteUrl]],
            ]],
            ['startFacebookAdsScraper', 'apify~facebook-ads-scraper', [[$facebookUrl], 10], [
                'startUrls' => [['url' => $facebookUrl]], 'resultsLimit' => 10,
            ]],
            ['startInstagramPostScraper', 'apify~instagram-post-scraper', [['example'], 20], [
                'username' => ['example'], 'resultsLimit' => 20,
            ]],
            ['startGoogleMapsReviewsScraper', 'compass~google-maps-reviews-scraper', [[$mapsUrl], 30], [
                'startUrls' => [['url' => $mapsUrl]], 'maxReviews' => 30,
            ]],
            ['startFacebookAdsScraper', 'apify~facebook-ads-scraper', [[$facebookUrl]], [
                'startUrls' => [['url' => $facebookUrl]],
            ]],
            ['startInstagramPostScraper', 'apify~instagram-post-scraper', [['example']], [
                'username' => ['example'],
            ]],
            ['startGoogleMapsReviewsScraper', 'compass~google-maps-reviews-scraper', [[$mapsUrl]], [
                'startUrls' => [['url' => $mapsUrl]],
            ]],
        ];
    }


    // Una consulta conserva el estado devuelto, incluso fallos, y admite que aún no haya dataset.
    #[Test]
    public function retrieves_a_run_without_a_dataset(): void
    {
        Http::fake(['https://api.apify.com/v2/actor-runs/run123' => Http::response([
            'data' => ['id' => 'run123', 'status' => 'FAILED'],
        ])]);

        $run = resolve(ApifyHelper::class)->getRun('run123');

        $this->assertSame('run123', $run->id);
        $this->assertSame('FAILED', $run->status);
        $this->assertNull($run->datasetId);
    }


    // Conserva todos los campos del dataset y omite limit cuando se solicita sin límite.
    #[Test]
    #[DataProvider('datasetPages')]
    public function retrieves_raw_dataset_items(int $offset, ?int $limit, array $query): void
    {
        $items = [['id' => '001', '#hidden' => true, 'snapshot' => ['cards' => [], 'text' => null]]];
        Http::fake(['https://api.apify.com/v2/datasets/dataset123/items*' => Http::response($items)]);

        $result = resolve(ApifyHelper::class)->getDatasetItems('dataset123', $offset, $limit);

        $this->assertSame($items, $result);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->data() === $query);
    }


    public static function datasetPages(): array
    {
        return [
            [0, null, ['format' => 'json', 'offset' => 0]],
            [100, 50, ['format' => 'json', 'offset' => 100, 'limit' => 50]],
        ];
    }


    // Un dataset vacío es un resultado válido.
    #[Test]
    public function accepts_an_empty_dataset(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->assertSame([], resolve(ApifyHelper::class)->getDatasetItems('dataset123'));
    }


    // Las respuestas inválidas o fallidas generan errores propios sin publicar el cuerpo externo.
    #[Test]
    #[DataProvider('invalidResponses')]
    public function rejects_invalid_responses(string $method, array|string $body, int $status, string $code): void
    {
        Http::fake(['*' => Http::response($body, $status)]);

        try {
            resolve(ApifyHelper::class)->{$method}('id123');
            $this->fail('Se esperaba un error de Apify.');
        } catch (ApiException $exception) {
            $this->assertSame($code, $exception->errorCode);
            $this->assertSame(502, $exception->httpStatus);
            $this->assertStringNotContainsString('secret', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }


    public static function invalidResponses(): array
    {
        return [
            ['getRun', ['error' => ['message' => 'secret']], 401, 'apify_request_failed'],
            ['getRun', 'not-json', 200, 'apify_response_invalid'],
            ['getRun', ['data' => ['id' => '123']], 200, 'apify_response_invalid'],
            ['getRun', ['data' => ['id' => [], 'status' => 'READY']], 200, 'apify_response_invalid'],
            ['getDatasetItems', '{}', 200, 'apify_response_invalid'],
            ['getDatasetItems', 'not-json', 200, 'apify_response_invalid'],
            ['getDatasetItems', [42], 200, 'apify_response_invalid'],
        ];
    }


    // Un fallo de conexión no vuelve a iniciar el actor y no expone la excepción de transporte.
    #[Test]
    public function reports_connection_failures_without_retrying(): void
    {
        $attempts = 0;
        Http::fake(function (Request $request) use (&$attempts): mixed {
            $attempts++;
            return Http::failedConnection('secret');
        });

        try {
            resolve(ApifyHelper::class)->startInstagramPostScraper(['example']);
            $this->fail('Se esperaba un error de conexión.');
        } catch (ApiException $exception) {
            $this->assertSame('apify_unavailable', $exception->errorCode);
            $this->assertNull($exception->getPrevious());
        }
        $this->assertSame(1, $attempts);
    }


    // Los parámetros inválidos se rechazan antes de contactar al proveedor.
    #[Test]
    #[DataProvider('invalidInputs')]
    public function rejects_invalid_input(string $method, array $arguments): void
    {
        Http::fake();

        try {
            resolve(ApifyHelper::class)->{$method}(...$arguments);
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
        Http::assertNothingSent();
    }


    public static function invalidInputs(): array
    {
        return [
            ['startWebsiteContentCrawler', [[]]],
            ['startWebsiteContentCrawler', [['invalid-url']]],
            ['startWebsiteContentCrawler', [['https://example.com'], 0]],
            ['startFacebookAdsScraper', [[]]],
            ['startGoogleMapsReviewsScraper', [['invalid-url']]],
            ['startInstagramPostScraper', [[42]]],
            ['startInstagramPostScraper', [['example'], 0]],
            ['getRun', ['../runs']],
            ['getDatasetItems', ['dataset123', -1]],
            ['getDatasetItems', ['dataset123', 0, 0]],
        ];
    }


    // La falta de credenciales impide enviar una petición.
    #[Test]
    public function requires_an_api_key(): void
    {
        Http::fake();
        config(['services.apify.api_key' => null]);

        try {
            resolve(ApifyHelper::class)->getRun('run123');
            $this->fail('Se esperaba un error de configuración.');
        } catch (ApiException $exception) {
            $this->assertSame('apify_not_configured', $exception->errorCode);
        }
        Http::assertNothingSent();
    }

}

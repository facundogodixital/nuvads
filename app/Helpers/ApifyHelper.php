<?php

namespace App\Helpers;

use stdClass;
use App\DTO\ApifyRunDto;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Client\ConnectionException;


class ApifyHelper
{


    /** @param list<string> $urls */
    public function startFacebookAdsScraper(array $urls, ?int $resultsLimit = null): ApifyRunDto
    {
        Validator::make(compact('urls', 'resultsLimit'), [
            'urls' => ['required', 'array', 'list', 'min:1'],
            'urls.*' => ['required', 'string', 'url:http,https'],
            'resultsLimit' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $input = ['startUrls' => []];
        foreach ($urls as $url) {
            $input['startUrls'][] = ['url' => $url];
        }
        $hasResultsLimit = $resultsLimit !== null;
        if ($hasResultsLimit) {
            $input['resultsLimit'] = $resultsLimit;
        }

        $response = $this->sendRequest('POST', 'actors/apify~facebook-ads-scraper/runs', $input);
        return $this->getValidatedRun($response);
    }


    /** @param list<string> $usernames */
    public function startInstagramPostScraper(array $usernames, ?int $resultsLimit = null): ApifyRunDto
    {
        Validator::make(compact('usernames', 'resultsLimit'), [
            'usernames' => ['required', 'array', 'list', 'min:1'],
            'usernames.*' => ['required', 'string'],
            'resultsLimit' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $input = ['username' => $usernames];
        $hasResultsLimit = $resultsLimit !== null;
        if ($hasResultsLimit) {
            $input['resultsLimit'] = $resultsLimit;
        }

        $response = $this->sendRequest('POST', 'actors/apify~instagram-post-scraper/runs', $input);
        return $this->getValidatedRun($response);
    }


    /** @param list<string> $urls */
    public function startGoogleMapsReviewsScraper(array $urls, ?int $maxReviews = null): ApifyRunDto
    {
        Validator::make(compact('urls', 'maxReviews'), [
            'urls' => ['required', 'array', 'list', 'min:1'],
            'urls.*' => ['required', 'string', 'url:http,https'],
            'maxReviews' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $input = ['startUrls' => []];
        foreach ($urls as $url) {
            $input['startUrls'][] = ['url' => $url];
        }
        $hasMaxReviews = $maxReviews !== null;
        if ($hasMaxReviews) {
            $input['maxReviews'] = $maxReviews;
        }

        $response = $this->sendRequest('POST', 'actors/compass~google-maps-reviews-scraper/runs', $input);
        return $this->getValidatedRun($response);
    }


    /**
     * Sin maxCrawlPages se conserva el límite predeterminado del actor.
     *
     * @param  list<string>  $urls
     */
    public function startWebsiteContentCrawler(array $urls, ?int $maxCrawlPages = null): ApifyRunDto
    {
        Validator::make(compact('urls', 'maxCrawlPages'), [
            'urls' => ['required', 'array', 'list', 'min:1'],
            'urls.*' => ['required', 'string', 'url:http,https'],
            'maxCrawlPages' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $input = ['startUrls' => []];
        foreach ($urls as $url) {
            $input['startUrls'][] = ['url' => $url];
        }
        $hasMaxCrawlPages = $maxCrawlPages !== null;
        if ($hasMaxCrawlPages) {
            $input['maxCrawlPages'] = $maxCrawlPages;
        }

        $response = $this->sendRequest('POST', 'actors/apify~website-content-crawler/runs', $input);
        return $this->getValidatedRun($response);
    }


    public function getRun(string $runId): ApifyRunDto
    {
        Validator::make(compact('runId'), ['runId' => ['required', 'alpha_num:ascii']])->validate();

        $response = $this->sendRequest('GET', "actor-runs/{$runId}");
        return $this->getValidatedRun($response);
    }


    /**
     * Sin limit se recuperan todos los registros desde offset, con sus campos originales.
     *
     * @return list<array<string, mixed>>
     */
    public function getDatasetItems(string $datasetId, int $offset = 0, ?int $limit = null): array
    {
        Validator::make(compact('datasetId', 'offset', 'limit'), [
            'limit' => ['nullable', 'integer', 'min:1'],
            'offset' => ['required', 'integer', 'min:0'],
            'datasetId' => ['required', 'alpha_num:ascii'],
        ])->validate();

        $query = ['format' => 'json', 'offset' => $offset];
        $hasLimit = $limit !== null;
        if ($hasLimit) {
            $query['limit'] = $limit;
        }

        $response = $this->sendRequest('GET', "datasets/{$datasetId}/items", $query);
        $items = $response->object();
        $isDatasetArray = is_array($items);
        if (!$isDatasetArray) {
            throw new ApiException(502, 'apify_response_invalid', 'Apify devolvió una respuesta inválida.');
        }

        foreach ($items as $item) {
            $isDatasetItemObject = $item instanceof stdClass;
            if (!$isDatasetItemObject) {
                throw new ApiException(502, 'apify_response_invalid', 'Apify devolvió una respuesta inválida.');
            }
        }

        return $response->json();
    }


    private function getValidatedRun(Response $response): ApifyRunDto
    {
        $run = $response->json('data');
        $isRunArray = is_array($run);
        if (!$isRunArray) {
            throw new ApiException(502, 'apify_response_invalid', 'Apify devolvió una respuesta inválida.');
        }

        $validator = Validator::make($run, [
            'id' => ['required', 'string'],
            'status' => ['required', 'string'],
            'defaultDatasetId' => ['nullable', 'string'],
        ]);
        $hasInvalidRunData = $validator->fails();
        if ($hasInvalidRunData) {
            throw new ApiException(502, 'apify_response_invalid', 'Apify devolvió una respuesta inválida.');
        }

        return new ApifyRunDto(
            id: $run['id'],
            status: $run['status'],
            datasetId: $run['defaultDatasetId'] ?? null,
        );
    }


    private function sendRequest(string $method, string $path, array $parameters = []): Response
    {
        $apiKey = config('services.apify.api_key');
        $hasApiKey = is_string($apiKey) && trim($apiKey) !== '';
        if (!$hasApiKey) {
            throw new ApiException(500, 'apify_not_configured', 'La integración con Apify no está configurada.');
        }

        $parameterType = $method === 'GET' ? 'query' : 'json';

        try {
            // No reintentar el inicio: una respuesta perdida podría duplicar una ejecución paga.
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->withoutRedirecting()
                ->send($method, "https://api.apify.com/v2/{$path}", [$parameterType => $parameters]);
        } catch (ConnectionException $exception) {
            throw new ApiException(502, 'apify_unavailable', 'No se pudo establecer la comunicación con Apify.');
        }

        $isRequestSuccessful = $response->successful();
        if (!$isRequestSuccessful) {
            // La respuesta externa puede contener información sensible; no se expone en el error.
            throw new ApiException(502, 'apify_request_failed', 'Apify no pudo completar la solicitud.');
        }

        return $response;
    }

}

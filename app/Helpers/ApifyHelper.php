<?php

namespace App\Helpers;

use stdClass;
use App\DTO\ApifyRunDto;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class ApifyHelper
{

    public const int DEFAULT_WEBSITE_MAX_PAGES = 10;


    /**
     * Sin sorting, el actor devuelve los anuncios en el orden de la Biblioteca de anuncios; relevancy_monthly_grouped
     * trae primero los más nuevos y total_impressions, los de más impresiones.
     *
     * @param  list<string>  $urls
     */
    public function startMetaAdsScraper(array $urls, ?int $resultsLimit = null, ?string $sorting = null): ApifyRunDto
    {
        Validator::make(compact('urls', 'resultsLimit', 'sorting'), [
            'urls' => ['required', 'array', 'list', 'min:1'],
            'urls.*' => ['required', 'string', 'url:http,https'],
            'resultsLimit' => ['nullable', 'integer', 'min:1'],
            'sorting' => ['nullable', 'string', 'in:relevancy_monthly_grouped,total_impressions'],
        ])->validate();

        $input = ['startUrls' => []];
        foreach ($urls as $url) {
            $input['startUrls'][] = ['url' => $url];
        }
        $hasResultsLimit = $resultsLimit !== null;
        if ($hasResultsLimit) {
            $input['resultsLimit'] = $resultsLimit;
        }
        $hasSorting = $sorting !== null;
        if ($hasSorting) {
            $input['sorting'] = $sorting;
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
     * El límite puede ajustarse por llamada; por defecto se recopilan hasta diez páginas.
     *
     * @param  list<string>  $urls
     */
    public function startWebsiteContentCrawler(
        array $urls,
        int $maxCrawlPages = self::DEFAULT_WEBSITE_MAX_PAGES,
    ): ApifyRunDto {
        Validator::make(compact('urls', 'maxCrawlPages'), [
            'urls' => ['required', 'array', 'list', 'min:1'],
            'urls.*' => ['required', 'string', 'url:http,https'],
            'maxCrawlPages' => ['required', 'integer', 'min:1'],
        ])->validate();

        $input = ['startUrls' => []];
        foreach ($urls as $url) {
            $input['startUrls'][] = ['url' => $url];
        }
        $input['maxCrawlPages'] = $maxCrawlPages;

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
            throw new ApiException(
                502, 'apify_response_invalid', "Apify devolvió una respuesta inválida: {$response->body()}",
            );
        }

        foreach ($items as $item) {
            $isDatasetItemObject = $item instanceof stdClass;
            if (!$isDatasetItemObject) {
                $detail = json_encode($item);
                throw new ApiException(502, 'apify_response_invalid', "Apify devolvió un ítem inválido: {$detail}");
            }
        }

        return $response->json();
    }


    /** @return list<array{url: string, title: string, content: string, payload: array}> */
    public function getWebsitePages(string $datasetId, int $maxPages): array
    {
        $items = $this->getDatasetItems($datasetId, limit: $maxPages);
        $pages = [];
        foreach ($items as $item) {
            $validator = Validator::make($item, [
                'url' => ['required', 'string', 'url:http,https'],
                'text' => ['nullable', 'string'],
                'markdown' => ['nullable', 'string'],
                'metadata' => ['sometimes', 'array'],
                'metadata.title' => ['nullable', 'string'],
            ]);
            if ($validator->fails()) {
                $detail = implode(' ', $validator->errors()->all());
                throw new ApiException(
                    502, 'apify_content_invalid', "Apify devolvió contenido web inválido: {$detail}",
                );
            }

            $content = trim($item['markdown'] ?? '');
            if ($content === '') {
                $content = trim($item['text'] ?? '');
            }
            // El dataset puede contener archivos u otras páginas sin texto aprovechable.
            if ($content === '') {
                continue;
            }

            $pages[] = [
                'url' => $item['url'],
                'title' => $item['metadata']['title'] ?? $item['url'],
                'content' => $content,
                'payload' => $item,
            ];
        }

        return $pages;
    }


    private function getValidatedRun(Response $response): ApifyRunDto
    {
        $run = $response->json('data');
        $isRunArray = is_array($run);
        if (!$isRunArray) {
            throw new ApiException(
                502, 'apify_response_invalid', "Apify devolvió una respuesta inválida: {$response->body()}",
            );
        }

        $validator = Validator::make($run, [
            'id' => ['required', 'string'],
            'status' => ['required', 'string'],
            'defaultDatasetId' => ['nullable', 'string'],
        ]);
        $hasInvalidRunData = $validator->fails();
        if ($hasInvalidRunData) {
            $detail = implode(' ', $validator->errors()->all()).' Respuesta: '.$response->body();
            throw new ApiException(502, 'apify_response_invalid', "Apify devolvió una ejecución inválida: {$detail}");
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

        // No reintentar el inicio: una respuesta perdida podría duplicar una ejecución paga. Un error HTTP sube
        // como RequestException, con el estado y la respuesta completa.
        return Http::withToken($apiKey)
            ->acceptJson()
            ->withoutRedirecting()
            ->dontTruncateExceptions()
            ->throw()
            ->send($method, "https://api.apify.com/v2/{$path}", [$parameterType => $parameters]);
    }

}

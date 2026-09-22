<?php

namespace App\Helpers;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Client\ConnectionException;


class FirecrawlHelper
{


    // Devuelve el JSON original validado para conservar todos los campos del proveedor.
    public function scrapeWebsite(string $url): string
    {
        Validator::make(compact('url'), ['url' => ['required', 'url:http,https']])->validate();

        $apiKey = config('services.firecrawl.api_key');
        $hasApiKey = is_string($apiKey) && trim($apiKey) !== '';
        if (!$hasApiKey) {
            throw new ApiException(
                500, 'firecrawl_not_configured', 'La integración con Firecrawl no está configurada.',
            );
        }

        try {
            // No reintentar automáticamente una operación que consume créditos.
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->withoutRedirecting()
                ->timeout(120)
                ->post('https://api.firecrawl.dev/v2/scrape', [
                    'url' => $url,
                    'formats' => ['markdown', 'branding', 'images', 'links'],
                ]);
        } catch (ConnectionException $exception) {
            $detail = $exception->getMessage();
            throw new ApiException(
                502, 'firecrawl_unavailable', "No se pudo conectar con Firecrawl: {$detail}", $exception,
            );
        }

        if (!$response->successful()) {
            $error = $response->json('error');
            $providerMessage = is_string($error) ? $error : $response->body();
            throw new ApiException(
                502, 'firecrawl_request_failed', "Firecrawl respondió {$response->status()}: {$providerMessage}",
            );
        }

        $payload = $response->json();
        $isPayloadArray = is_array($payload);
        if (!$isPayloadArray) {
            throw new ApiException(
                502, 'firecrawl_response_invalid', "Firecrawl devolvió una respuesta inválida: {$response->body()}",
            );
        }

        $validator = Validator::make($payload, [
            'success' => ['required', 'boolean'],
            'data' => ['required', 'array'],
            'data.markdown' => ['present', 'string'],
            'data.branding' => ['sometimes', 'nullable', 'array'],
            'data.metadata' => ['required', 'array'],
            'data.metadata.url' => ['sometimes', 'nullable', 'url:http,https'],
            'data.metadata.sourceURL' => ['sometimes', 'nullable', 'url:http,https'],
            'data.metadata.statusCode' => ['sometimes', 'integer', 'between:100,599'],
            'data.images' => ['present', 'array'],
            'data.images.*' => ['string'],
            'data.links' => ['present', 'array', 'list'],
            'data.links.*' => ['string'],
        ]);
        $hasInvalidPayload = $validator->fails();
        $hasSucceeded = ($payload['success'] ?? null) === true;
        if ($hasInvalidPayload || !$hasSucceeded) {
            $error = $payload['error'] ?? null;
            $detail = is_string($error) ? $error : implode(' ', $validator->errors()->all());
            throw new ApiException(
                502, 'firecrawl_response_invalid', "Firecrawl devolvió una respuesta inválida: {$detail}",
            );
        }

        $pageStatusCode = $payload['data']['metadata']['statusCode'] ?? 200;
        $hasPageError = $pageStatusCode >= 400;
        if ($hasPageError) {
            throw new ApiException(
                502, 'firecrawl_page_failed', "El sitio devolvió un error {$pageStatusCode} al obtener la página.",
            );
        }

        return $response->body();
    }

}

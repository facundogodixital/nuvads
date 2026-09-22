<?php

namespace App\Helpers;

use JsonException;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Client\ConnectionException;


class DeepSeekHelper
{


    // model: ID del modelo; instructions: 'Resume la oferta.' (la tarea).
    // input: 'UP! vende insumos de jardinería...' (el contenido sobre el que se trabaja).
    public function generateText(
        string $model,
        string $instructions,
        string $input,
        ?int $maxOutputTokens = null,
    ): string {
        return $this->generateContent($model, $instructions, $input, 'text', $maxOutputTokens);
    }


    // Garantiza un objeto JSON válido; el service valida los campos propios de su operación.
    // model: ID del modelo; instructions: 'Extrae nombre y oferta en JSON.' (la tarea).
    // input: 'UP! vende insumos de jardinería...' (el contenido que se analiza).
    public function generateJson(
        string $model,
        string $instructions,
        string $input,
        ?int $maxOutputTokens = null,
    ): array {
        $content = $this->generateContent($model, $instructions, $input, 'json_object', $maxOutputTokens);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $detail = "{$exception->getMessage()}: {$content}";
            throw new ApiException(
                502, 'deepseek_json_invalid', "DeepSeek devolvió un JSON inválido. {$detail}", $exception,
            );
        }

        $isJsonObject = str_starts_with(ltrim($content), '{');
        if (!$isJsonObject) {
            throw new ApiException(502, 'deepseek_json_invalid', "DeepSeek no devolvió un objeto JSON: {$content}");
        }

        return $data;
    }


    private function generateContent(
        string $model,
        string $instructions,
        string $input,
        string $outputFormat,
        ?int $maxOutputTokens,
    ): string {
        Validator::make(compact('model', 'instructions', 'input', 'maxOutputTokens'), [
            'model' => ['required', 'string'],
            'input' => ['required', 'string'],
            'instructions' => ['required', 'string'],
            'maxOutputTokens' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $apiKey = config('services.deepseek.api_key');
        $hasApiKey = is_string($apiKey) && trim($apiKey) !== '';
        if (!$hasApiKey) {
            throw new ApiException(500, 'deepseek_not_configured', 'DeepSeek no está configurado.');
        }

        if ($outputFormat === 'json_object') {
            $instructions .= "\nDevuelve únicamente un objeto JSON válido, sin bloques Markdown.";
        }

        $payload = [
            'model' => $model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $instructions],
                ['role' => 'user', 'content' => $input],
            ],
            'response_format' => ['type' => $outputFormat],
        ];
        if ($maxOutputTokens !== null) {
            $payload['max_tokens'] = $maxOutputTokens;
        }

        try {
            // No reintentar automáticamente: una respuesta perdida puede duplicar el consumo.
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->withoutRedirecting()
                ->timeout(120)
                ->post('https://api.deepseek.com/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            throw new ApiException(
                502, 'deepseek_unavailable', "No se pudo conectar con DeepSeek: {$exception->getMessage()}", $exception,
            );
        }

        if (!$response->successful()) {
            $providerMessage = $response->json('error.message') ?? $response->body();
            throw new ApiException(
                502, 'deepseek_request_failed', "DeepSeek respondió {$response->status()}: {$providerMessage}",
            );
        }

        $result = $response->json();
        $isResultArray = is_array($result);
        if (!$isResultArray) {
            throw new ApiException(
                502, 'deepseek_response_invalid', "DeepSeek devolvió una respuesta inválida: {$response->body()}",
            );
        }

        $validator = Validator::make($result, [
            'choices' => ['required', 'array', 'list', 'size:1'],
            'choices.0.finish_reason' => ['required', 'string'],
            'choices.0.message' => ['required', 'array'],
            'choices.0.message.role' => ['required', 'in:assistant'],
            'choices.0.message.content' => ['present', 'nullable', 'string'],
            'choices.0.message.refusal' => ['sometimes', 'nullable', 'string'],
        ]);
        if ($validator->fails()) {
            $detail = implode(' ', $validator->errors()->all());
            throw new ApiException(
                502, 'deepseek_response_invalid', "DeepSeek devolvió una respuesta inválida: {$detail}",
            );
        }

        $choice = $result['choices'][0];
        $hasRefusal = !empty($choice['message']['refusal']);
        $isFiltered = $choice['finish_reason'] === 'content_filter';
        if ($hasRefusal || $isFiltered) {
            $detail = json_encode($choice);
            throw new ApiException(
                502, 'deepseek_response_refused', "DeepSeek rechazó generar la respuesta: {$detail}",
            );
        }
        if ($choice['finish_reason'] !== 'stop') {
            throw new ApiException(
                502, 'deepseek_response_incomplete', "DeepSeek no completó la respuesta: {$choice['finish_reason']}",
            );
        }

        $text = $choice['message']['content'];
        $hasText = is_string($text) && trim($text) !== '';
        if (!$hasText) {
            throw new ApiException(
                502, 'deepseek_response_invalid', "DeepSeek devolvió una respuesta vacía: {$response->body()}",
            );
        }

        return $text;
    }

}

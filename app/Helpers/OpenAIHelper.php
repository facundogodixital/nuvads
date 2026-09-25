<?php

namespace App\Helpers;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class OpenAIHelper
{


    /**
     * model: ID del modelo; instructions: 'Describe el estilo de la marca.' (la tarea).
     * input: 'UP! vende insumos de jardinería...' (el contenido sobre el que se trabaja).
     * imageUrls: ['https://ejemplo.com/logo.png']; URLs accesibles y un modelo con visión.
     *
     * @param  list<string>  $imageUrls
     */
    public function generateText(
        string $model,
        string $instructions,
        string $input,
        ?int $maxOutputTokens = null,
        array $imageUrls = [],
    ): string {
        return $this->generateContent($model, $instructions, $input, 'text', $maxOutputTokens, $imageUrls);
    }


    /**
     * model: ID del modelo; instructions: 'Extrae nombre y colores en JSON.' (la tarea).
     * input: 'UP! vende insumos de jardinería...' (el contenido que se analiza).
     * Garantiza un objeto JSON válido; el service valida sus campos. También admite imageUrls.
     *
     * @param  list<string>  $imageUrls
     */
    public function generateJson(
        string $model,
        string $instructions,
        string $input,
        ?int $maxOutputTokens = null,
        array $imageUrls = [],
    ): array {
        $content = $this->generateContent($model, $instructions, $input, 'json_object', $maxOutputTokens, $imageUrls);

        $data = json_decode($content, true);
        $isJsonObject = is_array($data) && str_starts_with(ltrim($content), '{');
        if (!$isJsonObject) {
            $detail = json_last_error_msg().": {$content}";
            throw new ApiException(502, 'openai_json_invalid', "OpenAI no devolvió un objeto JSON válido. {$detail}");
        }

        return $data;
    }


    /**
     * model: ID del modelo de transcripción, como gpt-transcribe. audioPath: ruta local del archivo. OpenAI reconoce
     * el formato por la extensión: mp3, mp4, m4a, wav o webm, hasta 25 MB. Devuelve el texto, que puede venir vacío
     * si el audio no tiene voz.
     */
    public function transcribeAudio(string $model, string $audioPath): string
    {
        $apiKey = config('services.openai.api_key');
        $hasApiKey = is_string($apiKey) && trim($apiKey) !== '';
        if (!$hasApiKey) {
            throw new ApiException(500, 'openai_not_configured', 'OpenAI no está configurado.');
        }

        // Como en generateContent(), sin reintentos. Un audio largo tarda más en transcribirse que una respuesta
        // de texto.
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->withoutRedirecting()
            ->timeout(300)
            ->dontTruncateExceptions()
            ->throw()
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => $model]);

        $text = $response->json('text');
        if (!is_string($text)) {
            throw new ApiException(
                502, 'openai_response_invalid', "OpenAI devolvió una transcripción inválida: {$response->body()}",
            );
        }

        return $text;
    }


    private function generateContent(
        string $model,
        string $instructions,
        string $input,
        string $outputFormat,
        ?int $maxOutputTokens,
        array $imageUrls,
    ): string {
        Validator::make(compact('model', 'instructions', 'input', 'maxOutputTokens', 'imageUrls'), [
            'model' => ['required', 'string'],
            'input' => ['required', 'string'],
            'instructions' => ['required', 'string'],
            'imageUrls' => ['array', 'list'],
            'imageUrls.*' => ['required', 'string', 'url:http,https'],
            'maxOutputTokens' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $apiKey = config('services.openai.api_key');
        $hasApiKey = is_string($apiKey) && trim($apiKey) !== '';
        if (!$hasApiKey) {
            throw new ApiException(500, 'openai_not_configured', 'OpenAI no está configurado.');
        }

        if ($outputFormat === 'json_object') {
            // OpenAI exige la palabra JSON dentro del input para aceptar json_object; en instructions no alcanza.
            $jsonReminder = "\nDevuelve únicamente un objeto JSON válido, sin bloques Markdown.";
            $instructions .= $jsonReminder;
            $input .= $jsonReminder;
        }

        $payload = [
            'model' => $model,
            'input' => $input,
            'store' => false,
            'instructions' => $instructions,
            'text' => ['format' => ['type' => $outputFormat]],
        ];
        if ($imageUrls !== []) {
            $content = [['type' => 'input_text', 'text' => $input]];
            foreach ($imageUrls as $imageUrl) {
                $content[] = ['type' => 'input_image', 'image_url' => $imageUrl];
            }
            $payload['input'] = [['role' => 'user', 'content' => $content]];
        }
        if ($maxOutputTokens !== null) {
            $payload['max_output_tokens'] = $maxOutputTokens;
        }

        // No reintentar automáticamente: una respuesta perdida puede duplicar el consumo. Un error HTTP sube como
        // RequestException, con el estado y la respuesta completa.
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->withoutRedirecting()
            ->timeout(120)
            ->dontTruncateExceptions()
            ->throw()
            ->post('https://api.openai.com/v1/responses', $payload);

        $result = $response->json();
        $isResultArray = is_array($result);
        if (!$isResultArray) {
            throw new ApiException(
                502, 'openai_response_invalid', "OpenAI devolvió una respuesta inválida: {$response->body()}",
            );
        }

        $validator = Validator::make($result, [
            'status' => ['required', 'string'],
            'output' => ['present', 'array', 'list'],
            'output.*' => ['array'],
            'output.*.type' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            $detail = implode(' ', $validator->errors()->all());
            throw new ApiException(
                502, 'openai_response_invalid', "OpenAI devolvió una respuesta inválida: {$detail}",
            );
        }
        if ($result['status'] !== 'completed') {
            $detail = json_encode($result['incomplete_details'] ?? $result['error'] ?? null);
            throw new ApiException(
                502, 'openai_response_incomplete', "OpenAI no completó la respuesta ({$result['status']}): {$detail}",
            );
        }

        $text = '';
        foreach ($result['output'] as $item) {
            // Los modelos de razonamiento pueden incluir elementos previos al mensaje final.
            if ($item['type'] !== 'message') {
                continue;
            }

            $messageValidator = Validator::make($item, [
                'role' => ['required', 'in:assistant'],
                'status' => ['required', 'in:completed'],
                'content' => ['required', 'array', 'list'],
                'content.*' => ['array'],
                'content.*.type' => ['required', 'in:output_text,refusal'],
                'content.*.text' => ['sometimes', 'string'],
            ]);
            if ($messageValidator->fails()) {
                $detail = implode(' ', $messageValidator->errors()->all());
                throw new ApiException(
                    502, 'openai_response_invalid', "OpenAI devolvió un mensaje inválido: {$detail}",
                );
            }

            foreach ($item['content'] as $part) {
                if ($part['type'] === 'refusal') {
                    $refusal = $part['refusal'] ?? json_encode($part);
                    throw new ApiException(
                        502, 'openai_response_refused', "OpenAI rechazó generar la respuesta: {$refusal}",
                    );
                }
                $hasText = isset($part['text']);
                if (!$hasText) {
                    $detail = json_encode($part);
                    throw new ApiException(
                        502, 'openai_response_invalid', "OpenAI devolvió un mensaje sin texto: {$detail}",
                    );
                }
                $text .= $part['text'];
            }
        }

        if (trim($text) === '') {
            throw new ApiException(
                502, 'openai_response_invalid', "OpenAI devolvió una respuesta vacía: {$response->body()}",
            );
        }

        return $text;
    }

}

<?php

namespace App\Helpers;

use Exception;
use ZipArchive;
use App\DTO\WhatsAppConversationDto;


class WhatsAppConversationsZipHelper
{


    // Lee el zip que arma la extensión de WhatsApp: un .txt por conversación, con un encabezado (# Teléfono,
    // # Contacto, # Mensajes) y después un mensaje por línea, como "[2026-06-29 13:52] Yo: Hola". Devuelve una lista de
    // WhatsAppConversationDto, en el orden del zip. Los archivos que no son .txt se ignoran.
    public function readConversations(string $zipFilePath): array
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipFilePath, ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new Exception("No se pudo abrir el zip {$zipFilePath}. Código de ZipArchive: {$openResult}.");
        }

        $conversations = [];
        for ($fileIndex = 0; $fileIndex < $zip->numFiles; $fileIndex++) {
            $fileName = $zip->getNameIndex($fileIndex);
            $isTextFile = str_ends_with(strtolower($fileName), '.txt');
            if (!$isTextFile) {
                continue;
            }

            $fileText = $zip->getFromIndex($fileIndex);
            if ($fileText === false) {
                throw new Exception("No se pudo leer {$fileName} del zip {$zipFilePath}: {$zip->getStatusString()}.");
            }
            $conversations[] = $this->parseConversation($fileName, $fileText);
        }
        $zip->close();

        return $conversations;
    }


    // Antes del primer mensaje está el encabezado. Después, una línea sin fecha es la continuación del mensaje
    // anterior, porque un mensaje de varias líneas sigue en las líneas siguientes. Si falta el encabezado, el teléfono
    // sale del nombre del archivo, y el contacto es el teléfono.
    private function parseConversation(string $fileName, string $fileText): WhatsAppConversationDto
    {
        $phone = pathinfo($fileName, PATHINFO_FILENAME);
        $contactName = null;
        $messages = [];

        // Saltos de línea explícitos: \R sin el modificador u toma como salto el byte 0x85, que aparece dentro de
        // caracteres como 😅, y los corta a la mitad.
        foreach (preg_split('/\r\n|\r|\n/', $fileText) as $line) {
            $messagePattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\] (.+?): ?(.*)$/u';
            $isMessageLine = preg_match($messagePattern, $line, $messageMatch) === 1;
            if ($isMessageLine) {
                $messages[] = [
                    'sent_at' => $messageMatch[1],
                    // "Yo" es siempre el dueño del teléfono; cualquier otro autor es el contacto.
                    'is_from_owner' => $messageMatch[2] === 'Yo',
                    'text' => $messageMatch[3],
                ];
                continue;
            }

            $isHeaderLine = $messages === [];
            if ($isHeaderLine) {
                $isPhoneLine = preg_match('/^# Teléfono: (.+)$/u', $line, $phoneMatch) === 1;
                $isContactLine = preg_match('/^# Contacto: (.+)$/u', $line, $contactMatch) === 1;
                if ($isPhoneLine) {
                    $phone = trim($phoneMatch[1]);
                }
                if ($isContactLine) {
                    $contactName = trim($contactMatch[1]);
                }
                continue;
            }

            $lastMessageIndex = array_key_last($messages);
            $messages[$lastMessageIndex]['text'] .= "\n{$line}";
        }

        // Quita los espacios y las líneas vacías de los bordes, como la línea vacía del final del archivo.
        $messages = array_map(fn (array $message): array => [...$message, 'text' => trim($message['text'])], $messages);

        return new WhatsAppConversationDto(
            phone: $phone,
            contactName: $contactName ?? $phone,
            messages: $messages,
        );
    }

}

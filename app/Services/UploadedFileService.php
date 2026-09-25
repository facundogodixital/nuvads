<?php

namespace App\Services;

use Throwable;
use App\Models\Brand;
use App\Models\ResearchRun;
use Illuminate\Support\Str;
use App\Models\KnowledgeSource;
use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\MimeTypes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class UploadedFileService
{

    // Formatos que acepta OpenAI: las fotos van como imagen y el resto como documento.
    const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    const array DOCUMENT_EXTENSIONS = [
        'md',
        'pdf',
        'doc',
        'odt',
        'rtf',
        'txt',
        'csv',
        'xls',
        'ppt',
        'docx',
        'xlsx',
        'pptx',
    ];


    // Guarda el archivo en el disco local y lo registra como fuente pendiente de la marca: image si es una foto,
    // document si no. El job lo analiza y completa su payload.
    public function create(Brand $brand, UploadedFile $uploadedFile): KnowledgeSource
    {
        $fileName = $uploadedFile->getClientOriginalName();
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);
        // El tipo sale de la extensión: el que detecta PHP por el contenido puede ser application/zip en un .docx.
        $mimeType = MimeTypes::getDefault()->getMimeTypes($extension)[0];

        // Nombre aleatorio con la extensión original, para que el archivo se sirva con su tipo.
        $storedFileName = Str::random(40).".{$extension}";
        $storedPath = $uploadedFile->storeAs("uploaded-files/{$brand->id}", $storedFileName, 'local');
        try {
            return resolve(KnowledgeSourceService::class)->create($brand, [
                'status' => 'pending',
                'captured_at' => now(),
                // Por ahora el archivo vive en el disco local; la columna ya prevé S3.
                's3_path' => $storedPath,
                'type' => $isImage ? 'image' : 'document',
                'title' => Str::limit($fileName, 255, ''),
                'payload' => [
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'size' => $uploadedFile->getSize(),
                ],
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }
    }


    // Los archivos subidos de la marca, del más nuevo al más viejo, cada uno con url, el enlace para verlo.
    public function list(Brand $brand): Collection
    {
        $knowledgeSources = resolve(KnowledgeSourceService::class)->findByTypes($brand, ['image', 'document']);

        return $knowledgeSources->sortByDesc('id')->values()->each(function (KnowledgeSource $knowledgeSource): void {
            // url no es una columna: solo viaja en la respuesta.
            $knowledgeSource->setAttribute('url', $this->getUrl($knowledgeSource));
        });
    }


    // Borra el archivo y su fuente, y pide un nuevo análisis de los que quedan, que no toca la marca. No se puede
    // mientras se analiza otra subida o otro borrado.
    public function delete(Brand $brand, int $knowledgeSourceId): ResearchRun
    {
        $researchRunService = resolve(ResearchRunService::class);
        $activeResearchRun = $researchRunService->findOneActiveForBrand($brand, 'uploaded_files');
        if ($activeResearchRun !== null) {
            throw new ApiException(
                409, 'research_already_running', 'Espera a que termine el análisis de tus archivos para borrar uno.',
            );
        }
        $knowledgeSourceService = resolve(KnowledgeSourceService::class);
        $knowledgeSource = $knowledgeSourceService->find($brand, $knowledgeSourceId);
        $isUploadedFile = in_array($knowledgeSource?->type, ['image', 'document'], true);
        if (!$isUploadedFile) {
            throw (new ModelNotFoundException())->setModel(KnowledgeSource::class, [$knowledgeSourceId]);
        }

        DB::beginTransaction();
        try {
            $knowledgeSourceService->delete($brand, $knowledgeSource->id);
            $researchRun = $researchRunService->create($brand, ['type' => 'uploaded_files']);
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
        // El archivo se borra recién cuando el borrado de la fuente quedó firme.
        Storage::disk('local')->delete($knowledgeSource->s3_path);

        return $researchRun;
    }


    // El archivo en base64, como lo recibe OpenAI: 'data:image/png;base64,...'. Cuando los archivos estén en S3, va
    // a alcanzar con un enlace temporal.
    public function getDataUrl(KnowledgeSource $knowledgeSource): string
    {
        $fileContents = Storage::disk('local')->get($knowledgeSource->s3_path);

        return "data:{$knowledgeSource->payload['mime_type']};base64,".base64_encode($fileContents);
    }


    // Enlace firmado para ver el archivo sin el token de la API. Por ahora no vence. La firma se calcula sobre la ruta
    // relativa, porque así la valida Laravel al servir el disco local.
    private function getUrl(KnowledgeSource $knowledgeSource): string
    {
        $signedPath = URL::signedRoute('storage.local', ['path' => $knowledgeSource->s3_path], absolute: false);

        return URL::to($signedPath);
    }

}

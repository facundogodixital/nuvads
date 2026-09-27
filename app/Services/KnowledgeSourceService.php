<?php

namespace App\Services;

use Throwable;
use App\Models\Brand;
use App\Helpers\S3Helper;
use Illuminate\Support\Str;
use App\Models\KnowledgeSource;
use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\MimeTypes;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\KnowledgeSourceRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class KnowledgeSourceService
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

    private KnowledgeSourceRepository $knowledgeSourceRepository;


    public function __construct(KnowledgeSourceRepository $knowledgeSourceRepository)
    {
        $this->knowledgeSourceRepository = $knowledgeSourceRepository;
    }


    public function create(Brand $brand, array $attributes): KnowledgeSource
    {
        return $this->knowledgeSourceRepository->create($brand, $attributes);
    }


    // Registra el archivo que subió el usuario como fuente pendiente de la marca, image si es una foto y document si
    // no, y lo sube a S3 con el id de la fuente en el nombre. El job lo analiza y completa su payload. Se llama dentro
    // de la transacción de ResearchRunService::create: si la subida falla, la fuente se deshace con ella.
    public function createFromUploadedFile(Brand $brand, UploadedFile $uploadedFile): KnowledgeSource
    {
        $fileName = $uploadedFile->getClientOriginalName();
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);
        $type = $isImage ? 'image' : 'document';
        // El tipo sale de la extensión: el que detecta PHP por el contenido puede ser application/zip en un .docx.
        $mimeType = MimeTypes::getDefault()->getMimeTypes($extension)[0];

        $knowledgeSource = $this->knowledgeSourceRepository->create($brand, [
            'type' => $type,
            'status' => 'pending',
            'captured_at' => now(),
            'title' => Str::limit($fileName, 255, ''),
            'payload' => [
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'size' => $uploadedFile->getSize(),
            ],
        ]);

        // La extensión original hace que el archivo se sirva con su tipo.
        $fileS3Path = "{$brand->id}/sources/{$type}/{$knowledgeSource->id}.{$extension}";
        $s3Helper = resolve(S3Helper::class);
        $s3Helper->storeUploadedFile($uploadedFile, $fileS3Path);
        try {
            return $this->knowledgeSourceRepository->update(
                $brand, $knowledgeSource->id, ['file_s3_path' => $fileS3Path],
            );
        } catch (Throwable $exception) {
            $s3Helper->delete($fileS3Path);
            throw $exception;
        }
    }


    public function update(Brand $brand, int $knowledgeSourceId, array $attributes): KnowledgeSource
    {
        return $this->knowledgeSourceRepository->update($brand, $knowledgeSourceId, $attributes);
    }


    public function find(Brand $brand, int $knowledgeSourceId): ?KnowledgeSource
    {
        return $this->knowledgeSourceRepository->find($brand, $knowledgeSourceId);
    }


    public function list(Brand $brand): Collection
    {
        return $this->knowledgeSourceRepository->list($brand);
    }


    // Solo se borran las fotos y los documentos que subió el usuario. Sin el archivo, el análisis de los que quedan se
    // rehace sin tocar la marca: si ya hay uno en curso, ResearchRunService lo rechaza y el borrado se deshace.
    public function delete(Brand $brand, int $knowledgeSourceId): bool
    {
        $knowledgeSource = $this->knowledgeSourceRepository->find($brand, $knowledgeSourceId);
        if ($knowledgeSource === null) {
            throw (new ModelNotFoundException())->setModel(KnowledgeSource::class, [$knowledgeSourceId]);
        }
        $wasUploadedByUser = in_array($knowledgeSource->type, ['image', 'document'], true);
        if (!$wasUploadedByUser) {
            throw new ApiException(409, 'knowledge_source_not_deletable', 'Solo se pueden borrar fotos y documentos.');
        }

        DB::beginTransaction();
        try {
            $isDeleted = $this->knowledgeSourceRepository->delete($brand, $knowledgeSource->id);
            resolve(ResearchRunService::class)->create($brand, ['type' => 'uploaded_files']);
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
        // El archivo se borra recién cuando el borrado de la fuente quedó firme.
        resolve(S3Helper::class)->delete($knowledgeSource->file_s3_path);

        return $isDeleted;
    }


    public function findByIds(Brand $brand, array $knowledgeSourceIds): Collection
    {
        return $this->knowledgeSourceRepository->findByIds($brand, $knowledgeSourceIds);
    }


    public function findByTypes(Brand $brand, array $types): Collection
    {
        return $this->knowledgeSourceRepository->findByTypes($brand, $types);
    }


    // Borra las fuentes de un tipo de la marca, salvo las indicadas. Devuelve cuántas borró.
    public function deleteByTypeExceptIds(Brand $brand, string $type, array $keptKnowledgeSourceIds): int
    {
        return $this->knowledgeSourceRepository->deleteByTypeExceptIds($brand, $type, $keptKnowledgeSourceIds);
    }

}

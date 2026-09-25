<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\UploadedFileService;
use App\Http\Requests\UploadedFiles\DeleteUploadedFileRequest;


class UploadedFileController extends ApiController
{


    // Devuelve el nuevo análisis de los archivos que quedan, para que la pantalla siga su estado.
    public function delete(DeleteUploadedFileRequest $request, int $knowledgeSourceId): JsonResponse
    {
        $researchRun = resolve(UploadedFileService::class)->delete($request->brand, $knowledgeSourceId);
        return $this->respond($researchRun);
    }

}

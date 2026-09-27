<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\KnowledgeSourceService;
use App\Http\Requests\AuthenticatedRequest;


class KnowledgeSourceController extends ApiController
{


    public function delete(AuthenticatedRequest $request, int $knowledgeSourceId): JsonResponse
    {
        resolve(KnowledgeSourceService::class)->delete($request->brand, $knowledgeSourceId);
        return $this->respond();
    }

}

<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\ContentTypeService;
use App\Http\Requests\AuthenticatedRequest;


class ContentTypeController extends ApiController
{


    public function list(AuthenticatedRequest $request): JsonResponse
    {
        $contentTypes = resolve(ContentTypeService::class)->listWithAvailability($request->brand);
        return $this->respond($contentTypes);
    }

}

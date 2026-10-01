<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\ContentTypeService;


class ContentTypeController extends ApiController
{


    public function list(): JsonResponse
    {
        $contentTypes = resolve(ContentTypeService::class)->list();
        return $this->respond($contentTypes);
    }

}

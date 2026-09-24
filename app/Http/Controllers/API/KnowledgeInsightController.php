<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\KnowledgeInsightService;
use App\Http\Requests\AuthenticatedRequest;


class KnowledgeInsightController extends ApiController
{


    public function getWebsiteInsights(AuthenticatedRequest $request): JsonResponse
    {
        $websiteInsights = resolve(KnowledgeInsightService::class)->getWebsiteInsights($request->brand);

        return $this->respond($websiteInsights);
    }


    public function getInstagramInsights(AuthenticatedRequest $request): JsonResponse
    {
        $instagramInsights = resolve(KnowledgeInsightService::class)->getInstagramInsights($request->brand);

        return $this->respond($instagramInsights);
    }

}

<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\KnowledgeInsightService;
use App\Http\Requests\KnowledgeInsights\ListKnowledgeInsightsRequest;


class KnowledgeInsightController extends ApiController
{


    public function list(ListKnowledgeInsightsRequest $request): JsonResponse
    {
        $types = $request->validated('types');
        $knowledgeInsights = resolve(KnowledgeInsightService::class)->findCurrentByTypes($request->brand, $types);

        return $this->respond($knowledgeInsights);
    }

}

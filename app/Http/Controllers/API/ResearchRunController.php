<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\ResearchRunService;
use App\Http\Requests\AuthenticatedRequest;
use App\Http\Requests\ResearchRuns\CreateResearchRunRequest;


class ResearchRunController extends ApiController
{


    public function create(CreateResearchRunRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $researchRun = resolve(ResearchRunService::class)->create($request->brand, $attributes);

        return $this->respond($researchRun, 201);
    }


    public function find(AuthenticatedRequest $request, int $researchRunId): JsonResponse
    {
        $researchRun = resolve(ResearchRunService::class)->findForBrand($request->brand, $researchRunId);

        return $this->respond($researchRun);
    }


    public function getWebsiteStatus(AuthenticatedRequest $request): JsonResponse
    {
        $status = resolve(ResearchRunService::class)->getWebsiteStatus($request->brand);

        return $this->respond($status);
    }

}

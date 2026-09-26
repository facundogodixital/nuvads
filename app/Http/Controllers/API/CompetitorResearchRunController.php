<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\CompetitorService;
use App\Http\Requests\AuthenticatedRequest;
use App\Services\CompetitorResearchRunService;
use App\Http\Requests\Competitors\CreateCompetitorResearchRunRequest;


class CompetitorResearchRunController extends ApiController
{


    public function create(CreateCompetitorResearchRunRequest $request, int $competitorId): JsonResponse
    {
        $type = $request->validated('type');
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $researchRun = resolve(CompetitorResearchRunService::class)->create($competitor, $type);
        return $this->respond($researchRun, 201);
    }


    public function getWebsiteResearchStatus(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $status = resolve(CompetitorResearchRunService::class)->getResearchStatus($competitor, 'website');
        return $this->respond($status);
    }


    public function getInstagramResearchStatus(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $status = resolve(CompetitorResearchRunService::class)->getResearchStatus($competitor, 'instagram');
        return $this->respond($status);
    }


    public function getMetaAdsResearchStatus(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $status = resolve(CompetitorResearchRunService::class)->getResearchStatus($competitor, 'meta_ads');
        return $this->respond($status);
    }


    public function getGoogleReviewsResearchStatus(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $status = resolve(CompetitorResearchRunService::class)->getResearchStatus($competitor, 'google_reviews');
        return $this->respond($status);
    }

}

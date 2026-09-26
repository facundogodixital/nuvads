<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\CompetitorService;
use App\Services\CompetitorInsightService;
use App\Http\Requests\AuthenticatedRequest;


class CompetitorInsightController extends ApiController
{


    public function getWebsiteInsights(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $websiteInsights = resolve(CompetitorInsightService::class)->getWebsiteInsights($competitor);
        return $this->respond($websiteInsights);
    }


    public function getInstagramInsights(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $instagramInsights = resolve(CompetitorInsightService::class)->getInstagramInsights($competitor);
        return $this->respond($instagramInsights);
    }


    public function getMetaAdsInsights(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $metaAdsInsights = resolve(CompetitorInsightService::class)->getMetaAdsInsights($competitor);
        return $this->respond($metaAdsInsights);
    }


    public function getGoogleReviewsInsights(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $googleReviewsInsights = resolve(CompetitorInsightService::class)->getGoogleReviewsInsights($competitor);
        return $this->respond($googleReviewsInsights);
    }

}

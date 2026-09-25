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


    public function getMetaAdsInsights(AuthenticatedRequest $request): JsonResponse
    {
        $metaAdsInsights = resolve(KnowledgeInsightService::class)->getMetaAdsInsights($request->brand);
        return $this->respond($metaAdsInsights);
    }


    public function getGoogleReviewsInsights(AuthenticatedRequest $request): JsonResponse
    {
        $googleReviewsInsights = resolve(KnowledgeInsightService::class)->getGoogleReviewsInsights($request->brand);
        return $this->respond($googleReviewsInsights);
    }


    public function getWhatsAppConversationsInsights(AuthenticatedRequest $request): JsonResponse
    {
        $whatsAppConversationsInsights = resolve(KnowledgeInsightService::class)->getWhatsAppConversationsInsights(
            $request->brand,
        );
        return $this->respond($whatsAppConversationsInsights);
    }

}

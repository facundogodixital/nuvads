<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\SessionController;
use App\Http\Controllers\API\CompetitorController;
use App\Http\Controllers\API\ResearchRunController;
use App\Http\Controllers\API\UploadedFileController;
use App\Http\Middleware\ResolveClientContextMiddleware;
use App\Http\Controllers\API\KnowledgeInsightController;
use App\Http\Controllers\API\CompetitorInsightController;
use App\Http\Middleware\AuthenticateAccessTokenMiddleware;
use App\Http\Controllers\API\CompetitorResearchRunController;

Route::post('auth/exchange', [SessionController::class, 'create'])->middleware('throttle:10,1');

Route::middleware([AuthenticateAccessTokenMiddleware::class, ResolveClientContextMiddleware::class])
    ->group(function (): void {
        Route::post('research-runs', [ResearchRunController::class, 'create']);
        Route::get('research-runs/website/status', [ResearchRunController::class, 'getWebsiteResearchStatus']);
        Route::get('research-runs/instagram/status', [ResearchRunController::class, 'getInstagramResearchStatus']);
        Route::get('research-runs/meta-ads/status', [ResearchRunController::class, 'getMetaAdsResearchStatus']);
        Route::get(
            'research-runs/google-reviews/status', [ResearchRunController::class, 'getGoogleReviewsResearchStatus'],
        );
        Route::get(
            'research-runs/whatsapp-conversations/status',
            [ResearchRunController::class, 'getWhatsAppConversationsResearchStatus'],
        );
        Route::get('research-runs/audio/status', [ResearchRunController::class, 'getAudioResearchStatus']);
        Route::get(
            'research-runs/uploaded-files/status', [ResearchRunController::class, 'getUploadedFilesResearchStatus'],
        );
        Route::get('research-runs/{researchRunId}', [ResearchRunController::class, 'find'])
            ->whereNumber('researchRunId');

        Route::get('knowledge-insights/website', [KnowledgeInsightController::class, 'getWebsiteInsights']);
        Route::get('knowledge-insights/instagram', [KnowledgeInsightController::class, 'getInstagramInsights']);
        Route::get('knowledge-insights/meta-ads', [KnowledgeInsightController::class, 'getMetaAdsInsights']);
        Route::get(
            'knowledge-insights/google-reviews', [KnowledgeInsightController::class, 'getGoogleReviewsInsights'],
        );
        Route::get(
            'knowledge-insights/whatsapp-conversations',
            [KnowledgeInsightController::class, 'getWhatsAppConversationsInsights'],
        );
        Route::get('knowledge-insights/audio', [KnowledgeInsightController::class, 'getAudioInsights']);
        Route::get(
            'knowledge-insights/uploaded-files', [KnowledgeInsightController::class, 'getUploadedFilesInsights'],
        );

        Route::delete('uploaded-files/{knowledgeSourceId}', [UploadedFileController::class, 'delete'])
            ->whereNumber('knowledgeSourceId');

        Route::get('brand', [BrandController::class, 'find']);
        Route::patch('brand', [BrandController::class, 'update']);

        Route::get('competitors', [CompetitorController::class, 'list']);
        Route::post('competitors', [CompetitorController::class, 'create']);
        Route::get('competitors/{competitorId}', [CompetitorController::class, 'find'])->whereNumber('competitorId');
        Route::patch('competitors/{competitorId}', [CompetitorController::class, 'update'])
            ->whereNumber('competitorId');
        Route::delete('competitors/{competitorId}', [CompetitorController::class, 'delete'])
            ->whereNumber('competitorId');

        Route::post('competitors/{competitorId}/research-runs', [CompetitorResearchRunController::class, 'create'])
            ->whereNumber('competitorId');
        Route::get(
            'competitors/{competitorId}/research-runs/website/status',
            [CompetitorResearchRunController::class, 'getWebsiteResearchStatus'],
        )->whereNumber('competitorId');
        Route::get(
            'competitors/{competitorId}/research-runs/instagram/status',
            [CompetitorResearchRunController::class, 'getInstagramResearchStatus'],
        )->whereNumber('competitorId');
        Route::get(
            'competitors/{competitorId}/research-runs/meta-ads/status',
            [CompetitorResearchRunController::class, 'getMetaAdsResearchStatus'],
        )->whereNumber('competitorId');
        Route::get(
            'competitors/{competitorId}/research-runs/google-reviews/status',
            [CompetitorResearchRunController::class, 'getGoogleReviewsResearchStatus'],
        )->whereNumber('competitorId');

        Route::get(
            'competitors/{competitorId}/insights/website', [CompetitorInsightController::class, 'getWebsiteInsights'],
        )->whereNumber('competitorId');
        Route::get(
            'competitors/{competitorId}/insights/instagram',
            [CompetitorInsightController::class, 'getInstagramInsights'],
        )->whereNumber('competitorId');
        Route::get(
            'competitors/{competitorId}/insights/meta-ads', [CompetitorInsightController::class, 'getMetaAdsInsights'],
        )->whereNumber('competitorId');
        Route::get(
            'competitors/{competitorId}/insights/google-reviews',
            [CompetitorInsightController::class, 'getGoogleReviewsInsights'],
        )->whereNumber('competitorId');

        Route::get('auth/me', [SessionController::class, 'find']);
        Route::post('auth/logout', [SessionController::class, 'delete']);
    });

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\SessionController;
use App\Http\Controllers\API\ResearchRunController;
use App\Http\Middleware\ResolveClientContextMiddleware;
use App\Http\Controllers\API\KnowledgeInsightController;
use App\Http\Middleware\AuthenticateAccessTokenMiddleware;

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

        Route::get('brand', [BrandController::class, 'find']);
        Route::patch('brand', [BrandController::class, 'update']);

        Route::get('auth/me', [SessionController::class, 'find']);
        Route::post('auth/logout', [SessionController::class, 'delete']);
    });

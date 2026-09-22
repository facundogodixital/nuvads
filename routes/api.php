<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\BrandController;
use App\Http\Controllers\API\SessionController;
use App\Http\Controllers\API\ResearchRunController;
use App\Http\Middleware\ResolveClientContextMiddleware;
use App\Http\Middleware\AuthenticateAccessTokenMiddleware;

Route::post('auth/exchange', [SessionController::class, 'create'])->middleware('throttle:10,1');

Route::middleware([AuthenticateAccessTokenMiddleware::class, ResolveClientContextMiddleware::class])
    ->group(function (): void {
        Route::post('research-runs', [ResearchRunController::class, 'create']);
        Route::get('research-runs/website/status', [ResearchRunController::class, 'getWebsiteStatus']);
        Route::get('research-runs/{researchRunId}', [ResearchRunController::class, 'find'])
            ->whereNumber('researchRunId');

        Route::get('brand', [BrandController::class, 'find']);
        Route::patch('brand', [BrandController::class, 'update']);

        Route::get('auth/me', [SessionController::class, 'find']);
        Route::post('auth/logout', [SessionController::class, 'delete']);
    });

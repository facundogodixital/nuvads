<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveClientContext;
use App\Http\Controllers\API\SessionController;
use App\Http\Middleware\AuthenticateAccessToken;

Route::post('auth/exchange', [SessionController::class, 'create'])->middleware('throttle:10,1');

Route::middleware([AuthenticateAccessToken::class, ResolveClientContext::class])->group(function (): void {
    Route::get('auth/me', [SessionController::class, 'find']);
    Route::post('auth/logout', [SessionController::class, 'delete']);
});

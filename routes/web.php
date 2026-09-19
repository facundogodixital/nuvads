<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\HandleGoogleAuthErrors;
use App\Http\Controllers\Auth\GoogleAuthController;

Route::prefix('auth/google')->middleware(HandleGoogleAuthErrors::class)->group(function (): void {
    Route::get('redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('callback', [GoogleAuthController::class, 'callback']);
});

// Las rutas de la aplicación entregan Vue; los endpoints desconocidos conservan su 404.
Route::view('/{path?}', 'app')->where('path', '(?!(?:api|auth)(?:/|$)).*')->fallback();

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\GoogleAuthController;

Route::get('/', [SessionController::class, 'find'])->name('home');

Route::prefix('auth')->group(function (): void {
    Route::middleware('guest:web')->group(function (): void {
        Route::get('google/redirect', [GoogleAuthController::class, 'redirect']);
        Route::get('google/callback', [GoogleAuthController::class, 'callback']);
    });

    Route::post('logout', [SessionController::class, 'delete'])->middleware('auth:web');
});

<?php

use App\Http\Controllers\OnboardingApiController;
use App\Http\Controllers\PayPalOAuthController;
use Illuminate\Support\Facades\Route;

// Throttle raised — browser retries + multi-tab were hitting 429 at 10/min
Route::get('/oauth/paypal/redirect', [PayPalOAuthController::class, 'redirect'])
    ->middleware(['auth:merchant_web', 'throttle:60,1,onboarding_paypal9'])
    ->name('oauth.paypal.redirect');

Route::get('/oauth/paypal/callback', [PayPalOAuthController::class, 'callback'])
    ->middleware(['auth:merchant_web', 'throttle:60,1,onboarding_paypal13'])
    ->name('oauth.paypal.callback');

Route::post('/oauth/paypal/sandbox-connect', [PayPalOAuthController::class, 'sandboxConnect'])
    ->middleware(['auth:merchant_web', 'throttle:30,1,onboarding_paypal17'])
    ->name('oauth.paypal.sandbox-connect');

Route::prefix('/onboarding/api')->middleware(['auth:merchant_web', 'throttle:60,1,onboarding_paypal20'])->group(function () {
    Route::get('/status', [OnboardingApiController::class, 'status'])->name('onboarding.api.status');
    Route::post('/project', [OnboardingApiController::class, 'createProject'])->name('onboarding.api.project');
    Route::post('/keys', [OnboardingApiController::class, 'issueKeys'])->name('onboarding.api.keys');
    Route::post('/complete', [OnboardingApiController::class, 'complete'])->name('onboarding.api.complete');
});

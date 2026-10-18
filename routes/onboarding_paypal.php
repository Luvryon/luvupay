<?php

use App\Http\Controllers\OnboardingApiController;
use App\Http\Controllers\PayPalOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/oauth/paypal/redirect', [PayPalOAuthController::class, 'redirect'])
    ->middleware('throttle:10,1')
    ->name('oauth.paypal.redirect');

Route::get('/oauth/paypal/callback', [PayPalOAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('oauth.paypal.callback');

Route::post('/oauth/paypal/sandbox-connect', [PayPalOAuthController::class, 'sandboxConnect'])
    ->middleware('throttle:5,1')
    ->name('oauth.paypal.sandbox-connect');

Route::prefix('/onboarding/api')->middleware('throttle:20,1')->group(function () {
    Route::get('/status', [OnboardingApiController::class, 'status'])->name('onboarding.api.status');
    Route::post('/project', [OnboardingApiController::class, 'createProject'])->name('onboarding.api.project');
    Route::post('/keys', [OnboardingApiController::class, 'issueKeys'])->name('onboarding.api.keys');
    Route::post('/complete', [OnboardingApiController::class, 'complete'])->name('onboarding.api.complete');
});

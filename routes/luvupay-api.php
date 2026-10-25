<?php

use App\Http\Controllers\Api\V1\{AIController, EntitlementController, MerchantExportController, ResaleController, SecondChanceController, SubscriptionController};
use App\Http\Controllers\SnippetController;
use Illuminate\Support\Facades\Route;


Route::get('/v1/badge.js', [SnippetController::class, 'badge'])->middleware('throttle:600,1,luvupay-api8')->name('badge.js');

// Order matters: authenticate first, then the per-merchant limiter can key on the merchant.
Route::prefix('v1')->middleware(['merchant.auth', 'api.usage', 'throttle:merchant-api'])->group(function () {
    Route::get('snippet', [SnippetController::class, 'embed'])->middleware('scope:snippet:read');

    Route::post('subscriptions', [SubscriptionController::class, 'store'])->middleware(['scope:subscriptions:write', 'idempotent']);
    Route::post('transactions/{transaction}/second-chance', [SecondChanceController::class, 'store'])->middleware(['scope:second-chance:write', 'idempotent']);

    Route::get('resale-listings', [ResaleController::class, 'index'])->middleware('scope:resale:read,resale:write');
    Route::post('resale-listings', [ResaleController::class, 'store'])->middleware(['scope:resale:write', 'idempotent']);
    Route::delete('resale-listings/{listing}', [ResaleController::class, 'destroy'])->middleware(['scope:resale:write', 'idempotent']);
    Route::post('resale-listings/{listing}/checkout', [ResaleController::class, 'checkout'])->middleware(['scope:resale:write', 'idempotent']);
    Route::post('resale-listings/{listing}/complete', [ResaleController::class, 'complete'])->middleware(['scope:resale:write', 'idempotent']);

    Route::post('entitlements/ack', [EntitlementController::class, 'ack'])->middleware('signed')->name('api.entitlements.ack');
    Route::get('export', MerchantExportController::class)->middleware('scope:export:read')->name('api.export');

    // Nebius AI intelligence layer (rate-limited)
    Route::prefix('ai')->middleware(['scope:ai:read', 'throttle:100,1,luvupay-api27'])->group(function () {
        Route::post('churn-prediction', [AIController::class, 'churnPrediction']);
        Route::post('resale-price-suggestion', [AIController::class, 'resalePriceSuggestion']);
        Route::get('subscription-health-report', [AIController::class, 'subscriptionHealthReport']);
        Route::get('job-result', [AIController::class, 'jobResult']);
    });
});

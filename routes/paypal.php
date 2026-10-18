<?php

use App\Http\Controllers\Api\V1\Webhooks\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

// Add to routes/api.php:   require __DIR__.'/paypal.php';
// Deliberately OUTSIDE merchant API-key auth and Idempotency middleware: PayPal signs it, we de-dupe by event id.
Route::post('/v1/webhooks/paypal', PayPalWebhookController::class)
    ->middleware('throttle:600,1')
    ->name('webhooks.paypal');

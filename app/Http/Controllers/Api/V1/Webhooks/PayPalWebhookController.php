<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Services\PayPal\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * POST /api/v1/webhooks/paypal. Public endpoint, authenticated ONLY by PayPal's signature.
 * 200 = acknowledged (processed/duplicate/ignored) | 400 = bad signature | 500 = please redeliver.
 */
final class PayPalWebhookController
{
    public function __invoke(Request $request, WebhookHandler $handler): JsonResponse
    {
        try {
            // getContent() = exact raw bytes. Do NOT use $request->all() / json(): re-encoding breaks verification.
            $outcome = $handler->handle($request->headers->all(), $request->getContent());

            return response()->json(['status' => $outcome->value]);
        } catch (InvalidWebhookSignatureException) {
            return response()->json(['error' => 'invalid_signature'], 400);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'processing_failed'], 500);
        }
    }
}

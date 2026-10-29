<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentProviderException;
use App\Services\PayPal\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * POST /api/v1/webhooks/paypal
 * Public endpoint authenticated only by PayPal signature verification.
 * 200 = acknowledged (processed / duplicate / ignored)
 * 400 = invalid signature
 * 500 = temporary failure (PayPal should redeliver)
 */
final class PayPalWebhookController
{
    public function __invoke(Request $request, WebhookHandler $handler): JsonResponse
    {
        try {
            // Exact raw body bytes - never re-encode JSON before verification.
            $outcome = $handler->handle($request->headers->all(), $request->getContent());

            return response()->json([
                'status' => $outcome->value,
            ]);
        } catch (InvalidWebhookSignatureException) {
            return response()->json(['error' => 'invalid_signature'], 400);
        } catch (PaymentProviderException $e) {
            // Retryable PayPal outage during verify: ask PayPal to redeliver.
            report($e);

            return response()->json([
                'error' => $e->retryable ? 'provider_unavailable' : 'provider_error',
            ], $e->retryable ? 500 : 400);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'processing_failed'], 500);
        }
    }
}

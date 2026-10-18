<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verify LuvuPay → merchant webhooks. Adjust header names to match your WebhookSigner.
 * Route: POST /webhooks/luvupay  (exclude from CSRF)
 */
class LuvuPayWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $raw = $request->getContent();
        $timestamp = (string) $request->header('X-LuvuPay-Timestamp', '');
        $nonce = (string) $request->header('X-LuvuPay-Nonce', '');
        $signature = (string) $request->header('X-LuvuPay-Signature', '');
        $secret = (string) config('services.luvupay.webhook_secret');

        if ($timestamp === '' || abs(time() - (int) $timestamp) > 300) {
            return response('stale timestamp', 401);
        }
        if ($nonce === '' || ! Cache::add('luvupay:nonce:'.$nonce, 1, 600)) {
            return response('replay', 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$raw, $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response('bad signature', 401);
        }

        $event = json_decode($raw, true) ?: [];
        Log::info('luvupay.webhook', ['type' => $event['type'] ?? null]);

        // TODO: map entitlement.transfer → revoke/grant access in YOUR database

        return response()->json(['received' => true]);
    }
}

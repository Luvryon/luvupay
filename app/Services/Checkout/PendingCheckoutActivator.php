<?php

namespace App\Services\Checkout;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Marks a pending PayPal checkout as completed when a verified webhook confirms payment.
 * Idempotent: only rows with status=pending are updated.
 */
final class PendingCheckoutActivator
{
    /**
     * @param  array{provider_subscription_id?:?string, provider_reference?:?string, custom_id?:?string, order_id?:?string}  $hints
     */
    public function activateFromWebhook(array $hints): bool
    {
        $customId = $this->clean($hints['custom_id'] ?? null);
        $orderId = $this->clean($hints['order_id'] ?? null);
        $subId = $this->clean($hints['provider_subscription_id'] ?? null);
        $ref = $this->clean($hints['provider_reference'] ?? null);

        $tx = null;
        if ($customId && Str::isUuid($customId)) {
            $tx = DB::table('transactions')->where('id', $customId)->where('status', 'pending')->first();
        }
        if (! $tx && $ref) {
            $tx = DB::table('transactions')->where('provider_reference', $ref)->where('status', 'pending')->first();
        }
        if (! $tx && $orderId) {
            $tx = DB::table('transactions')->where('provider_reference', $orderId)->where('status', 'pending')->first();
        }
        if (! $tx && $subId) {
            $sub = DB::table('subscriptions')->where('provider_subscription_id', $subId)->first();
            if ($sub) {
                $tx = DB::table('transactions')
                    ->where('subscription_id', $sub->id)
                    ->where('type', 'charge')
                    ->where('status', 'pending')
                    ->orderByDesc('created_at')
                    ->first();
            }
        }

        if (! $tx) {
            Log::info('luvupay.webhook.checkout_not_pending', [
                'custom_id' => $customId,
                'order_id' => $orderId,
                'provider_subscription_id' => $subId,
            ]);

            return false;
        }

        $now = now();
        $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
        $meta['lifecycle'] = 'ACTIVE';
        $meta['awaiting_paypal'] = false;
        $meta['paypal_confirmed_at'] = $now->toIso8601String();
        $meta['activated_by'] = 'paypal_webhook';
        unset($meta['popup']);

        $reference = $ref ?: $orderId ?: ($tx->provider_reference ?? null);

        $won = DB::table('transactions')
            ->where('id', $tx->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'completed',
                'provider' => 'paypal',
                'provider_reference' => $reference,
                'processed_at' => $now,
                'metadata' => json_encode($meta),
                'updated_at' => $now,
            ]);

        if ($won === 0) {
            return false;
        }

        if ($tx->subscription_id) {
            $subUpdate = [
                'status' => 'active',
                'updated_at' => $now,
            ];
            if ($subId) {
                $subUpdate['provider_subscription_id'] = $subId;
            }
            $subMeta = json_decode((string) DB::table('subscriptions')->where('id', $tx->subscription_id)->value('metadata'), true) ?: [];
            $subMeta['awaiting_paypal'] = false;
            $subUpdate['metadata'] = json_encode($subMeta);
            DB::table('subscriptions')->where('id', $tx->subscription_id)->update($subUpdate);
        }

        Log::info('luvupay.webhook.checkout_activated', [
            'transaction_id' => $tx->id,
            'subscription_id' => $tx->subscription_id,
            'reference' => $reference,
        ]);

        // Webhook won the race against the return URL: invoice + emails must still go out (once).
        try {
            app(CheckoutFinalizer::class)->finalize((string) $tx->merchant_id, (string) $tx->id);
        } catch (\Throwable $e) {
            Log::warning('luvupay.webhook.finalize_failed', ['transaction_id' => $tx->id, 'err' => $e->getMessage()]);
        }

        return true;
    }

    private function clean(?string $v): ?string
    {
        $v = $v !== null ? trim($v) : null;

        return $v === '' ? null : $v;
    }
}

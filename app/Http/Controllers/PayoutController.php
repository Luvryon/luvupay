<?php

namespace App\Http\Controllers;

use App\Services\PayPal\PayPalClient;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * PayPal Payouts for two hackathon scenarios:
 *  1) merchant_withdrawal — platform balance → merchant's connected PayPal
 *  2) charity_donation    — unused subscription value → verified charity PayPal
 *
 * ENVIRONMENT SWAP:
 * Receiver emails live ONLY in .env (PAYPAL_MERCHANT_SANDBOX_EMAIL / PAYPAL_CHARITY_SANDBOX_EMAIL).
 * Production ships the same code with real emails — zero code change, no sb-* strings in the repo UI.
 */
final class PayoutController extends Controller
{
    public function executePayout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payout_type' => ['required', 'in:merchant_withdrawal,charity_donation'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'transaction_id' => ['nullable', 'uuid'], // required for charity (ties to subscription row)
            'charity_label' => ['nullable', 'string', 'max:120'], // display only — never the email
        ]);

        $user = $request->user('merchant_web');
        abort_unless($user && $user->merchant_id, 403);
        $merchantId = $user->merchant_id;

        $type = $data['payout_type'];
        $receiver = $this->resolveReceiver($type);
        if ($receiver === null) {
            return response()->json([
                'ok' => false,
                'error' => $type === 'merchant_withdrawal'
                    ? 'PAYPAL_MERCHANT_SANDBOX_EMAIL is not set in .env'
                    : 'PAYPAL_CHARITY_SANDBOX_EMAIL is not set in .env',
            ], 422);
        }

        $currency = 'USD';
        $amountMinor = 0;
        $note = '';
        $txId = $data['transaction_id'] ?? null;

        if ($type === 'merchant_withdrawal') {
            $available = $this->availableBalanceMinor($merchantId);
            $amountMinor = (int) ($data['amount_minor'] ?? $available);
            if ($amountMinor <= 0 || $amountMinor > $available) {
                return response()->json(['ok' => false, 'error' => 'Insufficient available balance'], 422);
            }
            $note = 'LuvuPay merchant withdrawal';
        } else {
            // charity_donation — amount from held subscription residual
            abort_unless($txId, 422, 'transaction_id required for charity_donation');
            $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $txId)->first();
            abort_unless($tx, 404);
            $amountMinor = (int) ($data['amount_minor'] ?? $tx->amount_minor);
            $currency = $tx->currency ?? 'USD';
            $label = $data['charity_label'] ?? 'Verified charity';
            $note = 'LuvuPay charity split · '.$label;
        }

        $senderBatchId = 'lp-'.($type === 'merchant_withdrawal' ? 'wd' : 'ch').'-'.Str::lower(Str::random(12));
        $requestId = $senderBatchId;

        try {
            $client = PayPalClient::fromConfig();
            $money = Money::of($amountMinor, $currency);
            $batch = $client->createPayout(
                [[
                    'receiver' => $receiver, // from .env — never from the request body
                    'amount' => $money,
                    'note' => $note,
                    'sender_item_id' => $requestId,
                ]],
                $senderBatchId,
                $requestId,
                $type === 'merchant_withdrawal' ? 'LuvuPay withdrawal' : 'LuvuPay charity donation'
            );

            $batchId = (string) ($batch['batch_header']['payout_batch_id'] ?? $batch['batch_header']['payout_batch_id'] ?? '');
            $status = (string) ($batch['batch_header']['batch_status'] ?? 'UNKNOWN');

            Log::info('paypal.payout.created', [
                'type' => $type,
                'merchant_id' => $merchantId,
                'batch_id' => $batchId,
                'status' => $status,
                'amount_minor' => $amountMinor,
                // never log the full receiver email in production logs if possible — mask
                'receiver_masked' => $this->maskEmail($receiver),
            ]);

            // Ledger row
            $ledgerId = (string) Str::uuid();
            DB::table('transactions')->insert([
                'id' => $ledgerId,
                'merchant_id' => $merchantId,
                'type' => $type === 'merchant_withdrawal' ? 'payout' : 'charity_payout',
                'status' => strtolower($status) === 'success' ? 'completed' : 'pending',
                'amount_minor' => -abs($amountMinor),
                'currency' => $currency,
                'provider' => 'paypal',
                'provider_reference' => $batchId ?: $senderBatchId,
                'email' => $type === 'charity_donation' ? ($data['charity_label'] ?? 'Charity') : null,
                'metadata' => json_encode([
                    'payout_type' => $type,
                    'payout_batch_id' => $batchId,
                    'batch_status' => $status,
                    'source_transaction_id' => $txId,
                    'charity_label' => $data['charity_label'] ?? null,
                    'receiver_masked' => $this->maskEmail($receiver),
                    'env_swap' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($type === 'charity_donation' && $txId) {
                $src = DB::table('transactions')->where('id', $txId)->first();
                if ($src) {
                    $meta = json_decode($src->metadata ?? '{}', true) ?: [];
                    $meta['lifecycle'] = 'AI-RETAINED';
                    $meta['decision'] = 'charity_donate';
                    $meta['charity'] = [
                        'label' => $data['charity_label'] ?? 'American Red Cross',
                        'payout_batch_id' => $batchId,
                        'amount_minor' => $amountMinor,
                        'at' => now()->toIso8601String(),
                    ];
                    DB::table('transactions')->where('id', $txId)->update([
                        'status' => 'completed',
                        'metadata' => json_encode($meta),
                        'updated_at' => now(),
                    ]);
                }
            }

            return response()->json([
                'ok' => true,
                'message' => $type === 'merchant_withdrawal'
                    ? 'Withdrawal submitted to PayPal Payouts'
                    : 'Charity donation submitted to PayPal Payouts',
                'payout_type' => $type,
                'payout_batch_id' => $batchId,
                'batch_status' => $status,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                // UI-safe: never return the sandbox email
                'receiver_display' => $type === 'merchant_withdrawal'
                    ? 'My Business PayPal Account'
                    : ($data['charity_label'] ?? 'American Red Cross'),
            ], 201);
        } catch (Throwable $e) {
            Log::error('paypal.payout.failed', [
                'type' => $type,
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'PayPal Payouts failed: '.$e->getMessage(),
            ], 502);
        }
    }

    /**
     * Resolve receiver email from environment only.
     * Sandbox: sb-****@business.example.com in .env
     * Production: real emails in .env — same code path.
     */
    private function resolveReceiver(string $payoutType): ?string
    {
        $key = $payoutType === 'merchant_withdrawal'
            ? 'services.paypal.merchant_payout_email'
            : 'services.paypal.charity_payout_email';

        $email = trim((string) config($key, ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function availableBalanceMinor(string $merchantId): int
    {
        $sum = (int) DB::table('transactions')
            ->where('merchant_id', $merchantId)
            ->whereIn('status', ['completed', 'settled', 'pending'])
            ->sum('amount_minor');

        return max(0, $sum);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $show = strlen($local) <= 2 ? '*' : substr($local, 0, 2).'***';

        return $show.'@'.$parts[1];
    }
}

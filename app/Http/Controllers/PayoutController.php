<?php

namespace App\Http\Controllers;

use App\Services\PayPal\PayPalClient;
use App\Support\Money;
use App\Support\PayoutFee;
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
        // Mock driver (default for demos / no PayPal credentials): payouts are simulated end-to-end,
        // written to the same ledger, and move the wallet exactly like a real PayPal Payout would.
        $mock = (string) config('luvupay.payment.driver', 'mock') !== 'paypal';
        $receiver = $mock
            ? ($this->resolveReceiver($type, (string) $merchantId) ?? 'mock-receiver@luvupay.local')
            : $this->resolveReceiver($type, (string) $merchantId);
        if ($receiver === null) {
            return response()->json([
                'ok' => false,
                'error' => $type === 'merchant_withdrawal'
                    ? 'Set your PayPal payout email in Settings → Payouts (or PAYPAL_MERCHANT_SANDBOX_EMAIL in .env)'
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
            $quote = PayoutFee::quote($amountMinor);
            if ($quote['net_minor'] < PayoutFee::MIN_NET_MINOR) {
                return response()->json(['ok' => false, 'error' => 'Amount too small: after the PayPal fee you would receive less than '.number_format(PayoutFee::MIN_NET_MINOR / 100, 2).'.'], 422);
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

        // What PayPal actually sends: gross - fee for withdrawals (fee stays in the merchant's ledger debit), full amount for charity.
        $quote = $quote ?? ['gross_minor' => $amountMinor, 'fee_minor' => 0, 'net_minor' => $amountMinor];
        $sendMinor = $quote['net_minor'];
        $senderBatchId = 'lp-'.($type === 'merchant_withdrawal' ? 'wd' : 'ch').'-'.Str::lower(Str::random(12));
        $requestId = $senderBatchId;

        try {
            $fallbackReason = null;
            if ($mock) {
                $batch = ['batch_header' => ['payout_batch_id' => 'MOCK-'.strtoupper(Str::random(12)), 'batch_status' => 'SUCCESS']];
            } else {
                try {
                    $client = PayPalClient::fromConfig();
                    $money = Money::of($sendMinor, $currency);
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
                } catch (Throwable $pe) {
                    // PayPal Sandbox frequently rejects payouts (Payouts not enabled on the app, sender account without
                    // balance, unverified receiver). In sandbox we still complete the withdrawal as a clearly-labelled
                    // simulation instead of failing with HTTP 502. Production never falls back.
                    if (! $this->sandboxFallback()) {
                        throw $pe;
                    }
                    $fallbackReason = $this->explainPayPalError($pe->getMessage());
                    Log::warning('paypal.payout.sandbox_fallback', ['type' => $type, 'merchant_id' => $merchantId, 'error' => $pe->getMessage()]);
                    $batch = ['batch_header' => ['payout_batch_id' => 'SANDBOX-SIM-'.strtoupper(Str::random(10)), 'batch_status' => 'SUCCESS']];
                }
            }
            $simulated = $mock || $fallbackReason !== null;

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
                'idempotency_key' => 'payout-'.$senderBatchId,
                'type' => $type === 'merchant_withdrawal' ? 'payout' : 'charity_payout',
                'status' => strtolower($status) === 'success' ? 'completed' : 'pending',
                'amount_minor' => -abs($amountMinor),
                'currency' => $currency,
                'provider' => $simulated ? 'mock' : 'paypal',
                'provider_reference' => $batchId ?: $senderBatchId,
                'email' => $type === 'charity_donation' ? ($data['charity_label'] ?? 'Charity') : null,
                'metadata' => json_encode([
                    'payout_type' => $type,
                    'payout_batch_id' => $batchId,
                    'batch_status' => $status,
                    'source_transaction_id' => $txId,
                    'charity_label' => $data['charity_label'] ?? null,
                    'receiver_masked' => $this->maskEmail($receiver),
                    'gross_minor' => $quote['gross_minor'],
                    'fee_minor' => $quote['fee_minor'],
                    'net_minor' => $quote['net_minor'],
                    'fee_bps' => PayoutFee::config()['bps'],
                    'simulated' => $simulated,
                    'fallback_reason' => $fallbackReason,
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

            $receipt = ['sent' => false, 'to' => null, 'error' => null];
            if ($type === 'merchant_withdrawal') {
                $receipt = \App\Services\Payout\PayoutMailer::sendWithdrawalReceipt((string) $merchantId, [
                    'gross_minor' => $quote['gross_minor'], 'fee_minor' => $quote['fee_minor'], 'net_minor' => $quote['net_minor'],
                    'currency' => $currency, 'batch_id' => $batchId ?: null, 'status' => $status, 'receiver_masked' => $this->maskEmail($receiver),
                    'simulated' => $simulated, 'ledger_id' => $ledgerId,
                ]);
            }

            return response()->json([
                'ok' => true,
                'message' => ($simulated ? 'Simulated: ' : '').($type === 'merchant_withdrawal'
                    ? 'Withdrawal submitted to PayPal Payouts'
                    : 'Charity donation submitted to PayPal Payouts'),
                'mock' => $simulated,
                'fallback' => $fallbackReason !== null,
                'warning' => $fallbackReason ? 'PayPal Sandbox did not accept this payout ('.$fallbackReason.'). It was recorded as a simulated payout so your wallet stays consistent.' : null,
                'payout_type' => $type,
                'payout_batch_id' => $batchId,
                'batch_status' => $status,
                'amount_minor' => $amountMinor,
                'gross_minor' => $quote['gross_minor'],
                'fee_minor' => $quote['fee_minor'],
                'net_minor' => $quote['net_minor'],
                'receipt_email_sent' => $receipt['sent'],
                'receipt_email_to' => $receipt['to'] ? $this->maskEmail($receipt['to']) : null,
                'currency' => $currency,
                // UI-safe: never return the sandbox email
                'receiver_display' => $type === 'merchant_withdrawal'
                    ? $this->maskEmail($receiver)
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
                'error' => 'PayPal Payouts failed: '.$this->explainPayPalError($e->getMessage()),
                'hint' => 'Check PAYPAL_CLIENT_ID/SECRET, that Payouts is enabled on the PayPal app, that the sender sandbox account has funds, and set PAYPAL_MODE=sandbox.',
            ], 502);
        }
    }

    /**
     * Resolve receiver email from environment only.
     * Sandbox: sb-****@business.example.com in .env
     * Production: real emails in .env — same code path.
     */
    private function resolveReceiver(string $payoutType, string $merchantId = ''): ?string
    {
        // Merchant withdrawals go to the merchant's OWN PayPal (Settings → Payouts); the .env account is only a sandbox fallback.
        if ($payoutType === 'merchant_withdrawal' && $merchantId !== '') {
            $own = trim((string) DB::table('merchants')->where('id', $merchantId)->value('payout_email'));
            if ($own !== '' && filter_var($own, FILTER_VALIDATE_EMAIL)) {
                return $own;
            }
        }
        $key = $payoutType === 'merchant_withdrawal' ? 'services.paypal.merchant_payout_email' : 'services.paypal.charity_payout_email';
        $email = trim((string) config($key, ''));

        return ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) ? null : $email;
    }

    private function sandboxFallback(): bool
    {
        return (string) config('services.paypal.mode', 'sandbox') !== 'live'
            && filter_var(config('luvupay.payout_sandbox_fallback', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function explainPayPalError(string $raw): string
    {
        $m = strtoupper($raw);
        $map = [
            'INSUFFICIENT_FUNDS' => 'the sender sandbox account has insufficient funds',
            'AUTHORIZATION_ERROR' => 'Payouts is not enabled for this PayPal app (enable "Payouts" in the developer dashboard)',
            'PAYOUTS_NOT_ENABLED' => 'Payouts is not enabled for this PayPal app',
            'RECEIVER_UNREGISTERED' => 'the receiver email is not a registered sandbox account',
            'INVALID_CLIENT' => 'invalid PayPal client id/secret',
            'INVALID_REQUEST' => 'PayPal rejected the request format',
            'USER_BUSINESS_ERROR' => 'the sandbox receiver account cannot receive payouts',
            'MALFORMED_REQUEST' => 'PayPal rejected the request format',
            'TIMEOUT' => 'PayPal timed out',
        ];
        foreach ($map as $needle => $text) {
            if (str_contains($m, $needle)) {
                return $text;
            }
        }

        return mb_substr(trim($raw) !== '' ? $raw : 'unknown PayPal error', 0, 160);
    }

    private function availableBalanceMinor(string $merchantId): int
    {
        return (int) app(\App\Services\Dashboard\MerchantMetrics::class)->compute($merchantId)['wallet_balance_minor'];
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

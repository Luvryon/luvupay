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
        // Only merchant withdrawals go through this endpoint. Charity donations are executed by the "donate" decision
        // (one per charge, amount derived server-side); a free-form charity payout API would let any caller drain the platform account.
        $data = $request->validate([
            'payout_type' => ['nullable', 'in:merchant_withdrawal'],
            'amount_minor' => ['nullable', 'integer', 'min:1', 'max:100000000000'],
        ]);

        $user = $request->user('merchant_web');
        abort_unless($user && $user->merchant_id, 403);
        $merchantId = (string) $user->merchant_id;
        $type = 'merchant_withdrawal';

        $mock = (string) config('luvupay.payment.driver', 'mock') !== 'paypal';
        $receiver = $mock
            ? ($this->resolveReceiver($type, $merchantId) ?? 'mock-receiver@luvupay.local')
            : $this->resolveReceiver($type, $merchantId);
        if ($receiver === null) {
            return response()->json(['ok' => false, 'error' => 'Set your PayPal payout email in Settings → Payouts (or PAYPAL_MERCHANT_SANDBOX_EMAIL in .env)'], 422);
        }

        $currency = strtoupper(trim((string) DB::table('merchants')->where('id', $merchantId)->value('currency'))) ?: 'USD';
        $senderBatchId = 'lp-wd-'.Str::lower(Str::random(12));
        $requestId = $senderBatchId;
        $ledgerId = (string) Str::uuid();

        // 1) RESERVE. The merchant row is locked, so two concurrent withdrawals are serialised and can never both
        //    spend the same balance. The pending ledger row already counts as withdrawn.
        $reserved = DB::transaction(function () use ($merchantId, $data, $currency, $senderBatchId, $ledgerId, $mock) {
            DB::table('merchants')->where('id', $merchantId)->lockForUpdate()->first();
            $available = $this->availableBalanceMinor($merchantId);
            $amountMinor = (int) ($data['amount_minor'] ?? $available);
            if ($amountMinor <= 0 || $amountMinor > $available) {
                return ['error' => 'Insufficient available balance'];
            }
            $quote = PayoutFee::quote($amountMinor);
            if ($quote['net_minor'] < PayoutFee::MIN_NET_MINOR) {
                return ['error' => 'Amount too small: after the PayPal fee you would receive less than '.number_format(PayoutFee::MIN_NET_MINOR / 100, 2).'.'];
            }
            DB::table('transactions')->insert([
                'id' => $ledgerId, 'merchant_id' => $merchantId, 'idempotency_key' => 'payout-'.$senderBatchId, 'type' => 'payout', 'status' => 'pending',
                'amount_minor' => -abs($amountMinor), 'currency' => $currency, 'provider' => $mock ? 'mock' : 'paypal', 'provider_reference' => $senderBatchId,
                'metadata' => json_encode(['payout_type' => 'merchant_withdrawal', 'gross_minor' => $quote['gross_minor'], 'fee_minor' => $quote['fee_minor'], 'net_minor' => $quote['net_minor'], 'reserved' => true]),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['amount' => $amountMinor, 'quote' => $quote];
        });
        if (isset($reserved['error'])) {
            return response()->json(['ok' => false, 'error' => $reserved['error']], 422);
        }
        $amountMinor = $reserved['amount'];
        $quote = $reserved['quote'];
        $sendMinor = $quote['net_minor'];
        $note = 'LuvuPay merchant withdrawal';

        // 2) PAY. Any failure releases the reservation (status failed is not counted as withdrawn).
        try {
            $fallbackReason = null;
            if ($mock) {
                $batch = ['batch_header' => ['payout_batch_id' => 'MOCK-'.strtoupper(Str::random(12)), 'batch_status' => 'SUCCESS']];
            } else {
                try {
                    $batch = PayPalClient::fromConfig()->createPayout(
                        [['receiver' => $receiver, 'amount' => Money::of($sendMinor, $currency), 'note' => $note, 'sender_item_id' => $requestId]],
                        $senderBatchId, $requestId, 'LuvuPay withdrawal'
                    );
                } catch (Throwable $pe) {
                    // Sandbox only: a payout PayPal rejects (Payouts not enabled, no funds...) is recorded as a labelled simulation.
                    if (! $this->sandboxFallback()) {
                        throw $pe;
                    }
                    $fallbackReason = $this->explainPayPalError($pe->getMessage());
                    Log::warning('paypal.payout.sandbox_fallback', ['merchant_id' => $merchantId, 'error' => $pe->getMessage()]);
                    $batch = ['batch_header' => ['payout_batch_id' => 'SANDBOX-SIM-'.strtoupper(Str::random(10)), 'batch_status' => 'SUCCESS']];
                }
            }
            $simulated = $mock || $fallbackReason !== null;
            $batchId = (string) ($batch['batch_header']['payout_batch_id'] ?? '');
            $status = (string) ($batch['batch_header']['batch_status'] ?? 'UNKNOWN');
            Log::info('paypal.payout.created', ['merchant_id' => $merchantId, 'batch_id' => $batchId, 'status' => $status, 'amount_minor' => $amountMinor, 'receiver_masked' => $this->maskEmail($receiver)]);

            DB::table('transactions')->where('id', $ledgerId)->update([
                'status' => strtolower($status) === 'success' ? 'completed' : 'pending',
                'provider' => $simulated ? 'mock' : 'paypal', 'provider_reference' => $batchId ?: $senderBatchId, 'processed_at' => now(),
                'metadata' => json_encode([
                    'payout_type' => 'merchant_withdrawal', 'payout_batch_id' => $batchId, 'batch_status' => $status, 'receiver_masked' => $this->maskEmail($receiver),
                    'gross_minor' => $quote['gross_minor'], 'fee_minor' => $quote['fee_minor'], 'net_minor' => $quote['net_minor'], 'fee_bps' => PayoutFee::config()['bps'],
                    'simulated' => $simulated, 'fallback_reason' => $fallbackReason,
                ]),
                'updated_at' => now(),
            ]);

            $receiptArgs = [
                'gross_minor' => $quote['gross_minor'], 'fee_minor' => $quote['fee_minor'], 'net_minor' => $quote['net_minor'],
                'currency' => $currency, 'batch_id' => $batchId ?: null, 'status' => $status, 'receiver_masked' => $this->maskEmail($receiver),
                'simulated' => $simulated, 'ledger_id' => $ledgerId,
            ];
            $receipt = \App\Services\Payout\PayoutMailer::sendWithdrawalReceipt($merchantId, $receiptArgs);
            $receiptPayload = \App\Services\Payout\PayoutMailer::receiptPayload($merchantId, $receiptArgs);

            return response()->json([
                'ok' => true,
                'message' => ($simulated ? 'Simulated: ' : '').'Withdrawal submitted to PayPal Payouts',
                'mock' => $simulated, 'fallback' => $fallbackReason !== null,
                'warning' => $fallbackReason ? 'PayPal Sandbox did not accept this payout ('.$fallbackReason.'). It was recorded as a simulated payout so your wallet stays consistent.' : null,
                'payout_type' => $type, 'payout_batch_id' => $batchId, 'batch_status' => $status,
                'amount_minor' => $amountMinor, 'gross_minor' => $quote['gross_minor'], 'fee_minor' => $quote['fee_minor'], 'net_minor' => $quote['net_minor'],
                'receipt_email_sent' => $receipt['sent'], 'receipt_email_to' => $receipt['to'] ? $this->maskEmail($receipt['to']) : null,
                'receipt_email_error' => $receipt['error'] ?? null, 'receipt' => $receiptPayload, 'currency' => $currency,
                'receiver_display' => $this->maskEmail($receiver),
            ], 201);
        } catch (Throwable $e) {
            DB::table('transactions')->where('id', $ledgerId)->where('status', 'pending')->update(['status' => 'failed', 'updated_at' => now()]);   // release the reservation
            Log::error('paypal.payout.failed', ['merchant_id' => $merchantId, 'error' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'error' => 'PayPal Payouts failed: '.$this->explainPayPalError($e->getMessage()).'. Your balance was not changed.',
                'hint' => 'Check PAYPAL_CLIENT_ID/SECRET, that Payouts is enabled on the PayPal app, that the sender sandbox account has funds, and set PAYPAL_MODE=sandbox',
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

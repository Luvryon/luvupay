<?php

namespace App\Http\Controllers\Demo;

use App\Exceptions\SmartConvertException;
use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Models\SmartConvertRun;
use App\Services\SmartConvert\SmartConvertEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Developer Sandbox: AI Smart-Convert protocol.
 *
 *   AI proposes (Nebius · Nemotron)  →  merchant can approve / reject / lower the donation  →  PayPal Payouts executes.
 *
 * mode=auto runs the three steps in one request (the one-click demo); mode=propose stops after the AI step.
 * Every run is stored in smart_convert_runs and shown on the public AI Negotiation Evidence page.
 * Gated by the demo.simulator middleware (off by default in production).
 */
final class DemoSmartConvertController
{
    use DemoSupport;

    /** POST /api/v1/dashboard/demo/smart-convert */
    public function smartConvert(Request $request, SmartConvertEngine $engine): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate([
            'transaction_id' => ['nullable', 'uuid'],
            'mode' => ['nullable', 'in:auto,propose'],
        ]);
        $mode = $data['mode'] ?? (string) config('luvupay.smart_convert.default_mode', 'auto');
        $mode = $mode === 'propose' ? 'propose' : 'auto';

        $tx = null;
        if (! empty($data['transaction_id'])) {
            $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $data['transaction_id'])->first();
        }
        $tx ??= DB::table('transactions')->where('merchant_id', $merchantId)->where('type', 'charge')->orderByDesc('created_at')->first();

        $email = $tx->email ?? 'smartconvert.demo@demo.luvupay.local';
        $currency = trim((string) ($tx->currency ?? $this->merchantCurrency($merchantId))) ?: 'USD';

        // 1) AI PROPOSES: no money moves, no DB lock held while the model thinks
        try {
            $plan = $engine->propose($merchantId, [
                'email' => $email, 'currency' => $currency,
                'unused_days' => 10, 'unused_value_minor' => 3000, 'inactive_days' => 20,
            ]);
        } catch (SmartConvertException $e) {
            $run = $this->recordFailure($merchantId, $tx->id ?? null, $mode, $currency, $e);

            return $this->failure($run, $e);
        }

        $run = SmartConvertRun::create([
            'merchant_id' => $merchantId,
            'transaction_id' => $tx->id ?? null,
            'status' => 'proposed',
            'mode' => $mode,
            'path' => $plan['path'],
            'model' => $plan['ai']['model'],
            'provider' => $plan['ai']['provider'],
            'tokens_used' => $plan['ai']['tokens'],
            'latency_ms' => $plan['ai']['latency_ms'],
            'ai_log_id' => $plan['ai']['log_id'],
            'ai_error' => $plan['ai']['error'],
            'rationale' => $plan['rationale'],
            'reasoning_trace' => $plan['reasoning'],
            'narrative' => $plan['narrative'],
            'risk_score' => $plan['risk_score'],
            'input_snapshot' => $plan['input'],
            'raw_split' => $plan['raw_split'],
            'guardrails' => $plan['guardrails'],
            'unused_value_minor' => $plan['unused_value_minor'],
            'store_credit_minor' => $plan['store_credit_minor'],
            'charity_minor' => $plan['charity_minor'],
            'fee_minor' => $plan['fee_minor'],
            'currency' => $currency,
        ]);

        if ($mode === 'propose') {
            return $this->respond($run, 'AI proposal ready. Review it, then approve to let PayPal execute.', 202, $tx ? $this->formatRow($tx->id) : null);
        }

        // 2+3) auto mode: approve on the merchant's behalf and execute immediately
        return $this->executeRun($request, $engine, $run, $tx, $email, null);
    }

    /** POST /api/v1/dashboard/demo/smart-convert/{run}/approve  (optional charity_minor can only LOWER the donation) */
    public function approve(Request $request, SmartConvertEngine $engine, string $run): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate(['charity_minor' => ['nullable', 'integer', 'min:0']]);
        $model = SmartConvertRun::where('merchant_id', $merchantId)->findOrFail($run);
        abort_unless($model->status === 'proposed', 409, 'This proposal was already decided.');

        $tx = $model->transaction_id ? DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $model->transaction_id)->first() : null;

        return $this->executeRun($request, $engine, $model, $tx, $tx->email ?? 'smartconvert.demo@demo.luvupay.local', $data['charity_minor'] ?? null);
    }

    /** POST /api/v1/dashboard/demo/smart-convert/{run}/reject */
    public function reject(Request $request, string $run): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $model = SmartConvertRun::where('merchant_id', $merchantId)->findOrFail($run);
        $won = SmartConvertRun::where('id', $model->id)->where('status', 'proposed')->update([
            'status' => 'rejected', 'decided_by' => $this->actor($request), 'decided_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($won === 1, 409, 'This proposal was already decided.');

        return $this->respond($model->refresh(), 'Proposal rejected. No money moved.', 200);
    }

    /** GET /api/v1/dashboard/demo/smart-convert/preflight[?ping=1]: is the hero flow ready to record? */
    public function preflight(Request $request, SmartConvertEngine $engine): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $driver = (string) config('luvupay.payment.driver', 'mock');
        $checks = [
            'nebius_enabled' => (bool) config('nebius.enabled'),
            'nebius_key_present' => trim((string) config('nebius.api_key')) !== '',
            'model' => (string) config('nebius.model'),
            'strict_mode' => (bool) config('luvupay.smart_convert.strict'),
            'payment_driver_is_paypal' => $driver === 'paypal',
            'paypal_credentials_present' => ! empty(config('services.paypal.client_id')) && ! empty(config('services.paypal.secret')),
            'paypal_mode' => (string) config('services.paypal.mode'),
            'charity_receiver_configured' => filter_var((string) config('services.paypal.charity_payout_email'), FILTER_VALIDATE_EMAIL) !== false
                || (bool) DB::table('charity_wallets')->where('is_active', true)->whereNotNull('payout_email')->exists(),
        ];
        $ready = $checks['nebius_enabled'] && $checks['nebius_key_present'] && $checks['payment_driver_is_paypal']
            && $checks['paypal_credentials_present'] && $checks['charity_receiver_configured'];
        $out = ['ready_for_real_run' => $ready, 'checks' => $checks, 'live_ai_ping' => null];

        if ($request->boolean('ping')) {
            try {
                $p = $engine->propose($merchantId, ['unused_days' => 10, 'unused_value_minor' => 3000, 'currency' => 'USD']);
                $out['live_ai_ping'] = ['ok' => $p['path'] === 'ai', 'model' => $p['ai']['model'], 'latency_ms' => $p['ai']['latency_ms'], 'tokens' => $p['ai']['tokens'], 'note' => 'No money moved; nothing stored except ai_request_logs.'];
            } catch (SmartConvertException $e) {
                $out['live_ai_ping'] = ['ok' => false, 'error' => $e->getMessage()];
                $out['ready_for_real_run'] = false;
            }
        }

        return response()->json($out);
    }

    // ------------------------------------------------------------------ execution

    private function executeRun(Request $request, SmartConvertEngine $engine, SmartConvertRun $run, ?object $tx, string $email, ?int $charityOverride): JsonResponse
    {
        // Compare-and-set so two approvals can never both pay
        $claimed = SmartConvertRun::where('id', $run->id)->where('status', 'proposed')->update([
            'status' => 'executing', 'decided_by' => $this->actor($request), 'decided_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($claimed === 1, 409, 'This proposal is already being executed.');
        $run->refresh();

        // Merchant intervention: the donation can be lowered (never raised); the difference becomes store credit
        if ($charityOverride !== null && $charityOverride < $run->charity_minor) {
            $diff = $run->charity_minor - $charityOverride;
            $run->guardrails = array_merge($run->guardrails ?? [], ["Merchant lowered the donation from {$run->charity_minor} to {$charityOverride}; {$diff} moved to store credit."]);
            $run->store_credit_minor += $diff;
            $run->charity_minor = $charityOverride;
            $run->save();
        }

        // Receiver comes from PAYPAL_CHARITY_SANDBOX_EMAIL first (environment swap); the seeded wallet address is only a last resort
        try {
            $pp = $engine->execute($run->merchant_id, $run->charity_minor, $run->currency, null, $run->id);
        } catch (SmartConvertException $e) {
            $run->update(['status' => 'failed', 'paypal_error' => Str::limit($e->getMessage(), 480), 'paypal_mode' => (string) config('services.paypal.mode'), 'paypal_sender_batch_id' => $e->context['sender_batch_id'] ?? null]);

            return $this->failure($run->refresh(), $e);
        }

        // Money has moved (or was explicitly simulated): record it BEFORE any bookkeeping that could fail
        $run->update([
            'status' => 'executed', 'executed_at' => now(),
            'paypal_live' => $pp['live'], 'paypal_simulated' => $pp['simulated'],
            'paypal_batch_id' => $pp['batch_id'], 'paypal_sender_batch_id' => $pp['sender_batch_id'], 'paypal_batch_status' => $pp['status'],
            'paypal_receiver_masked' => $pp['receiver_masked'], 'paypal_mode' => $pp['mode'], 'paypal_error' => $pp['error'],
        ]);

        $warning = null;
        try {
            $txId = $this->applyToLedger($run->refresh(), $tx, $email);
            if ($txId && ! $run->transaction_id) {
                $run->update(['transaction_id' => $txId]);
            }
        } catch (Throwable $e) {
            Log::critical('smart_convert.ledger_write_failed', ['run' => $run->id, 'err' => $e->getMessage()]);
            $warning = 'Payout recorded, but the dashboard ledger row could not be written. See server logs.';
        }

        return $this->respond($run->refresh(), 'Smart-Convert complete: chargeback prevented, merchant revenue retained.', 200, $run->transaction_id ? $this->formatRow($run->transaction_id) : null, $warning);
    }

    /** Dashboard ledger rows + webhook event. Returns the parent transaction id. */
    private function applyToLedger(SmartConvertRun $run, ?object $tx, string $email): string
    {
        return DB::transaction(function () use ($run, $tx, $email) {
            $now = now();
            $m = $run->merchant_id;
            $store = $run->store_credit_minor;
            $charity = $run->charity_minor;
            $fee = $run->fee_minor;
            $currency = $run->currency;
            $evidenceUrl = url('/evidence/smart-convert/'.$run->id);
            $breakdown = sprintf('Store Credit: $%s | Charity: $%s | Fee: $%s', number_format($store / 100, 2), number_format($charity / 100, 2), number_format($fee / 100, 2));
            $sc = [
                'run_id' => $run->id, 'evidence_url' => $evidenceUrl, 'path' => $run->path,
                'unused_days' => $run->input_snapshot['unused_days'] ?? null, 'unused_value_minor' => $run->unused_value_minor,
                'store_credit_minor' => $store, 'charity_minor' => $charity, 'fee_minor' => $fee,
                'narrative' => $run->narrative, 'risk_score' => $run->risk_score,
                'ai' => ['live' => $run->path === 'ai', 'model' => $run->model, 'tokens' => $run->tokens_used, 'latency_ms' => $run->latency_ms, 'provider' => $run->provider],
                'paypal' => ['live' => $run->paypal_live, 'simulated' => $run->paypal_simulated, 'batch_id' => $run->paypal_batch_id, 'status' => $run->paypal_batch_status],
                'chargeback_prevented' => true, 'revenue_retained_minor' => $store + $fee,
            ];
            $ref = $run->paypal_batch_id ?: 'SIM-'.strtoupper(substr($run->id, 0, 8));   // simulated rows say so; never a made-up PayPal id

            if ($tx) {
                $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
                $meta['lifecycle'] = 'SMART-CONVERTED';
                $meta['breakdown'] = $breakdown;
                $meta['smart_convert'] = $sc;
                DB::table('transactions')->where('id', $tx->id)->update([
                    'status' => 'completed', 'metadata' => json_encode($meta), 'churn_risk' => 'low',
                    'trust_score' => min(99, (int) ($tx->trust_score ?? 70) + 15), 'updated_at' => $now,
                ]);
                $txId = $tx->id;
            } else {
                $txId = (string) Str::uuid();
                DB::table('transactions')->insert([
                    'id' => $txId, 'merchant_id' => $m, 'type' => 'charge', 'status' => 'completed',
                    'idempotency_key' => 'smart-convert-'.$txId, 'email' => $email, 'amount_minor' => $run->unused_value_minor, 'currency' => $currency,
                    'provider' => $run->paypal_live ? 'paypal' : 'mock', 'provider_reference' => $ref, 'processed_at' => $now,
                    'trust_score' => 91, 'churn_risk' => 'low',
                    'metadata' => json_encode(['demo' => true, 'lifecycle' => 'SMART-CONVERTED', 'display_name' => 'Alex', 'breakdown' => $breakdown, 'smart_convert' => $sc]),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            if ($charity > 0) {
                DB::table('transactions')->insert([
                    'id' => (string) Str::uuid(), 'merchant_id' => $m, 'parent_transaction_id' => $txId, 'type' => 'charity_payout', 'status' => 'completed',
                    'idempotency_key' => 'smart-charity-'.$run->id, 'email' => (string) config('luvupay.smart_convert.charity_label', 'Example Charity (sandbox)'), 'amount_minor' => $charity, 'currency' => $currency,
                    'provider' => $run->paypal_live ? 'paypal' : 'mock', 'provider_reference' => $ref, 'processed_at' => $now,
                    'metadata' => json_encode(['source' => 'smart_convert', 'run_id' => $run->id, 'matched_by_merchant' => true, 'paypal_batch_id' => $run->paypal_batch_id, 'simulated' => $run->paypal_simulated, 'example_only' => (bool) config('luvupay.smart_convert.charity_example', true), 'receipt_id' => $run->donation()['receipt_id']]),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $this->logWebhookEvent('luvupay', 'smart_convert.completed', ['transaction_id' => $txId, 'run_id' => $run->id, 'path' => $run->path, 'store_credit_minor' => $store, 'charity_minor' => $charity, 'fee_minor' => $fee, 'paypal_batch_id' => $run->paypal_batch_id]);
            try {
                $this->recordOutboundDelivery($m, 'smart_convert.completed', ['transaction_id' => $txId, 'run_id' => $run->id, 'store_credit_minor' => $store, 'paypal_batch_id' => $run->paypal_batch_id], success: true);
            } catch (Throwable $e) {
                Log::notice('smart_convert.outbound_delivery_skipped', ['err' => $e->getMessage()]);
            }

            return $txId;
        });
    }

    // ------------------------------------------------------------------ responses

    private function respond(SmartConvertRun $run, string $message, int $status, ?array $row = null, ?string $warning = null): JsonResponse
    {
        $ev = $run->evidence();

        return response()->json([
            'ok' => true,
            'message' => $message,
            'warning' => $warning,
            'run_id' => $run->id,
            'status' => $run->status,
            'path' => $run->path,
            'headline' => $ev['headline'],
            'evidence_url' => url('/evidence/smart-convert/'.$run->id),
            'evidence_json_url' => url('/evidence/smart-convert/'.$run->id.'.json'),
            'approve_url' => $run->status === 'proposed' ? url('/api/v1/dashboard/demo/smart-convert/'.$run->id.'/approve') : null,
            'conversion' => $ev['split'] + ['narrative' => $run->narrative, 'risk_score' => $run->risk_score],
            'ai' => $ev['model'] + ['rationale' => $ev['reasoning']['rationale_steps'], 'provider_trace' => $ev['reasoning']['provider_trace']],
            'paypal' => $ev['paypal'],
            'donation' => $ev['donation'],
            'row' => $row,
        ], $status);
    }

    private function failure(SmartConvertRun $run, SmartConvertException $e): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'stage' => $e->stage,
            'error' => $e->getMessage(),
            'message' => $e->stage === 'ai'
                ? 'The AI step failed, so nothing was executed. No money moved and no fallback was used.'
                : 'The PayPal step failed. No money moved and no ledger row was written.',
            'run_id' => $run->id,
            'evidence_url' => url('/evidence/smart-convert/'.$run->id),
        ], 502);
    }

    private function recordFailure(string $merchantId, ?string $txId, string $mode, string $currency, SmartConvertException $e): SmartConvertRun
    {
        return SmartConvertRun::create([
            'merchant_id' => $merchantId, 'transaction_id' => $txId, 'status' => 'failed', 'mode' => $mode, 'path' => 'ai',
            'model' => (string) ($e->context['model'] ?? config('nebius.model')), 'provider' => 'Nebius Token Factory · NVIDIA Nemotron',
            'latency_ms' => $e->context['latency_ms'] ?? null, 'ai_error' => Str::limit($e->getMessage(), 480),
            'unused_value_minor' => 0, 'store_credit_minor' => 0, 'charity_minor' => 0, 'fee_minor' => 0, 'currency' => $currency,
        ]);
    }

    private function actor(Request $request): string
    {
        $u = $request->user('merchant_web');

        return Str::limit((string) ($u->email ?? $u->id ?? 'merchant'), 180, '');
    }
}

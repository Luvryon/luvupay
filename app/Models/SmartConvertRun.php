<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One AI Smart-Convert run = one row of evidence. See docs/AI_NEGOTIATION_EVIDENCE.md */
class SmartConvertRun extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rationale' => 'array', 'input_snapshot' => 'array', 'raw_split' => 'array', 'guardrails' => 'array',
            'paypal_live' => 'boolean', 'paypal_simulated' => 'boolean',
            'unused_value_minor' => 'integer', 'store_credit_minor' => 'integer', 'charity_minor' => 'integer', 'fee_minor' => 'integer',
            'tokens_used' => 'integer', 'latency_ms' => 'integer', 'risk_score' => 'integer',
            'decided_at' => 'datetime', 'executed_at' => 'datetime',
        ];
    }

    /**
     * Public-safe evidence document. No emails, no tokens, no merchant secrets: the id in the URL is an unguessable UUID,
     * so a judge can open it without logging in, and it is the same document the JSON endpoint returns.
     *
     * @return array<string,mixed>
     */
    public function evidence(): array
    {
        $realPayout = $this->paypal_live && $this->paypal_batch_id;
        $headline = match (true) {
            $this->status === 'failed' => 'FAILED: no money moved',
            $this->status === 'rejected' => 'REJECTED by merchant: no money moved',
            $this->status === 'proposed' => 'AI PROPOSAL: waiting for merchant approval, no money moved yet',
            $this->path === 'fallback' => 'FALLBACK PATH: this split was NOT produced by the AI model',
            $this->paypal_simulated => 'AI PATH · payout SIMULATED (mock driver, no PayPal call)',
            $realPayout => 'AI PATH · REAL PayPal Payouts batch',
            default => 'AI PATH',
        };

        return [
            'run_id' => $this->id,
            'status' => $this->status,
            'mode' => $this->mode,
            'path' => $this->path,
            'headline' => $headline,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'executed_at' => optional($this->executed_at)->toIso8601String(),
            'model' => [
                'name' => $this->model,
                'provider' => $this->provider,
                'live' => $this->path === 'ai',
                'tokens_used' => $this->tokens_used,
                'latency_ms' => $this->latency_ms,
                'ai_request_log_id' => $this->ai_log_id,
                'error' => $this->ai_error,
            ],
            'reasoning' => [
                'rationale_steps' => $this->rationale ?: [],
                'rationale_source' => 'Stated by the model inside its structured JSON answer',
                'provider_trace' => $this->reasoning_trace,
                'provider_trace_source' => $this->reasoning_trace ? 'Returned by the inference endpoint (truncated)' : 'Endpoint returned no separate reasoning trace for this run',
                'narrative' => $this->narrative,
                'risk_score' => $this->risk_score,
            ],
            'input' => $this->input_snapshot,
            'split' => [
                'currency' => $this->currency,
                'unused_value_minor' => $this->unused_value_minor,
                'store_credit_minor' => $this->store_credit_minor,
                'charity_minor' => $this->charity_minor,
                'fee_minor' => $this->fee_minor,
                'model_raw' => $this->raw_split,
                'guardrail_adjustments' => $this->guardrails ?: [],
            ],
            'paypal' => [
                'real' => (bool) $realPayout,
                'simulated' => (bool) $this->paypal_simulated,
                'mode' => $this->paypal_mode,
                'payout_batch_id' => $this->paypal_batch_id,
                'sender_batch_id' => $this->paypal_sender_batch_id,
                'batch_status' => $this->paypal_batch_status,
                'receiver_masked' => $this->paypal_receiver_masked,
                'endpoint' => 'POST /v1/payments/payouts',
                'error' => $this->paypal_error,
            ],
            'donation' => $this->donation(),
            'audit' => [
                'transaction_id' => $this->transaction_id,
                'decided_by' => $this->maskIdentity((string) $this->decided_by),
                'decided_at' => optional($this->decided_at)->toIso8601String(),
                'human_can_intervene' => $this->mode === 'propose',
            ],
        ];
    }

    /**
     * The donation stage of the flow. Illustrative by default (config smart_convert.charity_example):
     * the recipient is a placeholder and PayPal Sandbox moves test money only. Nothing here claims a real charity was paid.
     *
     * @return array<string,mixed>
     */
    public function donation(): array
    {
        $status = match (true) {
            in_array($this->status, ['failed', 'rejected'], true) => 'not_sent',
            $this->status === 'proposed' => 'awaiting_approval',
            (int) $this->charity_minor <= 0 => 'none',
            $this->status === 'executed' && $this->paypal_simulated => 'simulated',
            $this->status === 'executed' => 'sent',
            default => 'processing',
        };
        $example = (bool) config('luvupay.smart_convert.charity_example', true);
        $unused = max(1, (int) $this->unused_value_minor);

        return [
            'example_only' => $example,
            'recipient' => (string) config('luvupay.smart_convert.charity_label', 'Example Charity (sandbox)'),
            'recipient_note' => $example
                ? 'Illustrative recipient. No real organisation is paid; PayPal Sandbox moves test money only.'
                : 'Recipient configured by the merchant.',
            'amount_minor' => (int) $this->charity_minor,
            'currency' => $this->currency,
            'share_percent' => round(((int) $this->charity_minor / $unused) * 100, 1),
            'status' => $status,
            'receipt_id' => 'DON-'.strtoupper(substr((string) $this->id, 0, 8)),
            'payout_batch_id' => $this->paypal_batch_id,
            'network' => $this->paypal_simulated ? 'Simulated (mock driver)' : ($this->paypal_live ? 'PayPal '.ucfirst((string) $this->paypal_mode) : null),
        ];
    }

    /** Public pages never show a full email: o***@domain keeps the audit trail readable without exposing the person. */
    private function maskIdentity(string $v): ?string
    {
        if ($v === '') {
            return null;
        }
        if (! str_contains($v, '@')) {
            return mb_substr($v, 0, 1).'***';
        }
        [$l, $d] = explode('@', $v, 2);

        return mb_substr($l, 0, 1).'***@'.$d;
    }
}

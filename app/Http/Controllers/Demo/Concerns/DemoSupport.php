<?php

namespace App\Http\Controllers\Demo\Concerns;

use Illuminate\Http\Request;
use App\Support\MerchantProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Helpers shared by the Demo Simulator controllers (merchant scoping, ledger row shape, webhook event log). */
trait DemoSupport
{
    /** Merchant the current request acts for; events written/read through this trait are tenant-scoped to it. */
    private ?string $scopedMerchant = null;

    private function merchantId(Request $request): string
    {
        $id = $request->attributes->get('merchant_id');
        if (! $id && $request->user('merchant_web')) {
            $id = $request->user('merchant_web')->merchant_id;
        }
        abort_unless($id, 401, 'No merchant context.');

        return $this->scopedMerchant = (string) $id;
    }

    private function merchantCurrency(string $merchantId): string
    {
        $row = DB::table('merchants')->where('id', $merchantId)->first();
        $code = is_object($row) && isset($row->currency) ? trim((string) $row->currency) : '';

        return $code !== '' ? strtoupper($code) : 'USD';
    }

    private function formatRow(string $txId): array
    {
        $q = DB::table('transactions')->where('id', $txId);
        if ($this->scopedMerchant) {
            $q->where('merchant_id', $this->scopedMerchant);
        }
        $r = $q->first();
        abort_unless($r, 404, 'Transaction not found.');
        $meta = json_decode($r->metadata ?? '{}', true) ?: [];
        $inv = null;
        try {
            $inv = DB::table('invoices')->where('transaction_id', $txId)->first();
        } catch (\Throwable $e) {
            // invoices table may not be migrated yet
        }

        return [
            'id' => $r->id,
            'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
            'email' => $r->email,
            'amount_minor' => (int) $r->amount_minor,
            'currency' => trim($r->currency),
            'status' => $r->status,
            'lifecycle' => $meta['lifecycle'] ?? $this->inferLifecycle($r->status),
            'type' => $r->type === 'charge' ? 'Charge' : Str::headline($r->type),
            'provider_reference' => $r->provider_reference,
            'buyer_email' => $meta['buyer_email'] ?? null,
            'split_snapshot' => json_decode($r->split_snapshot ?? 'null', true),
            'trust_score' => $r->trust_score !== null ? (int) $r->trust_score : null,
            'churn_risk' => $r->churn_risk,
            'certificate_code' => $meta['certificate_code'] ?? null,
            'receipt_id' => $meta['receipt_id'] ?? null,
            'ai_nudge' => $meta['ai_nudge'] ?? null,
            'display_name' => $meta['display_name'] ?? null,
            'product' => $meta['product'] ?? MerchantProject::current()->product(),
            'invoice_id' => $inv->id ?? null,
            'invoice_number' => $inv->invoice_number ?? null,
        ];
    }

    private function inferLifecycle(string $status): string
    {
        return match ($status) {
            'held' => 'GRACE_PERIOD',
            'completed', 'settled' => 'ACTIVE',
            default => strtoupper($status),
        };
    }

    private function logWebhookEvent(string $provider, string $type, array $payload, string $status = 'processed'): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('webhook_events')) {
                \Illuminate\Support\Facades\Log::warning('demo.webhook_events_table_missing');

                return;
            }
            $now = now();
            DB::table('webhook_events')->insert([
                'id' => (string) Str::uuid(),
                'provider' => $provider,
                'merchant_id' => $this->scopedMerchant,
                'event_id' => substr($provider.'-'.Str::uuid()->toString(), 0, 190),
                'event_type' => substr($type, 0, 96),
                'status' => $status,
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'attempts' => 1,
                'last_error' => null,
                'received_at' => $now,
                'processed_at' => $status === 'processed' ? $now : null,
                'next_retry_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('demo.log_webhook_failed', [
                'type' => $type,
                'err' => $e->getMessage(),
            ]);
        }
    }

    /** @return list<array<string,mixed>> */
    private function recentWebhookEvents(int $limit = 10): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('webhook_events')) {
                return [];
            }

            if ($this->scopedMerchant === null) {
                return [];
            }

            return DB::table('webhook_events')
                ->where('merchant_id', $this->scopedMerchant)
                ->orderByDesc('received_at')
                ->limit($limit)
                ->get(['id', 'provider', 'event_type', 'status', 'payload', 'received_at'])
                ->map(function ($r) {
                    $payload = is_string($r->payload) ? $r->payload : json_encode($r->payload);

                    return [
                        'id' => $r->id,
                        'timestamp' => \Illuminate\Support\Carbon::parse($r->received_at)->toIso8601String(),
                        'provider' => $r->provider,
                        'event_type' => $r->event_type,
                        'status' => $r->status,
                        'http_hint' => in_array($r->status, ['processed', 'received'], true) ? '200 OK' : '400 Error',
                        'payload_preview' => \Illuminate\Support\Str::limit((string) $payload, 180),
                    ];
                })->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function recordOutboundDelivery(
        string $merchantId,
        string $eventType,
        array $payload,
        bool $success = true,
        ?string $forceFailUrl = null,
    ): string {
        $id = (string) Str::uuid();
        $now = now();
        $url = $forceFailUrl;
        if (! $url) {
            try {
                $url = DB::table('webhook_endpoints')->where('merchant_id', $merchantId)->value('url')
                    ?: 'https://httpbin.org/status/200';
            } catch (\Throwable $e) {
                $url = 'https://httpbin.org/status/200';
            }
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('webhook_deliveries')) {
                return $id;
            }
            DB::table('webhook_deliveries')->insert([
                'id' => $id,
                'merchant_id' => $merchantId,
                'event_type' => $eventType,
                'payload' => json_encode($payload),
                'response_code' => $success ? 200 : 500,
                'attempts' => 1,
                'next_retry_at' => $success ? null : $now->copy()->addMinutes(5),
                'status' => $success ? 'success' : 'failed',
                'last_error' => $success ? null : 'Simulated merchant endpoint error (500)',
                'target_url' => $url,
                'delivered_at' => $success ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // non-fatal for demo
        }

        return $id;
    }
}

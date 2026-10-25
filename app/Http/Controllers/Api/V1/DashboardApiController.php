<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Dashboard\MerchantMetrics;
use App\Support\MerchantProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only JSON feeding the merchant dashboard. Query-builder (no models needed yet).
 * Every query is scoped to ONE merchant. Row cap = 1000 (the grids use client-side pagination).
 */
final class DashboardApiController
{
    private const LIMIT = 1000;
    private const TYPE_LABELS = [
        'charge' => 'Charge', 'refund' => 'Refund',
        'charity_payout' => 'Payout', 'user_payout' => 'Payout', 'resale_sale' => 'Resale',
    ];

    public function page(Request $request)
    {
        $merchantId = $this->merchantId($request);

        return view('dashboard.index', [
            'merchantName' => DB::table('merchants')->where('id', $merchantId)->value('name'),
            'project' => MerchantProject::current(),
        ]);
    }

    public function setProject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'plan' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:240'],
            'environment' => ['nullable', 'in:sandbox,production'],
            'interval' => ['nullable', 'in:month,year'],
            'price_minor' => ['nullable', 'integer', 'min:100', 'max:100000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'marketplace_resale' => ['sometimes', 'boolean'],
            'charity_split' => ['sometimes', 'boolean'],
            'grace_period' => ['sometimes', 'boolean'],
            'partial_refund_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);

        $current = MerchantProject::current();
        if (empty($data['name']) && ! $current->isSet()) {
            return response()->json(['ok' => false, 'message' => 'Create a project first'], 422);
        }

        $payload = [];
        foreach (['name', 'plan', 'description', 'environment', 'interval', 'price_minor', 'currency', 'partial_refund_bps'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null) {
                $payload[$k] = $data[$k];
            }
        }
        if ($request->has('description')) {
            $payload['description'] = (string) ($data['description'] ?? '');
        }
        foreach (['marketplace_resale', 'charity_split', 'grace_period'] as $k) {
            if (array_key_exists($k, $data)) {
                $payload[$k] = (bool) $data[$k];
            }
        }
        if (empty($payload['name'])) {
            $payload['name'] = $current->name;
        }
        $project = MerchantProject::store($payload);

        // Keep the merchant's billing currency aligned with the project so every ledger row/KPI uses one currency.
        if (! empty($payload['currency'])) {
            DB::table('merchants')->where('id', $this->merchantId($request))->update([
                'currency' => strtoupper($payload['currency']), 'updated_at' => now(),
            ]);
        }

        return response()->json(['ok' => true, 'project' => $project->toArray()]);
    }

    public function project(): JsonResponse
    {
        return response()->json(['project' => MerchantProject::current()->toArray()]);
    }

    public function summary(Request $request, MerchantMetrics $metrics): JsonResponse
    {
        $m = $this->merchantId($request);
        $data = $metrics->compute($m);

        $data['integrations'] = [
            'paypal' => trim((string) config('services.paypal.client_id', '')) !== ''
                && trim((string) config('services.paypal.secret', '')) !== '',
            'paypal_mode' => config('services.paypal.mode', 'sandbox'),
            'payment_driver' => config('luvupay.payment.driver', 'mock'),
            'paypal_merchant_connected' => (bool) (DB::table('merchants')->where('id', $m)->value('paypal_merchant_id')
                ?: session('paypal_connected')),
            'nebius' => (bool) config('nebius.enabled', true)
                && trim((string) config('nebius.api_key', '')) !== '',
        ];
        // In mock mode withdrawals are simulated, so the wallet never needs a PayPal link.
        $data['can_withdraw'] = $data['integrations']['payment_driver'] !== 'paypal'
            || $data['integrations']['paypal_merchant_connected'];
        $data['project'] = MerchantProject::current()->toArray();
        $data['recent_events'] = $data['events'] = $this->recentEventsForMerchant($m, 40);

        return response()->json($data);
    }

    public function analytics(Request $request, MerchantMetrics $metrics): JsonResponse
    {
        $m = $this->merchantId($request);
        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $data = $metrics->compute($m, $days);
        $data['project'] = MerchantProject::current()->toArray();

        $data['recent'] = DB::table('transactions')->where('merchant_id', $m)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->orderByDesc('created_at')->limit(25)
            ->get(['id', 'created_at', 'email', 'type', 'status', 'amount_minor', 'currency', 'metadata'])
            ->map(function ($r) {
                $meta = json_decode($r->metadata ?? '{}', true) ?: [];

                return [
                    'id' => $r->id,
                    'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
                    'email' => $r->email,
                    'customer_name' => $meta['display_name'] ?? null,
                    'type' => self::TYPE_LABELS[$r->type] ?? Str::headline($r->type),
                    'status' => $r->status,
                    'lifecycle' => $meta['lifecycle'] ?? '',
                    'amount_minor' => abs((int) $r->amount_minor),
                    'currency' => trim($r->currency),
                ];
            })->values();

        return response()->json($data);
    }

    public function resale(Request $request): JsonResponse
    {
        $m = $this->merchantId($request);
        $merchant = DB::table('merchants')->where('id', $m)->first();
        $project = MerchantProject::current();
        $hosted = app(\App\Services\Hosted\HostedPurchase::class);
        $rows = DB::table('resale_listings')->where('merchant_id', $m)->orderByDesc('listed_at')->limit(100)->get();
        $list = $rows->map(function ($l) use ($merchant, $hosted) {
            $v = $l->redeem_token ? $hosted->listingView($l, $merchant) : null;
            $cur = trim((string) $l->currency) ?: 'USD';

            return [
                'id' => $l->id,
                'status' => $l->status === 'active' && Carbon::parse($l->access_expires_at)->isPast() ? 'expired' : $l->status,
                'seller' => $v['seller'] ?? $l->seller_email,
                'buyer' => $l->buyer_email,
                'days' => $v['days'] ?? null,
                'price_minor' => (int) $l->asking_price_minor,
                'currency' => $cur,
                'token' => $l->redeem_token,
                'link' => $l->redeem_token ? url('/redeem/'.$l->redeem_token) : null,
                'listed_at' => $l->listed_at ? Carbon::parse($l->listed_at)->toIso8601String() : null,
                'sold_at' => $l->sold_at ? Carbon::parse($l->sold_at)->toIso8601String() : null,
            ];
        })->values();

        return response()->json([
            'enabled' => $project->marketplaceResale,
            'store_url' => url('/m/'.$m),
            'currency' => trim((string) ($merchant->currency ?? 'USD')) ?: 'USD',
            'counts' => ['active' => $list->where('status', 'active')->count(), 'sold' => $list->where('status', 'sold')->count()],
            'listings' => $list,
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function recentEventsForMerchant(string $merchantId, int $limit = 40): array
    {
        try {
            return DB::table('webhook_events')->where('merchant_id', $merchantId)
                ->orderByDesc('received_at')->limit($limit)
                ->get(['id', 'provider', 'event_type', 'status', 'payload', 'received_at'])
                ->map(function ($r) {
                    $payload = is_string($r->payload) ? $r->payload : json_encode($r->payload);

                    return [
                        'id' => $r->id,
                        'timestamp' => Carbon::parse($r->received_at)->toIso8601String(),
                        'provider' => $r->provider,
                        'event_type' => $r->event_type,
                        'status' => $r->status,
                        'http_hint' => in_array($r->status, ['processed', 'received'], true) ? '200 OK' : '400 Error',
                        'payload_preview' => Str::limit((string) $payload, 180),
                    ];
                })->values()->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        $rows = DB::table('transactions')->where('merchant_id', $this->merchantId($request))
            ->orderByDesc('created_at')->limit(self::LIMIT)
            ->get(['id', 'created_at', 'email', 'amount_minor', 'currency', 'status', 'type', 'provider_reference', 'metadata', 'split_snapshot', 'trust_score', 'churn_risk']);

        $invoiceMap = collect();
        try {
            $invoiceMap = DB::table('invoices')->whereIn('transaction_id', $rows->pluck('id'))->get()->keyBy('transaction_id');
        } catch (\Throwable $e) {
            //
        }

        return response()->json(['data' => $rows->map(function ($r) use ($invoiceMap) {
            $meta = json_decode($r->metadata ?? '{}', true) ?: [];
            $lifecycle = $meta['lifecycle'] ?? match ($r->status) {
                'held' => 'GRACE_PERIOD',
                'completed', 'settled' => 'ACTIVE',
                default => strtoupper((string) $r->status),
            };
            $inv = $invoiceMap[$r->id] ?? null;

            return [
                'id' => $r->id,
                'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
                'email' => $r->email,
                'amount_minor' => (int) $r->amount_minor,
                'currency' => trim($r->currency),
                'status' => $r->status,
                'lifecycle' => $lifecycle,
                'type' => self::TYPE_LABELS[$r->type] ?? Str::headline($r->type),
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
                'breakdown' => $meta['breakdown'] ?? null,
                'smart_convert' => $meta['smart_convert'] ?? null,
            ];
        })->values()]);
    }

    public function resaleListings(Request $request): JsonResponse
    {
        $rows = DB::table('resale_listings as l')->join('subscriptions as s', 's.id', '=', 'l.subscription_id')
            ->where('l.merchant_id', $this->merchantId($request))
            ->orderByDesc('l.listed_at')->limit(self::LIMIT)
            ->get(['l.id', 'l.seller_email', 'l.buyer_email', 'l.asking_price_minor', 'l.currency', 'l.access_expires_at', 'l.status', 's.amount_minor as original_price_minor']);

        return response()->json(['data' => $rows->map(function ($r) {
            $secs = Carbon::parse($r->access_expires_at)->timestamp - now()->timestamp;
            $days = max(0, (int) ceil($secs / 86400));
            $status = ($r->status === 'active' && $days === 0) ? 'expired' : $r->status;

            return [
                'id' => $r->id,
                'seller' => $r->seller_email,
                'original_price_minor' => (int) $r->original_price_minor,
                'resale_price_minor' => (int) $r->asking_price_minor,
                'currency' => trim($r->currency),
                'days_remaining' => $days,
                'status' => $status,
                'buyer' => $r->buyer_email,
            ];
        })->values()]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $rows = DB::table('audit_logs')->where('merchant_id', $this->merchantId($request))
            ->orderByDesc('id')->limit(self::LIMIT)
            ->get(['id', 'created_at', 'auditable_type', 'auditable_id', 'event', 'old_values', 'new_values', 'actor_type', 'actor_id', 'ip_address', 'hash']);

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id' => $r->id,
            'timestamp' => Carbon::parse($r->created_at)->toIso8601String(),
            'event' => class_basename($r->auditable_type).'.'.$r->event,
            'actor' => $r->actor_type ? $r->actor_type.($r->actor_id ? ':'.$r->actor_id : '') : 'system',
            'details' => $this->describe($r),
            'ip_address' => $r->ip_address ?: '-',
            'hash' => $r->hash,
        ])->values()]);
    }

    // ------------------------------------------------------------------

    /** Human-readable diff. Sensitive keys are masked: the audit viewer must never leak secrets. */
    private function describe(object $r): string
    {
        $old = json_decode($r->old_values ?? 'null', true) ?: [];
        $new = json_decode($r->new_values ?? 'null', true) ?: [];
        $mask = fn (string $k, $v) => preg_match('/secret|token|password|api_key|hash/i', $k) ? '•••••' : (is_scalar($v) || $v === null ? var_export($v, true) : '{…}');
        $skip = ['updated_at', 'created_at'];

        $parts = [];
        foreach ($new as $k => $v) {
            if (in_array($k, $skip, true)) {
                continue;
            }
            $parts[] = array_key_exists($k, $old) ? "$k: ".$mask($k, $old[$k]).' → '.$mask($k, $v) : "$k=".$mask($k, $v);
        }

        $text = $parts ? implode('; ', array_slice($parts, 0, 4)).(count($parts) > 4 ? '; …' : '') : ucfirst($r->event).' '.Str::limit((string) $r->auditable_id, 8, '');

        return str_replace("'", '', $text);
    }


    public function subscriptions(Request $request): JsonResponse
    {
        $m = $this->merchantId($request);
        $rows = DB::table('subscriptions')->where('merchant_id', $m)
            ->orderByDesc('created_at')->limit(self::LIMIT)
            ->get(['id', 'email', 'merchant_user_id', 'status', 'amount_minor', 'currency', 'provider',
                'current_period_start', 'current_period_end', 'next_billing_at', 'metadata', 'created_at']);

        return response()->json(['data' => $rows->map(function ($r) {
            $meta = json_decode($r->metadata ?? '{}', true) ?: [];
            $status = strtolower((string) $r->status);
            $label = match (true) {
                in_array($status, ['active', 'trialing'], true) => 'Active',
                in_array($status, ['past_due', 'grace'], true) => 'At Risk',
                str_contains($status, 'retain') || (($meta['lifecycle'] ?? '') === 'AI-RETAINED') => 'AI-Retained',
                in_array($status, ['cancelled', 'churned', 'expired'], true) => 'Churned',
                default => ucfirst($status),
            };

            return [
                'id' => $r->id,
                'email' => $r->email,
                'display_name' => $meta['display_name'] ?? $r->email,
                'project' => $meta['product'] ?? \App\Support\MerchantProject::current()->product(),
                'plan' => number_format(((int) $r->amount_minor) / 100, 2).' '.($r->currency ?: 'USD').'/mo',
                'status' => $label,
                'status_raw' => $status,
                'amount_minor' => (int) $r->amount_minor,
                'ltv_minor' => (int) $r->amount_minor, // single period until we accumulate
                'next_billing_at' => $r->next_billing_at,
                'created_at' => $r->created_at,
            ];
        })->values()]);
    }

    private function merchantId(Request $request): string
    {
        $id = $request->attributes->get('merchant_id');
        if (! $id && $request->user('merchant_web')) {
            $id = $request->user('merchant_web')->merchant_id;
        }
        abort_unless($id, 401, 'No merchant context.');

        return (string) $id;
    }
}

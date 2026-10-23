<?php

namespace App\Http\Controllers\Api\V1;

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
            'price_minor' => ['nullable', 'integer', 'min:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'marketplace_resale' => ['sometimes', 'boolean'],
            'charity_split' => ['sometimes', 'boolean'],
            'partial_refund_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);

        // Allow feature-flag-only updates when project already exists
        if (empty($data['name']) && ! MerchantProject::current()->isSet()) {
            return response()->json(['ok' => false, 'message' => 'Create a project first'], 422);
        }

        $payload = [];
        foreach (['name', 'plan', 'description', 'environment', 'price_minor', 'currency', 'partial_refund_bps'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null) {
                $payload[$k] = $data[$k];
            }
        }
        if (array_key_exists('marketplace_resale', $data)) {
            $payload['marketplace_resale'] = (bool) $data['marketplace_resale'];
        }
        if (array_key_exists('charity_split', $data)) {
            $payload['charity_split'] = (bool) $data['charity_split'];
        }
        // Feature-only update: keep existing name
        if (empty($payload['name']) && MerchantProject::current()->isSet()) {
            $payload['name'] = MerchantProject::current()->name;
        }
        $project = MerchantProject::store($payload);

        return response()->json(['ok' => true, 'project' => $project->toArray()]);
    }

    public function summary(Request $request): JsonResponse
    {
        $m = $this->merchantId($request);
        $sum = fn (string $type) => (int) DB::table('transactions')->where('merchant_id', $m)->where('type', $type)->where('status', 'completed')->sum('amount_minor');
        $merchant = DB::table('merchants')->where('id', $m)->first(['serial_forgetter_threshold', 'currency']);

        $txBase = DB::table('transactions')->where('merchant_id', $m);
        $chargeCount = (int) (clone $txBase)->where('type', 'charge')->count();
        $completedCharges = (int) (clone $txBase)->where('type', 'charge')->where('status', 'completed')->count();
        $churned = 0;
        $retained = 0;
        try {
            $subs = DB::table('subscriptions')->where('merchant_id', $m);
            $totalSubs = (int) (clone $subs)->count();
            $churned = (int) (clone $subs)->whereIn('status', ['cancelled', 'churned', 'expired'])->count();
            $activeSubs = (int) (clone $subs)->whereIn('status', ['active', 'trialing', 'past_due'])->count();
        } catch (\Throwable $e) {
            $totalSubs = 0;
            $activeSubs = $completedCharges;
        }

        // Lifecycle signals from transaction metadata (demo + live)
        $recent = DB::table('transactions')->where('merchant_id', $m)->orderByDesc('created_at')->limit(1000)
            ->get(['metadata', 'type', 'status', 'amount_minor', 'churn_risk']);
        foreach ($recent as $r) {
            $meta = json_decode($r->metadata ?? '{}', true) ?: [];
            $life = strtoupper((string) ($meta['lifecycle'] ?? ''));
            if ($life === 'AI-RETAINED' || ! empty($meta['smart_convert']['chargeback_prevented'] ?? null)) {
                $retained++;
            }
            if ($life === 'CHURNED') {
                $churned++;
            }
        }

        $resaleVolume = $sum('resale_sale');
        if ($resaleVolume === 0) {
            // some rows typed as Resale label only in UI; sum by type name variants
            $resaleVolume = (int) DB::table('transactions')->where('merchant_id', $m)
                ->where('status', 'completed')
                ->where(function ($q) {
                    $q->where('type', 'like', '%resale%')->orWhere('type', 'resale_sale');
                })->sum('amount_minor');
        }

        $chargeVolume = $sum('charge');
        $revenueSavedRows = abs($sum('revenue_saved'));


        /*
         | Revenue Saved (hackathon formula)
         | A) Partial refund / store credit → cash retained (e.g. $10 of $20)
         | B) Marketplace resale → merchant transfer/platform fee from resale
         | C) Charity split → avoided PayPal chargeback penalty (~$15 = 1500 minor)
         */
        $revenueSaved = $sum('merchant_retention');
        $CHARGEBACK_AVOIDED_MINOR = 1500; // $15 typical PayPal chargeback fee avoided
        if ($revenueSaved === 0) {
            foreach ($recent as $r) {
                $meta = json_decode($r->metadata ?? '{}', true) ?: [];
                $life = strtoupper((string) ($meta['lifecycle'] ?? ''));
                $sc = $meta['smart_convert'] ?? [];
                $path = strtolower((string) ($sc['path'] ?? $meta['retention_path'] ?? $meta['choice'] ?? ''));

                // Explicit minor if engine stored it
                if (isset($sc['revenue_retained_minor'])) {
                    $revenueSaved += abs((int) $sc['revenue_retained_minor']);
                    continue;
                }
                if (isset($meta['revenue_saved_minor'])) {
                    $revenueSaved += abs((int) $meta['revenue_saved_minor']);
                    continue;
                }

                // Path C — charity / chargeback prevented
                if (! empty($sc['chargeback_prevented']) || str_contains($path, 'charity') || str_contains($path, 'donate')) {
                    $revenueSaved += $CHARGEBACK_AVOIDED_MINOR;
                    continue;
                }
                // Path B — resale fee to merchant
                if ($life === 'RESOLD' || $life === 'PENDING RESALE' || str_contains($path, 'resale') || $r->type === 'resale_sale') {
                    // Prefer fee fields; else ~10% of amount as transfer fee
                    $fee = (int) ($sc['fee_minor'] ?? $meta['merchant_fee_minor'] ?? max(100, (int) round(abs((int) $r->amount_minor) * 0.10)));
                    $revenueSaved += abs($fee);
                    continue;
                }
                // Path A — partial refund / store credit / AI-retained
                if ($life === 'AI-RETAINED' || $life === 'SMART-CONVERTED' || str_contains($path, 'credit') || str_contains($path, 'partial') || str_contains($path, 'refund')) {
                    $retained = (int) ($sc['store_credit_minor'] ?? $meta['retained_minor'] ?? (int) round(abs((int) $r->amount_minor) * 0.5));
                    $revenueSaved += abs($retained);
                }
            }
        }

        // Also count completed resale_sale fees not already in loop
        $revenueSaved += $revenueSavedRows;

        $resaleFees = (int) DB::table('transactions')->where('merchant_id', $m)
            ->where('status', 'completed')
            ->whereIn('type', ['resale_payout', 'resale_fee', 'merchant_fee'])
            ->sum('amount_minor');
        if ($resaleFees > 0 && $revenueSaved === 0) {
            $revenueSaved += abs($resaleFees);
        }

        $charity = abs($sum('charity_payout'));
        if ($charity === 0) {
            $charity = (int) DB::table('transactions')->where('merchant_id', $m)
                ->where(function ($q) {
                    $q->where('type', 'like', '%charity%')->orWhere('type', 'charity_payout');
                })->sum(DB::raw('ABS(amount_minor)'));
        }
        $denom = max(1, $retained + $churned);
        $churnRate = round(($churned / $denom) * 100, 1);


        // Merchant wallet available = completed subscription charges + resale credits − withdrawals
        $withdrawn = (int) DB::table('transactions')->where('merchant_id', $m)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->whereIn('type', ['payout', 'merchant_withdrawal', 'withdrawal', 'user_payout'])
                    ->orWhere('type', 'like', '%withdraw%');
            })->sum(DB::raw('ABS(amount_minor)'));
        $walletIn = abs($chargeVolume) + abs($resaleVolume);
        // Resale payouts to original subscribers are not merchant wallet
        $resalePayoutOut = (int) DB::table('transactions')->where('merchant_id', $m)
            ->where('status', 'completed')
            ->where('type', 'resale_payout')
            ->sum(DB::raw('ABS(amount_minor)'));
        $walletBalance = max(0, $walletIn - $withdrawn - abs($resalePayoutOut));

        return response()->json([
            'integrations' => [
                'paypal' => trim((string) config('services.paypal.client_id', '')) !== ''
                    && trim((string) config('services.paypal.secret', '')) !== '',
                'paypal_mode' => config('services.paypal.mode', 'sandbox'),
                'payment_driver' => config('luvupay.payment.driver', 'mock'),
                'paypal_merchant_connected' => (bool) (DB::table('merchants')->where('id', $m)->value('paypal_merchant_id')
                    ?: DB::table('merchants')->where('id', $m)->value('paypal_email')
                    ?: session('paypal_connected')),
                'nebius' => (bool) config('nebius.enabled', true)
                    && trim((string) config('nebius.api_key', '')) !== '',
            ],
            'currency' => trim($merchant->currency ?? 'USD'),
            'revenue_saved_minor' => $revenueSaved,
            'charity_minor' => abs($charity),
            'resale_volume_minor' => abs($resaleVolume),
            'charge_volume_minor' => abs($chargeVolume),
            // Alias for UI "Total revenue" = completed charge volume (real ledger)
            'total_revenue_minor' => abs($chargeVolume),
            'active_listings' => DB::table('resale_listings')->where('merchant_id', $m)->where('status', 'active')->where('access_expires_at', '>', now())->count(),
            'serial_forgetters' => DB::table('subscriptions')->where('merchant_id', $m)
                ->where('forgotten_cancel_count', '>=', $merchant->serial_forgetter_threshold ?? 2)->distinct()->count('merchant_user_id'),
            'transaction_count' => $chargeCount,
            'active_subscribers' => $activeSubs,
            'chargebacks_prevented' => $retained,
            'churned_count' => $churned,
            'churn_rate' => $churnRate,
            'marketplace_volume_minor' => $resaleVolume,
            'charity_impact_minor' => $charity,
            'total_revenue_saved_minor' => $revenueSaved,
            'wallet_balance_minor' => $walletBalance,
            'available_balance_minor' => $walletBalance,
            'recent_events' => $this->recentEventsForMerchant($m, 40),
            'events' => $this->recentEventsForMerchant($m, 40),
        ]);
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

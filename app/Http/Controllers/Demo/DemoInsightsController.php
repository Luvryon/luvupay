<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Developer Sandbox: Read-only demo views: webhook event log and charity receipts.
 * Gated by the demo.simulator middleware (off by default in production).
 */
final class DemoInsightsController
{
    use DemoSupport;

    /** GET /api/v1/dashboard/demo/webhook-logs */
    public function webhookLogs(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $rows = DB::table('webhook_events')
            ->where('merchant_id', $merchantId)          // tenant isolation: never show other merchants' events
            ->orderByDesc('received_at')
            ->limit(10)
            ->get(['id', 'provider', 'event_type', 'status', 'payload', 'received_at', 'attempts', 'last_error']);

        $data = $rows->map(function ($r) {
                $payload = is_string($r->payload) ? $r->payload : json_encode($r->payload);

                return [
                    'id' => $r->id,
                    'timestamp' => Carbon::parse($r->received_at)->toIso8601String(),
                    'provider' => $r->provider,
                    'event_type' => $r->event_type,
                    'status' => $r->status,
                    'http_hint' => in_array($r->status, ['processed', 'received'], true) ? '200 OK' : ($r->status === 'failed' || $r->status === 'dead' ? '400 Error' : '202 Accepted'),
                    'payload_preview' => Str::limit((string) $payload, 180),
                    'attempts' => (int) $r->attempts,
                    'last_error' => $r->last_error,
                ];
            })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'count' => $data->count(),
                'hint' => $data->isEmpty()
                    ? 'No events yet. Run Trigger Test: New Subscription Checkout — events are written to webhook_events.'
                    : null,
            ],
        ]);
    }

    /** GET /api/v1/dashboard/demo/receipts/{id} */
    public function charityReceipt(Request $request, string $id): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $row = DB::table('charity_receipts')
            ->where('merchant_id', $merchantId)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('certificate_code', $id)->orWhere('transaction_id', $id);
            })
            ->first();

        // Fallback: derive from transaction / metadata when charity_receipts row missing
        if (! $row) {
            $tx = DB::table('transactions')
                ->where('merchant_id', $merchantId)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)
                        ->orWhere('provider_reference', $id);
                })
                ->first();

            if (! $tx) {
                // last try: any tx whose metadata.receipt_id or certificate_code matches
                $candidates = DB::table('transactions')
                    ->where('merchant_id', $merchantId)
                    ->orderByDesc('created_at')
                    ->limit(200)
                    ->get(['id', 'email', 'currency', 'amount_minor', 'provider_reference', 'metadata', 'created_at', 'updated_at', 'type']);
                foreach ($candidates as $c) {
                    $meta = json_decode($c->metadata ?? '{}', true) ?: [];
                    if (($meta['receipt_id'] ?? null) === $id || ($meta['certificate_code'] ?? null) === $id) {
                        $tx = $c;
                        break;
                    }
                }
            }

            if ($tx) {
                $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
                $split = json_decode($tx->split_snapshot ?? 'null', true) ?: [];
                $charityMinor = (int) ($meta['charity_minor'] ?? $split['charity_minor'] ?? 0);
                $feeMinor = (int) ($meta['fee_minor'] ?? $split['fee_minor'] ?? 0);
                $storeCredit = (int) ($meta['store_credit_minor'] ?? $split['store_credit_minor'] ?? 0);

                return response()->json([
                    'id' => $meta['receipt_id'] ?? $tx->id,
                    'certificate_code' => $meta['certificate_code'] ?? ('RCP-'.strtoupper(substr((string) $tx->id, 0, 8))),
                    'charity_name' => $meta['charity_name'] ?? 'Verified Charity Partner',
                    'amount_minor' => (int) $tx->amount_minor,
                    'charity_minor' => $charityMinor,
                    'fee_minor' => $feeMinor,
                    'store_credit_minor' => $storeCredit,
                    'currency' => trim($tx->currency ?? 'USD'),
                    'paypal_payout_id' => $tx->provider_reference,
                    'donor_email' => $tx->email,
                    'transaction_id' => $tx->id,
                    'date' => Carbon::parse($tx->updated_at ?? $tx->created_at)->toIso8601String(),
                ]);
            }
            abort(404, 'Receipt not found');
        }

        return response()->json([
            'id' => $row->id,
            'certificate_code' => $row->certificate_code,
            'charity_name' => $row->charity_name,
            'amount_minor' => (int) $row->amount_minor,
            'charity_minor' => (int) $row->amount_minor,
            'fee_minor' => 0,
            'store_credit_minor' => 0,
            'currency' => $row->currency,
            'paypal_payout_id' => $row->paypal_payout_id,
            'donor_email' => $row->donor_email,
            'transaction_id' => $row->transaction_id,
            'date' => Carbon::parse($row->created_at)->toIso8601String(),
        ]);
    }
}

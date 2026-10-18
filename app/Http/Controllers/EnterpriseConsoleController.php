<?php

namespace App\Http\Controllers;

use App\Jobs\RetryWebhookDeliveryJob;
use App\Services\Usage\ApiUsageMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Enterprise console: invoices, webhook delivery center, API usage meter.
 */
final class EnterpriseConsoleController
{
    public function __construct(private ApiUsageMeter $meter) {}

    public function apiUsage(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);

        return response()->json($this->meter->snapshot($merchantId));
    }

    public function simulateRateLimit(Request $request): JsonResponse
    {
        abort_unless(config('luvupay.demo_simulator'), 404);
        $merchantId = $this->merchantId($request);
        $used = $this->meter->maxOut($merchantId);

        return response()->json([
            'ok' => true,
            'message' => "API usage maxed out at {$used}. Next authenticated API call will return 429.",
            'usage' => $this->meter->snapshot($merchantId),
        ]);
    }

    public function resetRateLimit(Request $request): JsonResponse
    {
        abort_unless(config('luvupay.demo_simulator'), 404);
        $merchantId = $this->merchantId($request);
        $this->meter->reset($merchantId);

        return response()->json([
            'ok' => true,
            'message' => 'API usage counter reset for this period.',
            'usage' => $this->meter->snapshot($merchantId),
        ]);
    }

    public function invoiceShow(Request $request, string $id): View
    {
        $merchantId = $this->merchantId($request);
        $invoice = DB::table('invoices')
            ->where('merchant_id', $merchantId)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('invoice_number', $id)->orWhere('transaction_id', $id);
            })
            ->first();
        abort_unless($invoice, 404);

        $merchant = DB::table('merchants')->where('id', $merchantId)->first();
        $lineItems = json_decode($invoice->line_items ?? '[]', true) ?: [];
        $meta = json_decode($invoice->metadata ?? '{}', true) ?: [];

        return view('invoices.show', compact('invoice', 'merchant', 'lineItems', 'meta'));
    }

    public function invoiceJson(Request $request, string $id): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $invoice = DB::table('invoices')
            ->where('merchant_id', $merchantId)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('invoice_number', $id)->orWhere('transaction_id', $id);
            })
            ->first();
        abort_unless($invoice, 404);

        return response()->json([
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'total_minor' => (int) $invoice->total_minor,
            'currency' => $invoice->currency,
            'url' => url('/dashboard/invoices/'.$invoice->id),
        ]);
    }

    public function webhookDeliveries(Request $request): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $rows = DB::table('webhook_deliveries')
            ->where('merchant_id', $merchantId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'event_type' => $r->event_type,
                'response_code' => $r->response_code,
                'attempts' => (int) $r->attempts,
                'status' => $r->status,
                'next_retry_at' => $r->next_retry_at ? Carbon::parse($r->next_retry_at)->toIso8601String() : null,
                'target_url' => $r->target_url,
                'last_error' => $r->last_error,
                'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
                'payload_preview' => Str::limit(is_string($r->payload) ? $r->payload : json_encode($r->payload), 120),
            ])->values(),
        ]);
    }

    public function retryWebhookDelivery(Request $request, string $id): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $row = DB::table('webhook_deliveries')
            ->where('merchant_id', $merchantId)
            ->where('id', $id)
            ->first();
        abort_unless($row, 404);

        DB::table('webhook_deliveries')->where('id', $id)->update([
            'status' => 'pending',
            'next_retry_at' => now(),
            'updated_at' => now(),
        ]);

        // Prefer queue; fall back to sync for local demo without workers
        try {
            RetryWebhookDeliveryJob::dispatch($id);
        } catch (\Throwable $e) {
            RetryWebhookDeliveryJob::dispatchSync($id);
        }

        // If still using sync / immediate, re-read
        $fresh = DB::table('webhook_deliveries')->where('id', $id)->first();

        return response()->json([
            'ok' => true,
            'message' => 'Retry dispatched',
            'delivery' => [
                'id' => $fresh->id,
                'status' => $fresh->status,
                'response_code' => $fresh->response_code,
                'attempts' => (int) $fresh->attempts,
                'last_error' => $fresh->last_error,
            ],
        ]);
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

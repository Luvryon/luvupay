<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Nebius\NegotiatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** POST /api/v1/dashboard/demo/negotiator - two-way retention chat (sandbox). */
final class DemoNegotiatorController
{
    use DemoSupport;

    public function chat(Request $request, NegotiatorService $negotiator): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'transaction_id' => ['nullable', 'uuid'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $merchantId = $this->merchantId($request);
        $ctx = [
            'merchant_id' => $merchantId,
            'name' => 'there',
            'product' => 'subscription',
            'amount_minor' => 2000,
            'currency' => 'USD',
            'loyalty_months' => 1,
            'fraud_risk' => 'low',
        ];

        if (! empty($data['transaction_id'])) {
            $tx = DB::table('transactions')->where('merchant_id', $merchantId)->where('id', $data['transaction_id'])->first();
            if ($tx) {
                $meta = json_decode($tx->metadata ?? '{}', true) ?: [];
                $ctx['name'] = (string) ($meta['display_name'] ?? explode('@', (string) $tx->email)[0] ?: 'there');
                $ctx['email'] = (string) $tx->email;
                $ctx['amount_minor'] = (int) $tx->amount_minor;
                $ctx['currency'] = trim((string) $tx->currency) ?: 'USD';
                $ctx['product'] = (string) ($meta['product'] ?? 'subscription');
                $ctx['loyalty_months'] = (int) ($meta['loyalty_months'] ?? 3);
                $ctx['fraud_risk'] = (string) ($meta['fraud_risk'] ?? 'low');
            }
        }

        $history = $data['history'] ?? [];
        $history[] = ['role' => 'user', 'content' => $data['message']];

        $result = $negotiator->turn($history, $ctx);
        $history[] = ['role' => 'assistant', 'content' => $result['reply']];

        $this->logWebhookEvent('nebius', 'ai.negotiator_turn', [
            'transaction_id' => $data['transaction_id'] ?? null,
            'tone' => $result['tone'],
            'options' => array_column($result['options'], 'id'),
        ]);

        return response()->json([
            'ok' => true,
            'reply' => $result['reply'],
            'options' => $result['options'],
            'tone' => $result['tone'],
            'safe_refund' => $result['safe_refund'],
            'thinking_ms' => $result['thinking_ms'],
            'history' => $history,
        ]);
    }
}

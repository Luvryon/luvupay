<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Dispute\DisputeEvidenceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET/POST dispute evidence from real transaction data. */
final class DemoDisputeEvidenceController
{
    use DemoSupport;

    public function show(Request $request, string $tx, DisputeEvidenceBuilder $builder): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $pack = $builder->build($merchantId, $tx);
        if (! $pack['ok']) {
            return response()->json($pack, 404);
        }

        $this->logWebhookEvent('luvupay', 'dispute.evidence_generated', [
            'transaction_id' => $tx,
            'sections' => count($pack['sections']),
        ]);

        return response()->json(['ok' => true] + $pack);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EntitlementStatus;
use App\Models\ResaleListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/v1/entitlements/ack (merchant.auth + signed): the merchant confirms it applied (or could not apply) the swap. */
final class EntitlementController
{
    public function ack(Request $request): JsonResponse
    {
        $d = $request->validate(['correlation_id' => 'required|uuid', 'status' => 'required|in:applied,failed']);

        $l = ResaleListing::query()->where('merchant_id', $request->attributes->get('merchant_id'))
            ->where('entitlement_correlation_id', $d['correlation_id'])->firstOrFail();

        if ($l->entitlement_status !== EntitlementStatus::Acknowledged) {   // first ack wins; replays are harmless
            $l->update($d['status'] === 'applied'
                ? ['entitlement_status' => EntitlementStatus::Acknowledged, 'entitlement_acknowledged_at' => now()]
                : ['entitlement_status' => EntitlementStatus::Failed]);
        }

        return response()->json(['correlation_id' => $l->entitlement_correlation_id, 'entitlement_status' => $l->entitlement_status->value]);
    }
}

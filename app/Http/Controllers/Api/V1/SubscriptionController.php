<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * INVISIBLE UX: the merchant registers (merchant_user_id, email) when a user subscribes. The end-user never sees
 * or creates a LuvuPay account. (merchant.auth + idempotent)
 */
final class SubscriptionController
{
    public function store(Request $request, PaymentProviderInterface $provider): JsonResponse
    {
        $d = $request->validate([
            'merchant_user_id' => 'required|string|max:191', 'email' => 'required|email:rfc|max:255',
            'plan_id' => 'required|string|max:64', 'plan_code' => 'nullable|string|max:191',
            'amount_minor' => 'required|integer|min:1', 'currency' => 'required|string|size:3',
            'billing_interval' => 'required|in:day,week,month,year',
            'return_url' => 'required|url:https|max:2048', 'cancel_url' => 'required|url:https|max:2048',
            'charity_wallet_id' => 'nullable|uuid|exists:charity_wallets,id',
        ]);

        $sub = Subscription::create([
            'merchant_id' => $request->attributes->get('merchant_id'),
            'merchant_user_id' => $d['merchant_user_id'], 'email' => $d['email'], 'plan_code' => $d['plan_code'] ?? $d['plan_id'],
            'status' => SubscriptionStatus::PendingApproval, 'amount_minor' => $d['amount_minor'], 'currency' => strtoupper($d['currency']),
            'billing_interval' => $d['billing_interval'], 'charity_wallet_id' => $d['charity_wallet_id'] ?? null,
        ]);

        try {
            $ps = $provider->createSubscription($d['plan_id'], $d['email'], $sub->id, $d['return_url'], $d['cancel_url'], "sub-create:{$sub->id}");
        } catch (\Throwable $e) {
            $sub->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
            throw $e;
        }
        $sub->update(['provider_subscription_id' => $ps->providerSubscriptionId]);

        return response()->json(['id' => $sub->id, 'status' => $sub->status->value, 'approval_url' => $ps->approvalUrl], 201);
    }
}

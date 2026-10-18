<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Models\ResaleListing;
use App\Models\Subscription;
use App\Services\Resale\ResaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** All routes: merchant.auth. Mutations also: idempotent. Every lookup is scoped to the calling merchant (no IDOR). */
final class ResaleController
{
    public function __construct(private readonly ResaleService $svc) {}

    public function index(Request $request): JsonResponse
    {
        $page = ResaleListing::query()->with('subscription')->where('merchant_id', $this->mid($request))
            ->where('status', $request->query('status', ListingStatus::Active->value))
            ->where('access_expires_at', '>', now())->orderByDesc('listed_at')->paginate(25);

        return response()->json(['data' => $page->getCollection()->map(fn ($l) => ResaleService::present($l))->values(), 'next_page_url' => $page->nextPageUrl()]);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['subscription_id' => 'required|uuid', 'asking_price_minor' => 'required|integer|min:1']);
        $sub = Subscription::query()->where('merchant_id', $this->mid($request))->findOrFail($d['subscription_id']);

        $listing = $this->svc->list($request->attributes->get('merchant'), $sub, $d['asking_price_minor']);

        return response()->json(ResaleService::present($listing->load('subscription')), 201);
    }

    public function destroy(Request $request, string $listing): JsonResponse
    {
        return response()->json(ResaleService::present($this->svc->cancel($this->find($request, $listing))->load('subscription')));
    }

    public function checkout(Request $request, string $listing): JsonResponse
    {
        $d = $request->validate([
            'buyer_merchant_user_id' => 'required|string|max:191', 'buyer_email' => 'required|email:rfc|max:255',
            'return_url' => 'required|url:https|max:2048', 'cancel_url' => 'required|url:https|max:2048',
        ]);

        $r = $this->svc->checkout($this->find($request, $listing), $d['buyer_merchant_user_id'], $d['buyer_email'], $d['return_url'], $d['cancel_url']);

        return response()->json(['order_id' => $r['order_id'], 'approval_url' => $r['approval_url'], 'reserved_until' => $r['reserved_until']], 201);
    }

    public function complete(Request $request, string $listing): JsonResponse
    {
        $d = $request->validate(['order_id' => 'required|string|max:64']);
        $l = $this->svc->complete($this->find($request, $listing), $d['order_id']);

        return response()->json(ResaleService::present($l->load('subscription')) + ['correlation_id' => $l->entitlement_correlation_id], 200);
    }

    private function find(Request $request, string $id): ResaleListing
    {
        return ResaleListing::query()->with('subscription')->where('merchant_id', $this->mid($request))->findOrFail($id);
    }

    private function mid(Request $request): string { return $request->attributes->get('merchant_id'); }
}

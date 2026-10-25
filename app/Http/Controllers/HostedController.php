<?php

namespace App\Http\Controllers;

use App\Services\Hosted\HostedPurchase;
use App\Support\MerchantBranding;
use App\Support\MerchantProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/** Public pages: merchant storefront (/m/{merchant}), hosted checkout and smart-token redemption. */
final class HostedController
{
    public function __construct(private readonly HostedPurchase $hosted) {}

    /** @return array<string,mixed> */
    private function brand(object $m): array
    {
        return [
            'id' => $m->id,
            'name' => (string) $m->name,
            'initials' => MerchantBranding::initials((string) $m->name),
            'logo' => MerchantBranding::logoUrl($m),
            'slug' => HostedPurchase::slug((string) $m->name),
        ];
    }

    private function money(int $minor, string $cur): string
    {
        $n = number_format($minor / 100, 2);

        return strtoupper($cur) === 'USD' ? '$'.$n : $n.' '.strtoupper($cur);
    }

    private function driverLabel(): string
    {
        $paypal = (string) config('luvupay.payment.driver', 'mock') === 'paypal'
            && trim((string) config('services.paypal.client_id', '')) !== '';

        return $paypal ? 'PayPal' : 'PayPal (sandbox simulation)';
    }

    public function storefront(string $merchant): View
    {
        $m = $this->hosted->merchant($merchant);
        abort_unless($m, 404);
        $project = MerchantProject::forMerchant($m->id);

        return view('hosted.store', [
            'brand' => $this->brand($m),
            'project' => $project,
            'price' => $project->price(),
            'listings' => $project->marketplaceResale ? array_map(fn ($l) => $l + ['price' => $this->money($l['price_minor'], $l['currency'])], $this->hosted->activeListings($m)) : [],
            'resaleOn' => $project->marketplaceResale,
        ]);
    }

    public function checkout(string $merchant): View
    {
        $m = $this->hosted->merchant($merchant);
        abort_unless($m, 404);
        $project = MerchantProject::forMerchant($m->id);
        abort_unless($project->isSet(), 404);

        return view('hosted.checkout', [
            'mode' => 'checkout',
            'brand' => $this->brand($m),
            'cfg' => [
                'mode' => 'checkout',
                'payUrl' => url('/checkout/'.$m->id.'/pay'),
                'storeUrl' => url('/m/'.$m->id),
            ],
            'productName' => $project->product(),
            'planLine' => 'Monthly Subscription - '.$project->price().'/'.($project->interval === 'year' ? 'yr' : 'mo'),
            'subtotal' => $project->price(),
            'total' => $project->price(),
            'fee' => $this->money(0, $project->currency),
            'token' => null,
            'driver' => $this->driverLabel(),
        ]);
    }

    public function redeem(string $token): View
    {
        $l = $this->hosted->listingByToken($token);
        abort_unless($l, 404);
        $m = $this->hosted->merchant((string) $l->merchant_id);
        abort_unless($m, 404);
        $v = $this->hosted->listingView($l, $m);

        return view('hosted.checkout', [
            'mode' => $v['available'] ? 'redeem' : 'unavailable',
            'brand' => $this->brand($m),
            'cfg' => [
                'mode' => $v['available'] ? 'redeem' : 'unavailable',
                'payUrl' => url('/redeem/'.$token.'/pay'),
                'storeUrl' => url('/m/'.$m->id),
            ],
            'productName' => $v['product'],
            'planLine' => null,
            'subtotal' => $this->money($v['price_minor'], $v['currency']),
            'total' => $this->money($v['price_minor'], $v['currency']),
            'fee' => $this->money(0, $v['currency']),
            'token' => $v,
            'driver' => $this->driverLabel(),
        ]);
    }

    public function payCheckout(Request $request, string $merchant): JsonResponse
    {
        $m = $this->hosted->merchant($merchant);
        abort_unless($m, 404);
        $data = $request->validate(['name' => ['nullable', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190']]);
        $project = MerchantProject::forMerchant($m->id);
        abort_unless($project->isSet(), 404);

        return $this->run([
            'intent' => 'checkout', 'merchant_id' => $m->id, 'name' => $data['name'] ?? '', 'email' => $data['email'],
            'amount_minor' => $project->priceMinor, 'currency' => $project->currency,
        ]);
    }

    public function payRedeem(Request $request, string $token): JsonResponse
    {
        $l = $this->hosted->listingByToken($token);
        abort_unless($l, 404);
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        if ($l->status !== 'active' || \Illuminate\Support\Carbon::parse($l->access_expires_at)->isPast()) {
            return response()->json(['ok' => false, 'message' => 'This token has already been redeemed or expired.'], 410);
        }
        if (strtolower((string) $l->seller_email) === strtolower($data['email'])) {
            return response()->json(['ok' => false, 'message' => 'You cannot redeem your own listing.'], 422);
        }

        return $this->run([
            'intent' => 'redeem', 'merchant_id' => (string) $l->merchant_id, 'token' => $token, 'email' => $data['email'],
            'amount_minor' => (int) $l->asking_price_minor, 'currency' => trim((string) $l->currency) ?: 'USD',
        ]);
    }

    /** @param array<string,mixed> $ctx */
    private function run(array $ctx): JsonResponse
    {
        try {
            $r = $this->hosted->start($ctx);
        } catch (Throwable $e) {
            Log::error('hosted.pay_failed', ['err' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'Payment provider unavailable. Please try again.'], 502);
        }

        return response()->json(['ok' => true] + $r);
    }

    public function paypalReturn(Request $request): RedirectResponse
    {
        $key = (string) $request->query('k', '');
        $ctx = Cache::get('hosted:pay:'.$key);
        try {
            $url = $this->hosted->finish($key);
        } catch (Throwable $e) {
            Log::error('hosted.return_failed', ['err' => $e->getMessage()]);
            $url = null;
        }
        if ($url) {
            return redirect($url);
        }

        return redirect(is_array($ctx) && $ctx['intent'] === 'redeem' ? url('/redeem/'.$ctx['token']) : url('/'))->with('error', 'Payment was not completed.');
    }

    public function paypalCancel(Request $request): RedirectResponse
    {
        $ctx = Cache::get('hosted:pay:'.(string) $request->query('k', ''));

        return redirect(is_array($ctx) ? ($ctx['intent'] === 'redeem' ? url('/redeem/'.$ctx['token']) : url('/checkout/'.$ctx['merchant_id'])) : url('/'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Mail\PlainHtmlMail;
use App\Services\Marketplace\MarketplaceService;
use App\Services\PayPal\PayPalClient;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Public B2C marketplace: DB-backed listings + PayPal Orders checkout.
 *
 * Money and state rules live in MarketplaceService. This controller only validates input and shapes responses.
 * Never expose seller PII here (see format()).
 */
final class MarketplaceController extends Controller
{
    public function __construct(private readonly MarketplaceService $market) {}

    /** GET /api/marketplace/listings */
    public function index(): JsonResponse
    {
        $this->market->releaseExpired();


        $rows = DB::table('marketplace_listings')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->limit(48)
            ->get();

        return response()->json(['data' => $rows->map(fn ($r) => $this->format($r))->values()]);
    }

    /** POST /api/marketplace/sell */
    public function sell(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/i'],
            'app_name' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'seller_email' => ['required', 'email', 'max:190'],
            'days_remaining' => ['required', 'integer', 'min:1', 'max:365'],
            'original_price_minor' => ['required', 'integer', 'min:100', 'max:10000000'],
            'price_minor' => ['required', 'integer', 'min:100', 'lte:original_price_minor'],
            'currency' => ['nullable', 'string', 'size:3'],
            'emoji' => ['nullable', 'string', 'max:16'],
        ]);

        $currency = strtoupper($data['currency'] ?? 'USD');
        abort_unless(in_array($currency, (array) config('luvupay.marketplace.currencies', ['USD']), true), 422, 'Unsupported currency');

        $needsVerification = (bool) config('luvupay.marketplace.require_seller_verification');
        $id = (string) Str::uuid();
        $now = now();

        DB::table('marketplace_listings')->insert([
            'id' => $id,
            'app_key' => strtolower($data['app_key']),
            'app_name' => $data['app_name'],
            'title' => $data['title'],
            'description' => $data['description'],
            'seller_email' => strtolower($data['seller_email']),
            'days_remaining' => (int) $data['days_remaining'],
            'original_price_minor' => (int) $data['original_price_minor'],
            'price_minor' => (int) $data['price_minor'],
            'currency' => $currency,
            'status' => $needsVerification ? 'pending' : 'active',
            'verified_at' => $needsVerification ? null : $now,
            'emoji' => $data['emoji'] ?? '🍿',
            'metadata' => json_encode(['source' => 'connect_and_sell', 'ai_generated' => true]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            if ($needsVerification) {
                $link = URL::temporarySignedRoute(
                    'marketplace.verify',
                    now()->addHours((int) config('luvupay.marketplace.verification_ttl_hours', 24)),
                    ['id' => $id],
                );
                Mail::to($data['seller_email'])->sendNow(new PlainHtmlMail(
                    'Confirm your LuvuPay marketplace listing',
                    '<p>Confirm that you want to publish <strong>'.e($data['title']).'</strong>:</p>'
                    .'<p><a href="'.e($link).'">Publish my listing</a></p><p>If this was not you, ignore this email.</p>'
                ));
            } else {
                Mail::to($data['seller_email'])->sendNow(new PlainHtmlMail(
                    'Your LuvuPay marketplace listing is live',
                    '<p>Your listing <strong>'.e($data['title']).'</strong> is now active on the LuvuPay marketplace.</p>'
                ));
            }
        } catch (Throwable $e) {
            Log::warning('marketplace.sell.email_failed', ['err' => $e->getMessage()]);
            if ($needsVerification) {
                DB::table('marketplace_listings')->where('id', $id)->delete();   // an unverifiable listing is useless; don't leave junk
                abort(503, 'Could not send the verification email. Please try again.');
            }
        }

        $row = DB::table('marketplace_listings')->where('id', $id)->first();

        return response()->json([
            'ok' => true,
            'needs_verification' => $needsVerification,
            'listing' => $this->format($row),
        ], $needsVerification ? 202 : 201);
    }

    /** GET /marketplace/listings/{id}/verify (signed URL emailed to the seller) */
    public function verify(string $id): RedirectResponse
    {
        DB::table('marketplace_listings')->where('id', $id)->where('status', 'pending')
            ->update(['status' => 'active', 'verified_at' => now(), 'updated_at' => now()]);

        return redirect('/marketplace?verified=1');
    }

    /** POST /api/marketplace/listings/{id}/buy */
    public function buy(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['buyer_email' => ['required', 'email', 'max:190']]);

        abort_if(
            trim((string) config('services.paypal.client_id', '')) === '' || trim((string) config('services.paypal.secret', '')) === '',
            503,
            'PayPal is not configured (PAYPAL_CLIENT_ID / PAYPAL_SECRET)'
        );

        // 1) Reserve (atomic compare-and-set, no external call under a lock).
        $token = $this->market->reserve($id, strtolower($data['buyer_email']));
        $listing = DB::table('marketplace_listings')->where('id', $id)->first();

        // 2) Talk to PayPal. Any failure hands the listing straight back to the pool.
        try {
            $order = $this->paypal()->createOrder(
                Money::of((int) $listing->price_minor, $listing->currency),
                $id,
                url('/marketplace/paypal/return?'.http_build_query(['listing' => $id, 'rt' => $token])),
                url('/marketplace/paypal/cancel?'.http_build_query(['listing' => $id, 'rt' => $token])),
                'mkt-buy-'.$id.'-'.substr($token, 0, 16),
                $id,
            );
            $orderId = (string) ($order['id'] ?? '');
            $approveUrl = PayPalClient::link($order, 'payer-action', 'approve');
            if ($orderId === '' || ! $approveUrl) {
                throw new \RuntimeException('PayPal order response missing id or approval link.');
            }
        } catch (Throwable $e) {
            $this->market->release($id, $token);
            Log::error('marketplace.order_failed', ['listing' => $id, 'err' => $e->getMessage()]);
            abort(502, 'Payment provider unavailable. Please try again.');
        }

        $this->market->attachOrder($id, $token, $orderId);

        return response()->json([
            'ok' => true,
            'order_id' => $orderId,
            'approve_url' => $approveUrl,
            'expires_in_minutes' => $this->market->reservationMinutes(),
            'message' => 'PayPal order created. Complete payment to buy this listing.',
        ]);
    }

    /** GET /marketplace/paypal/return: PayPal sends the buyer back; we capture only for the exact reservation. */
    public function paypalReturn(Request $request): RedirectResponse
    {
        $id = (string) $request->query('listing', '');
        $rt = (string) $request->query('rt', '');
        $orderId = (string) $request->query('token', '');

        $listing = $id !== '' ? DB::table('marketplace_listings')->where('id', $id)->first() : null;
        if (! $listing) {
            return redirect('/marketplace?failed=1');
        }
        if ($listing->status === 'sold' && $orderId !== '' && hash_equals((string) $listing->paypal_order_id, $orderId)) {
            return redirect('/marketplace?bought=1');       // page refresh after success
        }
        if (! $this->market->claimCapture($id, $rt, $orderId)) {
            Log::warning('marketplace.return_rejected', ['listing' => $id]);

            return redirect('/marketplace?failed=1');
        }

        try {
            $response = $this->paypal()->capturePayment($orderId, 'mkt-cap-'.$orderId);
        } catch (Throwable $e) {
            $this->market->unclaim($id);                     // reservation stays; buyer can retry the same link
            Log::error('marketplace.capture_error', ['listing' => $id, 'err' => $e->getMessage()]);

            return redirect('/marketplace?failed=1');
        }

        $verified = $this->market->verifiedCapture($listing, $response);
        if (! $verified) {
            $this->market->failCapture($id);
            // If PayPal did take money but it did not match, finance must refund it by hand.
            Log::critical('marketplace.capture_unverified', ['listing' => $id, 'order' => $orderId, 'status' => $response['status'] ?? null]);

            return redirect('/marketplace?failed=1');
        }

        $this->market->finalizeSale($id, $verified['id'], $verified['money']);

        try {
            Mail::to($listing->seller_email)->sendNow(new PlainHtmlMail(
                'Your marketplace listing was sold',
                '<p>Your listing <strong>'.e($listing->title).'</strong> was sold. Your payout is recorded and will be settled by LuvuPay.</p>'
            ));
        } catch (Throwable $e) {
            Log::warning('marketplace.sold_email_failed', ['err' => $e->getMessage()]);
        }

        return redirect('/marketplace?bought=1');
    }

    /** GET /marketplace/paypal/cancel: only the reservation holder's link (matching token) can release it. */
    public function paypalCancel(Request $request): RedirectResponse
    {
        $id = (string) $request->query('listing', '');
        $rt = (string) $request->query('rt', '');
        if ($id !== '' && $rt !== '') {
            $this->market->release($id, $rt);
        }

        return redirect('/marketplace?cancelled=1');
    }

    private function paypal(): PayPalClient
    {
        return app()->bound(PayPalClient::class) ? app(PayPalClient::class) : PayPalClient::fromConfig();
    }

    /** Public shape of a listing. NEVER include seller_email / buyer_email / tokens here. */
    private function format(object $r): array
    {
        return [
            'id' => $r->id,
            'app' => $r->app_name,
            'app_key' => $r->app_key,
            'title' => $r->title,
            'description' => $r->description,
            'emoji' => $r->emoji ?: '🍿',
            'daysLeft' => (int) $r->days_remaining,
            'original' => round(((int) $r->original_price_minor) / 100, 2),
            'price' => round(((int) $r->price_minor) / 100, 2),
            'currency' => $r->currency,
            'status' => $r->status,
            'seller' => $this->maskEmail((string) $r->seller_email),
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $domain === '' ? '***' : mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function seedDefaults(): void
    {
        // Intentionally empty — listings come only from real resale_listings / marketplace_listings rows.
    }
}

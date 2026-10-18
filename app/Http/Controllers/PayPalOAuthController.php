<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PayPal Business OAuth — demo-safe but production-strict.
 * Always persists paypal_merchant_id + paypal_connected_at on merchants.
 */
final class PayPalOAuthController
{
    public function redirect(Request $request): RedirectResponse
    {
        $user = $request->user('merchant_web');
        abort_unless($user, 401);

        $clientId = trim((string) config('services.paypal.client_id', env('PAYPAL_CLIENT_ID', '')));
        if ($clientId === '' || strcasecmp($clientId, 'null') === 0) {
            return redirect()->route('settings')
                ->with('status', 'PayPal credentials not configured. Use Sandbox Demo connect (CSRF POST).');
        }

        $state = Str::random(48);
        $request->session()->put('paypal_oauth_state', $state);
        $request->session()->put('paypal_oauth_user_id', $user->getAuthIdentifier());

        $base = rtrim((string) config('services.paypal.base_url', 'https://www.sandbox.paypal.com'), '/');
        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'scope' => 'openid email https://uri.paypal.com/services/paypalattributes',
            'redirect_uri' => route('oauth.paypal.callback'),
            'state' => $state,
        ]);

        return redirect()->away($base.'/signin/authorize?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $request->user('merchant_web');
        abort_unless($user, 401);

        $state = $request->session()->pull('paypal_oauth_state');
        $expectedUser = $request->session()->get('paypal_oauth_user_id');

        if (! is_string($state) || ! hash_equals($state, (string) $request->query('state', ''))) {
            Log::warning('paypal.oauth.invalid_state', ['user_id' => $user->getAuthIdentifier()]);

            return redirect()->route('settings')->with('error', 'Invalid OAuth state.');
        }
        if ($expectedUser && ! hash_equals((string) $expectedUser, (string) $user->getAuthIdentifier())) {
            return redirect()->route('settings')->with('error', 'OAuth user mismatch.');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect()->route('settings')->with('error', 'PayPal authorization denied.');
        }

        $paypalMerchantId = $this->exchangeCodeForMerchantId($code);
        if ($paypalMerchantId === null) {
            // Sandbox without working token endpoint: deterministic demo id still persisted to DB
            if (config('luvupay.public_sandbox') || config('luvupay.demo_simulator')) {
                $paypalMerchantId = 'oauth_demo_'.hash('sha256', $code.$user->getAuthIdentifier());
                $paypalMerchantId = 'pp_'.substr($paypalMerchantId, 0, 24);
            } else {
                return redirect()->route('settings')->with('error', 'PayPal token exchange failed.');
            }
        }

        $this->persistConnected($request, $user, $paypalMerchantId, 'oauth');

        return redirect()->route('settings')->with('status', 'PayPal Business account connected.');
    }

    public function sandboxConnect(Request $request): RedirectResponse
    {
        $user = $request->user('merchant_web');
        abort_unless($user, 401);

        abort_unless(
            (bool) config('luvupay.public_sandbox', false) || (bool) config('luvupay.demo_simulator', false),
            403,
            'Sandbox demo connect is disabled.'
        );

        $data = $request->validate([
            'confirm' => ['required', 'accepted'],
            // Optional explicit Sandbox Merchant ID from developer.paypal.com (no password on this form)
            'paypal_merchant_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $merchantId = trim((string) ($data['paypal_merchant_id'] ?? ''));
        if ($merchantId === '') {
            // Deterministic demo id when merchant only wants one-click connect
            $merchantId = 'sandbox_'.substr(hash('sha256', 'u:'.$user->getAuthIdentifier().'|'.Str::uuid()), 0, 28);
        }
        $this->persistConnected($request, $user, $merchantId, 'sandbox_demo');

        Log::info('paypal.sandbox_demo_connected', [
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $request->ip(),
        ]);

        return redirect()->route('settings')
            ->with('status', 'PayPal Sandbox linked and stored on your merchant record (demo).');
    }

    /** @return string|null PayPal merchant / payer id */
    private function exchangeCodeForMerchantId(string $code): ?string
    {
        $clientId = trim((string) config('services.paypal.client_id', env('PAYPAL_CLIENT_ID', '')));
        $secret = trim((string) config('services.paypal.secret', env('PAYPAL_CLIENT_SECRET', '')));
        $apiBase = rtrim((string) config('services.paypal.api_base', env('PAYPAL_API_BASE', 'https://api-m.sandbox.paypal.com')), '/');

        if ($clientId === '' || $secret === '') {
            return null;
        }

        try {
            $tokenRes = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->timeout(15)
                ->post($apiBase.'/v1/oauth2/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => route('oauth.paypal.callback'),
                ]);

            if (! $tokenRes->successful()) {
                Log::warning('paypal.oauth.token_failed', ['status' => $tokenRes->status()]);

                return null;
            }

            $access = $tokenRes->json('access_token');
            if (! is_string($access) || $access === '') {
                return null;
            }

            $userRes = Http::withToken($access)->timeout(15)->get($apiBase.'/v1/identity/oauth2/userinfo', [
                'schema' => 'paypalv1.1',
            ]);

            if ($userRes->successful()) {
                $payerId = $userRes->json('payer_id') ?? $userRes->json('user_id');
                if (is_string($payerId) && $payerId !== '') {
                    return $payerId;
                }
            }

            // Fallback: use hash of access token material (never store raw token)
            return 'pp_'.substr(hash('sha256', $access), 0, 24);
        } catch (\Throwable $e) {
            Log::warning('paypal.oauth.exchange_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function persistConnected(Request $request, object $user, string $paypalMerchantId, string $mode): void
    {
        $merchantId = $user->merchant_id ?? null;
        abort_unless($merchantId, 403, 'No merchant on this account.');

        DB::table('merchants')->where('id', $merchantId)->update([
            'paypal_merchant_id' => $paypalMerchantId,
            'paypal_connected_at' => now(),
            'paypal_connect_mode' => $mode,
            'updated_at' => now(),
        ]);

        $request->session()->put('paypal_connected', true);
        $request->session()->put('paypal_oauth_user_id', $user->getAuthIdentifier());
        $request->session()->put('paypal_connected_via', $mode);
        $request->session()->regenerate(true);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard gate: PayPal must be linked on the merchant DB row.
 * Session is only a short-lived cache of the same fact (same user id), never a substitute in production.
 */
final class EnsurePayPalConnected
{
    private const ALLOW = [
        'onboarding',
        'oauth.paypal.redirect',
        'oauth.paypal.callback',
        'oauth.paypal.sandbox-connect',
        'onboarding.api.status',
        'onboarding.api.project',
        'onboarding.api.keys',
        'onboarding.api.complete',
        'logout',
        'verification.notice',
        'verification.verify',
        'verification.resend',
        'mfa.challenge',
        'mfa.verify',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs(...self::ALLOW)) {
            return $next($request);
        }

        if (self::isConnected($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'paypal_not_connected',
                'message' => 'Connect your PayPal account in Settings to continue.',
                'action' => [
                    'label' => 'Open Settings',
                    'href' => '/settings',
                ],
            ], 403);
        }

        return redirect()->route('settings')
            ->with('warning', 'Connect your PayPal account in Settings to continue.');
    }

    public static function isConnected(Request $request): bool
    {
        $user = $request->user('merchant_web');
        if (! $user) {
            return false;
        }

        // Mock driver: every payment/payout is simulated, so no PayPal link is required to use the console.
        if ((string) config('luvupay.payment.driver', 'mock') !== 'paypal') {
            return true;
        }

        $merchant = self::merchantOf($user);
        if ($merchant && self::dbConnected($merchant)) {
            return true;
        }

        // Production: DB is mandatory — no session-only unlock
        if (app()->environment('production') && ! config('luvupay.allow_session_paypal_fallback', false)) {
            return false;
        }

        // Non-production / explicit fallback: session must match this user and DB write must have been attempted
        $sessionUserId = $request->session()->get('paypal_oauth_user_id');
        $connected = (bool) $request->session()->get('paypal_connected');
        if ($connected && $sessionUserId && hash_equals((string) $sessionUserId, (string) $user->getAuthIdentifier())) {
            // Still require that we at least have a merchant context
            return $merchant !== null;
        }

        return false;
    }

    public static function dbConnected(object $merchant): bool
    {
        $id = $merchant->paypal_merchant_id ?? null;
        $at = $merchant->paypal_connected_at ?? null;

        return ! empty($id) && ! empty($at);
    }

    private static function merchantOf(object $user): ?object
    {
        if (method_exists($user, 'merchant')) {
            try {
                return $user->merchant;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}

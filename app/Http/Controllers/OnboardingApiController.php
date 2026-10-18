<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsurePayPalConnected;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Server-side onboarding only. Secrets never minted in the browser.
 */
final class OnboardingApiController
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user('merchant_web');
        abort_unless($user, 401);

        $merchant = $this->merchantRow($user);

        return response()->json([
            'paypal_connected' => EnsurePayPalConnected::isConnected($request),
            'has_project' => (bool) $request->session()->get('onboarding_project_name'),
            'onboarding_complete' => $this->isOnboardingComplete($request, $merchant),
            'paypal_mode' => $merchant->paypal_connect_mode ?? null,
        ]);
    }

    public function createProject(Request $request): JsonResponse
    {
        $user = $request->user('merchant_web');
        abort_unless($user, 401);
        abort_unless(EnsurePayPalConnected::isConnected($request), 403, 'Connect PayPal first.');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'plan' => ['nullable', 'string', 'max:40'],
        ]);

        $request->session()->put('onboarding_project_name', $data['name']);
        $request->session()->put('onboarding_project_plan', $data['plan'] ?? 'Premium');

        // Soft-bind project label on merchant name only if still default-like (non-destructive)
        Log::info('onboarding.project_created', [
            'user_id' => $user->getAuthIdentifier(),
            'merchant_id' => $user->merchant_id ?? null,
            'name' => $data['name'],
        ]);

        return response()->json([
            'ok' => true,
            'project' => ['name' => $data['name'], 'plan' => $data['plan'] ?? 'Premium'],
        ]);
    }

    public function issueKeys(Request $request): JsonResponse
    {
        $user = $request->user('merchant_web');
        abort_unless($user, 401);
        abort_unless(EnsurePayPalConnected::isConnected($request), 403);
        abort_unless($request->session()->get('onboarding_project_name'), 422, 'Create a project first.');

        $last = (int) $request->session()->get('onboarding_keys_issued_at', 0);
        if ($last && (time() - $last) < 60) {
            return response()->json(['error' => 'rate_limited', 'message' => 'Wait before re-issuing keys.'], 429);
        }

        $merchantId = $user->merchant_id ?? null;
        abort_unless($merchantId, 403);

        $env = (config('luvupay.demo_simulator') || config('luvupay.public_sandbox')) ? 'test' : 'live';
        $issued = Merchant::issueApiKey($env);

        // Persist hash — never store plaintext key
        DB::table('merchants')->where('id', $merchantId)->update([
            'api_key_prefix' => $issued['prefix'],
            'api_key_hash' => $issued['hash'],
            'publishable_key' => $issued['publishable'],
            'updated_at' => now(),
        ]);

        $request->session()->put('onboarding_keys_issued_at', time());
        $request->session()->put('onboarding_key_prefix', $issued['prefix']);

        Log::info('onboarding.keys_issued', [
            'user_id' => $user->getAuthIdentifier(),
            'merchant_id' => $merchantId,
            'prefix' => $issued['prefix'],
            'env' => $env,
        ]);

        return response()->json([
            'ok' => true,
            'client_id' => $issued['publishable'],
            'secret_key' => $issued['key'],
            'warning' => 'Secret is shown once and stored only as a hash. Save it now.',
            'environment' => $env,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user('merchant_web');
        abort_unless($user, 401);
        abort_unless(EnsurePayPalConnected::isConnected($request), 403);
        abort_unless($request->session()->get('onboarding_project_name'), 422);
        abort_unless($request->session()->get('onboarding_key_prefix'), 422, 'Issue API keys first.');

        $merchantId = $user->merchant_id ?? null;
        abort_unless($merchantId, 403);

        DB::table('merchants')->where('id', $merchantId)->update([
            'onboarding_completed_at' => now(),
            'updated_at' => now(),
        ]);

        $request->session()->put('onboarding_complete', true);
        $request->session()->regenerate(true);

        return response()->json(['ok' => true, 'redirect' => url('/dashboard')]);
    }

    private function merchantRow(object $user): ?object
    {
        $id = $user->merchant_id ?? null;
        if (! $id) {
            return null;
        }

        return DB::table('merchants')->where('id', $id)->first();
    }

    private function isOnboardingComplete(Request $request, ?object $merchant): bool
    {
        if ($merchant && ! empty($merchant->onboarding_completed_at)) {
            return true;
        }

        return (bool) $request->session()->get('onboarding_complete');
    }
}

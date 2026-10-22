<?php

namespace App\Http\Controllers;

use App\Jobs\ActivatePendingWebhookUrlJob;

use App\Models\Merchant;
use App\Models\PaypalPayoutRecipient;
use App\Models\WebhookEndpoint;
use App\Services\Security\PayoutAllowList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MerchantPanelController extends Controller
{
    public function onboarding()
    {
        return view('merchant.onboarding');
    }

    public function settings(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        $recipients = $merchant ? PaypalPayoutRecipient::query()->where('merchant_id', $merchant->id)->orderByDesc('created_at')->get() : collect();
        $endpoints = $merchant ? WebhookEndpoint::query()->where('merchant_id', $merchant->id)->get() : collect();
        $project = \App\Support\MerchantProject::current();

        return view('merchant.settings', compact('merchant', 'recipients', 'endpoints', 'project'));
    }

    public function analytics(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        $merchantId = $merchant?->id;

        $revenue = [];
        $charity = [];
        $secondChance = [];
        $resale = [];

        if ($merchantId) {
            $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
            foreach ($days as $day) {
                $revenue[] = [
                    'date' => $day,
                    'amount' => (int) DB::table('transactions')
                        ->where('merchant_id', $merchantId)
                        ->where('type', 'charge')
                        ->where('status', 'completed')
                        ->whereDate('created_at', $day)
                        ->sum('amount_minor'),
                ];
                $charity[] = [
                    'date' => $day,
                    'amount' => (int) DB::table('transactions')
                        ->where('merchant_id', $merchantId)
                        ->where('type', 'charity_payout')
                        ->where('status', 'completed')
                        ->whereDate('created_at', $day)
                        ->sum('amount_minor'),
                ];
            }
            $secondChance = [
                'held' => DB::table('transactions')->where('merchant_id', $merchantId)->where('status', 'held')->count(),
                'triggered' => DB::table('transactions')->where('merchant_id', $merchantId)->whereNotNull('second_chance_triggered_at')->count(),
                'settled' => DB::table('transactions')->where('merchant_id', $merchantId)->where('status', 'settled')->count(),
            ];
            $resale = [
                'active' => DB::table('resale_listings')->where('merchant_id', $merchantId)->where('status', 'active')->count(),
                'sold' => DB::table('resale_listings')->where('merchant_id', $merchantId)->where('status', 'sold')->count(),
            ];
        }

        return view('merchant.analytics', compact('merchant', 'revenue', 'charity', 'secondChance', 'resale'));
    }

    public function exportTransactions(Request $request): StreamedResponse
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);

        $filename = 'transactions-'.$merchant->id.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($merchant) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'type', 'status', 'email', 'amount_minor', 'currency', 'provider_reference', 'created_at']);
            DB::table('transactions')->where('merchant_id', $merchant->id)->orderByDesc('created_at')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->id, $r->type, $r->status, $r->email, $r->amount_minor, $r->currency, $r->provider_reference, $r->created_at]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportAudit(Request $request): StreamedResponse
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);

        $filename = 'audit-'.$merchant->id.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($merchant) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'event', 'auditable_type', 'auditable_id', 'actor_type', 'actor_id', 'created_at', 'hash']);
            DB::table('audit_logs')->where('merchant_id', $merchant->id)->orderByDesc('id')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->id, $r->event, $r->auditable_type, $r->auditable_id, $r->actor_type, $r->actor_id, $r->created_at, $r->hash]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function rotateKey(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);

        $keyData = Merchant::issueApiKey('live');
        $merchant->update([
            'api_key_prefix' => $keyData['prefix'],
            'api_key_hash' => $keyData['hash'],
            'publishable_key' => $keyData['publishable'],
        ]);

        return back()->with('new_api_key', $keyData['key'])->with('status', 'API key rotated. Copy it now — it will not be shown again.');
    }


    public function requestPayoutRecipient(Request $request, PayoutAllowList $allowList)
    {
        $merchant = $this->resolveMerchant($request);
        $user = $request->user('merchant_web');
        abort_unless($merchant && $user, 403);
        $data = $request->validate([
            'email' => 'required|email',
            'purpose' => 'required|in:charity,resale_seller,refund,general',
            'label' => 'nullable|string|max:120',
        ]);
        $r = $allowList->requestAdd($merchant, $user, $data['email'], $data['purpose'], $data['label'] ?? null);
        return back()->with('status', "Payout recipient queued. Active after {$r->activates_at} (24h cooling-off).");
    }

    public function requestWebhookUrlChange(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 403);
        $data = $request->validate([
            'endpoint_id' => 'required|uuid',
            'url' => 'required|url|max:2048',
        ]);
        $ep = WebhookEndpoint::query()->where('merchant_id', $merchant->id)->where('id', $data['endpoint_id'])->firstOrFail();
        $ep->update([
            'pending_url' => $data['url'],
            'pending_url_activates_at' => now()->addHours(24),
            'change_confirmation_code' => (string) random_int(100000, 999999),
        ]);
        // Email confirmation code (no TOTP/2FA app required)
        $code = (string) random_int(100000, 999999);
        $ep->change_confirmation_code = $code;
        $ep->save();
        \Illuminate\Support\Facades\Mail::to($merchant->email)->send(new \App\Mail\PlainHtmlMail(
            'Confirm webhook URL change',
            '<p>Confirm changing webhook to <b>'.e($data['url']).'</b>.</p><p>Code: <b>'.e($code).'</b></p><p>Change activates after cooling-off if confirmed.</p>'
        ));
        return back()->with('status', 'Confirmation code emailed to merchant owner. Enter it below. 24h cooling-off still applies.');
    }

    public function confirmWebhookUrlChange(\Illuminate\Http\Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 403);
        $data = $request->validate([
            'endpoint_id' => 'required|uuid',
            'code' => 'required|string|size:6',
        ]);
        $ep = \App\Models\WebhookEndpoint::query()->where('merchant_id', $merchant->id)->where('id', $data['endpoint_id'])->firstOrFail();
        if (!$ep->change_confirmation_code || !hash_equals($ep->change_confirmation_code, $data['code'])) {
            return back()->withErrors(['code' => 'Invalid confirmation code.']);
        }
        $ep->update(['change_confirmation_code' => null]); // pending_url remains until activates_at
        if ($ep->pending_url_activates_at) {
            ActivatePendingWebhookUrlJob::dispatch($ep->id)->delay($ep->pending_url_activates_at);
        } else {
            ActivatePendingWebhookUrlJob::dispatch($ep->id);
        }
        return back()->with('status', 'Webhook change confirmed. URL switches after cooling-off.');
    }

    public function deactivate(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 403);
        $merchant->update([
            'subscription_status' => 'deactivated',
            'deactivated_at' => now(),
            'purge_after' => now()->addDays(90),
            'is_active' => false,
        ]);
        return redirect()->route('login')->with('status', 'Merchant deactivated. Data retained 90 days then purged (GDPR).');
    }


    public function wallet(Request $request)
    {
        $merchant = $this->resolveMerchant($request);

        return view('wallet.index', [
            'merchant' => $merchant,
            'merchantName' => $merchant?->name ?? 'Merchant',
        ]);
    }

    public function subscriptions(Request $request)
    {
        $merchant = $this->resolveMerchant($request);

        return view('subscriptions.index', [
            'merchant' => $merchant,
            'merchantName' => $merchant?->name ?? 'Merchant',
        ]);
    }


    public function uploadLogo(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);

        // No SVG: can embed scripts. Images only, re-encoded path, size cap.
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ]);

        $file = $request->file('logo');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getRealPath()) ?: '';
        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        abort_unless(in_array($mime, $allowed, true), 422, 'Invalid image type.');

        // Reject polyglot / double-extension names
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        abort_unless(in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true), 422, 'Invalid extension.');

        if (! empty($merchant->logo_path)) {
            Storage::disk('public')->delete($merchant->logo_path);
        }

        // Random filename — never trust client original name
        $path = $file->storeAs(
            'merchant-logos/'.$merchant->id,
            bin2hex(random_bytes(16)).'.'.$ext,
            'public'
        );
        $merchant->update(['logo_path' => $path]);

        return back()->with('status', 'Company logo updated.');
    }

    public function removeLogo(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);

        if (! empty($merchant->logo_path)) {
            Storage::disk('public')->delete($merchant->logo_path);
            $merchant->update(['logo_path' => null]);
        }

        return back()->with('status', 'Company logo removed.');
    }

    private function resolveMerchant(Request $request): ?Merchant
    {
        if ($m = $request->attributes->get('merchant')) {
            return $m instanceof Merchant ? $m : Merchant::query()->find($m);
        }
        $user = $request->user('merchant_web');
        return $user?->merchant;
    }
}

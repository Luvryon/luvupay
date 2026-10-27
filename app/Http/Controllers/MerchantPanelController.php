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
        $recipients = collect();
        $endpoints = collect();
        try {
            if ($merchant) {
                $recipients = PaypalPayoutRecipient::query()->where('merchant_id', $merchant->id)->orderByDesc('created_at')->get();
                $endpoints = WebhookEndpoint::query()->where('merchant_id', $merchant->id)->get();
            }
        } catch (\Throwable $e) {
            report($e);
        }
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

    public function developers(Request $request)
    {
        return redirect()->route('analytics');
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

        // Accept both classic form POST and AJAX FormData.
        // Do NOT rely on Laravel "image" rule alone — Hostinger often lacks GD/Imagick.
        $request->validate([
            'logo' => ['required', 'file', 'max:2048'],
        ]);

        $file = $request->file('logo');
        if (! $file || ! $file->isValid()) {
            return $this->logoResponse($request, false, 'Upload failed (invalid file).', 422);
        }

        $real = $file->getRealPath();
        if (! is_string($real) || ! is_readable($real)) {
            return $this->logoResponse($request, false, 'Upload failed (unreadable temp file).', 422);
        }

        // MIME from content, not client header
        $mime = '';
        if (class_exists(\finfo::class)) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($real) ?: '';
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($real);
        }
        // getimagesize works without GD for jpeg/png; webp may need GD
        $info = @getimagesize($real);
        if (is_array($info) && ! empty($info['mime'])) {
            $mime = $info['mime'];
        }

        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (! isset($allowed[$mime])) {
            return $this->logoResponse($request, false, 'Only PNG, JPEG, or WebP allowed (no SVG).', 422);
        }
        $ext = $allowed[$mime];

        // Ensure public disk root exists and is writable
        $disk = Storage::disk('public');
        $dir = 'merchant-logos/'.$merchant->id;
        try {
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }
        } catch (\Throwable $e) {
            @mkdir(storage_path('app/public/'.$dir), 0755, true);
        }

        if (! empty($merchant->logo_path)) {
            try {
                $disk->delete($merchant->logo_path);
            } catch (\Throwable $e) {
                //
            }
        }

        $filename = bin2hex(random_bytes(16)).'.'.$ext;
        // Binary-safe put (avoids storeAs quirks on some hosts)
        $path = $dir.'/'.$filename;
        $bytes = file_get_contents($real);
        if ($bytes === false || $bytes === '') {
            return $this->logoResponse($request, false, 'Could not read uploaded file.', 422);
        }
        // Block embedded PHP / script polyglots in first bytes (defense in depth)
        if (preg_match('/<\?php|<script/i', substr($bytes, 0, 512))) {
            return $this->logoResponse($request, false, 'File rejected by security scan.', 422);
        }

        $ok = $disk->put($path, $bytes, 'public');
        if (! $ok) {
            // Last resort: direct filesystem write
            $abs = storage_path('app/public/'.$path);
            @mkdir(dirname($abs), 0755, true);
            if (file_put_contents($abs, $bytes) === false) {
                return $this->logoResponse($request, false, 'Storage not writable. Run: php artisan storage:link && chmod -R 775 storage', 500);
            }
        }

        $merchant->forceFill(['logo_path' => $path])->save();

        $url = \App\Support\MerchantBranding::logoUrl($merchant->fresh());

        return $this->logoResponse($request, true, 'Company logo updated.', 200, [
            'logo_path' => $path,
            'logo_url' => $url,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logoResponse(Request $request, bool $ok, string $message, int $status = 200, array $extra = [])
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(array_merge(['ok' => $ok, 'message' => $message], $extra), $status);
        }

        if (! $ok) {
            return back()->with('toast_error', $message);
        }

        return back()->with('toast_success', $message);
    }

    public function saveInvoiceDetails(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'billing_address' => ['nullable', 'string', 'max:400'],
            'tax_id' => ['nullable', 'string', 'max:60'],
        ]);
        $merchant->forceFill([
            'name' => trim(strip_tags($data['name'])),
            'billing_address' => isset($data['billing_address']) ? trim(strip_tags($data['billing_address'])) : null,
            'tax_id' => isset($data['tax_id']) ? trim(strip_tags($data['tax_id'])) : null,
        ])->save();

        return back()->with('toast_success', 'Invoice details saved. New invoices and emails will use them.');
    }

    public function savePayoutEmail(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);
        $user = $request->user('merchant_web') ?? $request->user();
        $data = $request->validate([
            'payout_email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'string'],
        ]);
        // Changing where money is sent is sensitive: re-authenticate.
        if (! $user || ! \Illuminate\Support\Facades\Hash::check($data['password'], (string) $user->password)) {
            return back()->withErrors(['password' => 'Password is incorrect.'])->withInput(['payout_email' => $data['payout_email']]);
        }
        $merchant->forceFill(['payout_email' => strtolower(trim($data['payout_email']))])->save();

        return back()->with('toast_success', 'Payout PayPal email saved. Withdrawals now go to this account.');
    }

    public function removeLogo(Request $request)
    {
        $merchant = $this->resolveMerchant($request);
        abort_unless($merchant, 404);

        if (! empty($merchant->logo_path)) {
            Storage::disk('public')->delete($merchant->logo_path);
            $merchant->update(['logo_path' => null]);
        }

        return back()->with('toast_success', 'Company logo removed.');
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

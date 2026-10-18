<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MerchantExportController
{
    /** GET /api/v1/export — portable dump of merchant mapping + transactions + audit (no vendor lock-in). */
    public function __invoke(Request $request): StreamedResponse
    {
        $merchantId = $request->attributes->get('merchant_id');
        abort_unless($merchantId, 401);

        return response()->streamDownload(function () use ($merchantId) {
            $out = fopen('php://output', 'w');
            fwrite($out, json_encode([
                'exported_at' => now()->toIso8601String(),
                'merchant' => DB::table('merchants')->where('id', $merchantId)->first(),
                'subscriptions' => DB::table('subscriptions')->where('merchant_id', $merchantId)->get(),
                'transactions' => DB::table('transactions')->where('merchant_id', $merchantId)->get(),
                'resale_listings' => DB::table('resale_listings')->where('merchant_id', $merchantId)->get(),
                'audit_logs' => DB::table('audit_logs')->where('merchant_id', $merchantId)->orderBy('id')->get(),
                'trust_scores' => DB::table('user_trust_scores')->where('merchant_id', $merchantId)->get(),
                'note' => 'Canonical money movement also lives at the payment provider (PayPal). LuvuPay stores mapping + metadata.',
            ], JSON_PRETTY_PRINT));
            fclose($out);
        }, 'luvupay-export-'.$merchantId.'.json', ['Content-Type' => 'application/json']);
    }
}

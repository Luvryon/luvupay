<?php

namespace App\Http\Controllers;

use App\Support\MerchantBranding;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class BrandController
{
    public function logo(string $id): Response
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);
        $merchant = DB::table('merchants')->where('id', $id)->first(['id', 'logo_path']);
        $logo = $merchant ? MerchantBranding::logo($merchant) : null;
        abort_unless($logo, 404);

        return response($logo['bytes'], 200, [
            'Content-Type' => $logo['mime'],
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}

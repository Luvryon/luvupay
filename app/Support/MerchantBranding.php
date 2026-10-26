<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Merchant branding used by the console, invoices (HTML + PDF), emails and the hosted checkout.
 * Logos are served through a route (/brand/logo/{id}) instead of /storage/*, so they work even when
 * `php artisan storage:link` was never run on the host.
 */
final class MerchantBranding
{
    /** @return array{bytes:string,mime:string}|null */
    public static function logo(object $merchant): ?array
    {
        $path = (string) ($merchant->logo_path ?? '');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        $bytes = null;
        try {
            $disk = Storage::disk('public');
            if ($disk->exists($path)) {
                $bytes = $disk->get($path);
            }
        } catch (\Throwable $e) {
            // fall through to direct read
        }
        if ($bytes === null) {
            $abs = storage_path('app/public/'.$path);
            $bytes = is_file($abs) ? @file_get_contents($abs) : null;
        }
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };

        return $mime ? ['bytes' => $bytes, 'mime' => $mime] : null;
    }

    public static function logoUrl(object $merchant): ?string
    {
        if (empty($merchant->logo_path) || self::logo($merchant) === null) {
            return null; // no file on disk (e.g. wiped storage): fall back to the initials badge instead of a broken image
        }

        return route('brand.logo', ['id' => $merchant->id, 'v' => substr(md5((string) $merchant->logo_path), 0, 8)]);
    }

    /** Self-contained data URI (for emails and anywhere a CSP blocks remote images). */
    public static function logoDataUri(object $merchant): ?string
    {
        $l = self::logo($merchant);

        return $l ? 'data:'.$l['mime'].';base64,'.base64_encode($l['bytes']) : null;
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $a = mb_strtoupper(mb_substr($parts[0] ?? 'M', 0, 1));
        $b = isset($parts[1]) ? mb_strtoupper(mb_substr($parts[1], 0, 1)) : mb_strtoupper(mb_substr($parts[0] ?? 'M', 1, 1));

        return $a.$b;
    }
}

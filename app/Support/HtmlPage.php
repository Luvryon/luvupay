<?php

namespace App\Support;

use Illuminate\Http\Response;

/** Tiny self-contained, locked-down HTML page for the public signed-link screens (no end-user accounts exist). */
final class HtmlPage
{
    public static function render(string $title, string $bodyHtml, int $status = 200): Response
    {
        $t = e($title);
        $html = <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$t}</title><style>
body{margin:0;font:16px/1.5 system-ui,sans-serif;background:#f8fafc;color:#0f172a;display:grid;place-items:center;min-height:100vh;padding:16px}
.c{max-width:460px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px;box-shadow:0 10px 30px rgba(15,23,42,.08)}
h1{font-size:22px;margin:0 0 8px}p{color:#475569}table{width:100%;border-collapse:collapse;margin:16px 0}td{padding:8px 0;border-bottom:1px solid #f1f5f9}td+td{text-align:right;font-weight:600}
button{width:100%;background:linear-gradient(90deg,#4f46e5,#10b981);color:#fff;border:0;border-radius:12px;padding:13px;font-size:16px;font-weight:700;cursor:pointer}
small{color:#94a3b8}</style></head><body><div class="c">{$bodyHtml}</div></body></html>
HTML;

        return response($html, $status)->withHeaders([
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'",
        ]);
    }
}

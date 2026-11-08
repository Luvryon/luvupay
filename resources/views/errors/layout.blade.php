@php
    $code = trim($__env->yieldContent('code'));
    $zeroMid = substr($code, 1, 1) === '0';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="light">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'">
    <title>@yield('headline') · LuvuPay</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="/css/errors.css?v={{ @filemtime(public_path('css/errors.css')) }}">
</head>
<body class="err">
    {{-- Quiet background: a dotted payment path with a few coins and two cards --}}
    <svg class="err-bg" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
        <path d="M-40 820 C 220 700, 360 880, 640 780 S 1060 640, 1480 740" fill="none" stroke="#c8a45d" stroke-opacity=".55" stroke-width="3" stroke-linecap="round" stroke-dasharray="1 14"/>
        <path d="M-40 180 C 260 260, 420 80, 760 170 S 1180 300, 1480 150" fill="none" stroke="#09027b" stroke-opacity=".16" stroke-width="3" stroke-linecap="round" stroke-dasharray="1 14"/>
        <g fill="#efdca8" stroke="#c8a45d" stroke-width="2">
            <circle cx="292" cy="792" r="15"/><circle cx="1007" cy="685" r="17"/>
            <circle cx="345" cy="171" r="10" fill="#fff"/><circle cx="1135" cy="250" r="13" fill="#fff"/>
        </g>
        <g fill="none" stroke="#09027b" stroke-opacity=".2" stroke-width="2.5" stroke-linejoin="round">
            <g transform="translate(96 330) rotate(-14)"><rect width="150" height="96" rx="14"/><path d="M0 28h150"/><path d="M18 64h44" stroke-linecap="round"/></g>
            <g transform="translate(1196 640) rotate(11)"><rect width="150" height="96" rx="14"/><path d="M0 28h150"/><path d="M18 64h44" stroke-linecap="round"/></g>
        </g>
    </svg>

    <header class="err-top">
        <a class="err-brand" href="/"><img src="{{ asset('images/logo-luvupay.webp') }}" width="32" height="32" alt="">LuvuPay</a>
    </header>

    <main class="err-main">
        <div class="err-code{{ $zeroMid ? '' : ' err-code--stack' }}" role="img" aria-label="Error {{ $code }}">
            <span class="err-digit" aria-hidden="true">{{ $zeroMid ? substr($code, 0, 1) : '' }}</span>
            <svg class="err-coin" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
                <defs>
                    <linearGradient id="err-gold" x1="14" y1="8" x2="106" y2="112" gradientUnits="userSpaceOnUse">
                        <stop offset="0" stop-color="#efdca8"/><stop offset=".55" stop-color="#c8a45d"/><stop offset="1" stop-color="#a98342"/>
                    </linearGradient>
                </defs>
                <circle cx="60" cy="60" r="57" fill="url(#err-gold)"/>
                <circle cx="60" cy="60" r="47" fill="#fffaf0" stroke="#a98342" stroke-opacity=".45" stroke-width="2"/>
                <path d="M26 46a38 38 0 0 1 22-18" fill="none" stroke="#fff" stroke-opacity=".9" stroke-width="4" stroke-linecap="round"/>
                @yield('glyph')
            </svg>
            <span class="err-digit" aria-hidden="true">{{ $zeroMid ? substr($code, 2, 1) : '' }}</span>
            <span class="err-digits" aria-hidden="true">{{ $zeroMid ? '' : $code }}</span>
        </div>

        <h1>@yield('headline')</h1>
        <p>@yield('message')</p>

        <div class="err-actions">
            <a class="err-btn is-primary" href="@yield('primary_href', '/')">@yield('primary_label', 'Back to home')</a>
            <a class="err-btn" href="@yield('secondary_href', '/support')">@yield('secondary_label', 'Contact support')</a>
        </div>
    </main>
</body>
</html>

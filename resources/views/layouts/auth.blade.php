<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-luvupay-v2.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-luvupay-v2.png') }}">

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LuvuPay')</title>
    @include('partials.assets')
</head>
<body class="min-h-full bg-wash text-ink antialiased relative overflow-x-hidden">
<div class="pointer-events-none absolute inset-x-0 top-0 h-[520px] lp-grid"></div>
<div class="pointer-events-none absolute left-1/2 top-[-120px] h-[420px] w-[720px] -translate-x-1/2 lp-glow"></div>
<main class="relative min-h-screen flex flex-col items-center justify-center px-5 py-14">
    <a href="/" class="mb-8 flex items-center gap-2.5 font-semibold tracking-tight text-lg" aria-label="LuvuPay home">
        @include('partials.logo', ['size' => 32]) LuvuPay
    </a>
    <div class="w-full max-w-[26rem]">
        @yield('auth')
    </div>
    <p class="mt-8 text-xs text-muted">
        <a href="/support" class="hover:text-ink">Support</a><span class="mx-2 text-line">|</span>
        <a href="/privacy" class="hover:text-ink">Privacy</a><span class="mx-2 text-line">|</span>
        <a href="/terms" class="hover:text-ink">Terms</a>
    </p>
</main>
</body>
</html>

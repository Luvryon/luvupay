<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'">
    <title>@yield('code') · LuvuPay</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="/css/ui.css">
</head>
<body class="grid min-h-full place-items-center px-5">
    <main class="w-full max-w-md text-center">
        <x-ui.logo :size="44" class="mx-auto mb-6" />
        <p class="bg-gradient-to-br from-white to-brand-soft bg-clip-text text-7xl font-black tracking-tight text-transparent">@yield('code')</p>
        <h1 class="mt-3 text-xl font-semibold text-white">@yield('headline')</h1>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">@yield('message')</p>
        <div class="mt-7 flex flex-wrap justify-center gap-3">
            <x-ui.button href="/" icon="arrow">Back home</x-ui.button>
            <x-ui.button variant="secondary" href="/support">Contact support</x-ui.button>
        </div>
    </main>
</body>
</html>

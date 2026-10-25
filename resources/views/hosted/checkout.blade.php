<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $mode === 'redeem' ? 'Redeem access' : 'Checkout' }} · {{ $productName }}</title>
    <link rel="stylesheet" href="/css/hosted.css?v={{ @filemtime(public_path('css/hosted.css')) }}">
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 font-sans text-slate-900 antialiased">
<main x-data="hostedPage()" x-cloak data-cfg='@json($cfg, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT)'
      class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">

    {{-- Header --}}
    <header class="px-6 pt-6 pb-2 flex items-center gap-3">
        @if($brand['logo'])
            <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }} logo" class="h-11 w-11 rounded-full object-cover border border-slate-200 bg-white">
        @else
            <div class="h-11 w-11 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-bold" aria-hidden="true">{{ $brand['initials'] }}</div>
        @endif
        <h1 class="text-xl font-bold text-slate-900">{{ $productName }}</h1>
    </header>

    {{-- Unavailable token --}}
    <section x-show="mode === 'unavailable'" class="px-6 pb-8 pt-4">
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 text-sm">
            <p class="font-semibold">This token is no longer available</p>
            <p class="mt-1">It was already redeemed or the access window has ended.</p>
        </div>
        <a href="{{ $cfg['storeUrl'] }}" class="mt-5 block text-center text-sm font-semibold text-indigo-600 hover:text-indigo-700">Browse other available access →</a>
    </section>

    {{-- Success --}}
    <section x-show="done" class="px-6 pb-8 pt-4 text-center">
        <div class="mx-auto h-14 w-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
            <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
        </div>
        <h2 class="mt-4 text-lg font-bold text-slate-900" x-text="mode === 'redeem' ? 'Access transferred' : 'Subscription active'"></h2>
        <p class="mt-1 text-sm text-slate-500">Your invoice was emailed to <span class="font-semibold text-slate-700" x-text="email"></span>.</p>
        <a x-bind:href="invoiceUrl" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-slate-900 py-3 text-sm font-semibold text-white hover:bg-slate-800">View invoice</a>
    </section>

    <section x-show="mode !== 'unavailable' && !done" class="px-6 pb-6 pt-3">
        {{-- Redeem: smart token --}}
        <div x-show="mode === 'redeem'">
            <span class="bg-emerald-100 text-emerald-700 text-xs font-bold px-3 py-1 rounded-full mb-4 inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                Verified Resale Token
            </span>
            @if($token)
            <div class="bg-indigo-50 border border-indigo-100 p-4 rounded-xl mb-4">
                <p class="text-sm text-slate-700 leading-relaxed">You are redeeming <strong class="text-slate-900">{{ $token['days'] }} days</strong> of {{ $productName }}, transferred from user <strong class="text-slate-900">{{ $token['seller'] }}</strong></p>
                <p class="mt-2 font-mono text-[11px] text-indigo-500 break-all">{{ $token['token'] }}</p>
            </div>
            @endif
        </div>

        {{-- Checkout: standard plan --}}
        <div x-show="mode === 'checkout'" class="mb-4">
            <p class="text-sm text-slate-600">{{ $planLine }}</p>
        </div>

        {{-- Buyer details --}}
        <div class="space-y-3 mb-4">
            <div x-show="mode === 'checkout'">
                <label for="h-name" class="block text-xs font-semibold text-slate-600 mb-1">Full name</label>
                <input id="h-name" type="text" x-model="name" autocomplete="name" maxlength="120" placeholder="Jane Doe"
                       class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
            </div>
            <div>
                <label for="h-email" class="block text-xs font-semibold text-slate-600 mb-1">Email for your receipt</label>
                <input id="h-email" type="email" x-model="email" autocomplete="email" maxlength="190" placeholder="you@example.com" required
                       class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
            </div>
        </div>

        {{-- Order summary --}}
        <div class="bg-slate-50 p-4 rounded-xl space-y-3 text-sm">
            <div class="flex justify-between text-slate-600"><span>Subtotal</span><span>{{ $subtotal }}</span></div>
            <div class="flex justify-between text-xs text-slate-400"><span>LuvuPay Platform Fee</span><span>{{ $fee }}</span></div>
            <div class="flex justify-between font-bold text-slate-900 border-t border-slate-200 pt-3 mt-3"><span>Total due today</span><span>{{ $total }}</span></div>
        </div>

        <p x-show="error" x-text="error" class="mt-4 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700" role="alert"></p>

        {{-- PayPal --}}
        <button type="button" x-on:click="pay()" x-bind:disabled="busy"
                class="bg-[#0070ba] hover:bg-[#005ea6] text-white font-bold py-3.5 rounded-xl flex items-center justify-center gap-2 transition-all shadow-md hover:shadow-lg mt-6 w-full disabled:opacity-60 disabled:cursor-not-allowed">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.1 21.6l.3-1.9H4.6L6.9 5.2A.7.7 0 017.6 4.6h6c2 0 3.4.4 4.1 1.3.7.8.9 1.7.7 3-.4 3.4-2.6 5.2-6.2 5.2H10l-.8 5.1-.3 2.4zM18.8 7.9c0 .2 0 .4-.1.6-.5 3.1-2.5 4.6-5.9 4.6h-1.4l-.9 6.2H7.4l-.2 1.2a.5.5 0 00.5.6h3.4a.6.6 0 00.6-.5l.7-4.3h2.2c3 0 4.6-1.6 5.1-4.7.3-1.5.1-2.6-.9-3.7z"/></svg>
            <span x-text="busy ? 'Processing…' : (mode === 'redeem' ? 'Pay {{ $total }} with PayPal' : 'Pay with PayPal')"></span>
        </button>

        <p class="text-[10px] text-slate-400 text-center mt-4 flex items-center justify-center gap-1">
            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
            Secure checkout powered by LuvuPay &amp; PayPal
        </p>
        <p class="text-[10px] text-slate-300 text-center mt-1">{{ $driver }}</p>
    </section>

    <footer class="px-6 pb-6">
        <p class="text-xs text-slate-400 text-center mt-2">Need help? <a href="{{ url('/support') }}" class="underline hover:text-slate-600">Contact LuvuPay Support</a></p>
    </footer>
</main>
<script src="/js/hosted.js?v={{ @filemtime(public_path('js/hosted.js')) }}" defer></script>
<script src="/js/alpine.min.js" defer></script>
</body>
</html>

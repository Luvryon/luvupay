@php
    $user = auth('merchant_web')->user();
    $allows = fn (?string $perm) => $perm === null || ($user && $user->allows($perm));
    $safeRoute = function (string $name, string $fallback = '#') {
        try { return \Illuminate\Support\Facades\Route::has($name) ? route($name) : $fallback; }
        catch (\Throwable $e) { return $fallback; }
    };
    $item = fn (string $route, string $label, string $icon, string $match, ?string $can = null) => $allows($can)
        ? ['href' => $safeRoute($route), 'label' => $label, 'icon' => $icon, 'active' => request()->routeIs($match)] : null;
    $groups = [
        'Workspace' => array_filter([
            $item('dashboard', 'Overview', 'grid', 'dashboard'),
            $item('wallet', 'Wallet & Transactions', 'card', 'wallet'),
            $item('subscriptions', 'Subscriptions', 'users', 'subscriptions'),
            $item('resale', 'Resale', 'store', 'resale'),
            $item('analytics', 'Analytics', 'chart', 'analytics', 'analytics.read'),
        ]),
        'Account' => array_filter([
            $item('team.index', 'Team', 'users', 'team.*', 'team.manage'),
            $item('security.index', 'Security', 'shield', 'security.*'),
            $item('settings', 'Settings', 'cog', 'settings*', 'settings.read'),
        ]),
        'Resources' => [
            ['href' => url('/marketplace'), 'label' => 'Marketplace', 'icon' => 'store', 'active' => false, 'external' => true],
            ['href' => url('/m/'.($user?->merchant_id ?? '')), 'label' => 'Public merchant page', 'icon' => 'store', 'active' => false, 'external' => true],
            ['href' => url('/docs'), 'label' => 'Documentation', 'icon' => 'book', 'active' => false, 'external' => true],
            ['href' => url('/status'), 'label' => 'System status', 'icon' => 'pulse', 'active' => false, 'external' => true],
        ],
    ];
    $driver = config('luvupay.payment.driver', 'mock');
    [$envLabel, $envTone] = app()->environment('production') ? ['Live', 'ok'] : ($driver === 'mock' ? ['Mock', 'warn'] : ['Sandbox', 'brand']);
    $displayName = $user->name ?? $user->email ?? 'Merchant';
    $merchantLogo = null;
    try {
        $m = $user?->merchant ?? null;
        if ($m && ! empty($m->logo_path)) {
            $merchantLogo = \App\Support\MerchantBranding::logoUrl($m);
        }
    } catch (\Throwable $e) {}
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('partials.favicon')

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'self'; object-src 'none'">
    <title>@yield('title', 'Console') · LuvuPay</title>
    <link rel="stylesheet" href="/css/ui.css?v={{ @filemtime(public_path('css/ui.css')) }}">
    @stack('head')
    <link rel="stylesheet" href="/css/console.css?v={{ @filemtime(public_path('css/console.css')) }}">
    <link rel="stylesheet" href="/css/loader.css?v={{ @filemtime(public_path('css/loader.css')) }}">
    <link rel="stylesheet" href="/css/toast.css?v={{ @filemtime(public_path('css/toast.css')) }}">
</head>
<body class="console-light" x-data="{ drawer: false }" x-on:keydown.escape.window="drawer = false">
<a href="#main" class="sr-only">Skip to content</a>
<div class="app">
    <aside class="side">
        <a href="{{ route('dashboard') }}" class="brand"><x-ui.logo :size="30" /> LuvuPay</a>
        <x-console.nav :groups="$groups" />
        <div class="side-foot">
            <div class="row" style="gap:8px"><span style="font-size:.8rem;color:var(--muted)">Environment</span><span class="pill {{ $envTone }}">{{ $envLabel }}</span></div>
            <div class="who">
                @if(!empty($merchantLogo))
                    <img src="{{ $merchantLogo }}" alt="" class="merchant-logo" style="width:36px;height:36px;border-radius:10px;object-fit:contain;border:1px solid var(--line);background:#fff;flex-shrink:0" />
                @endif<span class="avatar">{{ strtoupper(substr($displayName, 0, 1)) }}</span><div style="min-width:0"><b>{{ $displayName }}</b><span>{{ $user->email ?? '' }}</span></div></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="c-btn c-btn-sm" style="width:100%">Sign out</button></form>
        </div>
    </aside>

    <div class="drawer" x-show="drawer" x-cloak style="display:none">
        <div class="panel">
            <div class="row" style="padding:0 20px;height:68px;border-bottom:1px solid var(--line);flex-wrap:nowrap"><span class="brand" style="padding:0;height:auto"><x-ui.logo :size="26" /> LuvuPay</span><button type="button" class="burger" x-on:click="drawer = false" aria-label="Close"><x-ui.icon name="x" /></button></div>
            <x-console.nav :groups="$groups" />
            <form method="post" action="{{ route('logout') }}" class="side-foot">@csrf<button type="submit" class="c-btn c-btn-sm">Sign out</button></form>
        </div>
        <div class="scrim" x-on:click="drawer = false"></div>
    </div>

    <div class="main">
        <header class="top">
            <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1">
                <button type="button" class="burger" x-on:click="drawer = true" aria-label="Open menu"><x-ui.icon name="menu" /></button>
                <p class="crumb" style="margin:0;flex-shrink:0">Console / <b>@yield('heading', 'Overview')</b></p>
                <div class="nav-search" id="luvupay-search" style="position:relative;flex:1;min-width:180px;max-width:360px;margin-left:8px">
                    <button type="button" class="ns-trigger" id="luvupay-search-btn" aria-label="Open search">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                        <span>Search…</span>
                        <kbd>Ctrl&nbsp;+&nbsp;K</kbd>
                    </button>
                    <div class="ns-panel" id="luvupay-search-panel" hidden>
                        <div class="ns-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                            <input type="search" id="luvupay-search-input" placeholder="Search pages, docs, subscribers…" autocomplete="off" />
                            <kbd id="luvupay-search-esc" style="cursor:pointer">Esc</kbd>
                        </div>
                        <ul class="ns-list" id="luvupay-search-list"></ul>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">@yield('header_actions')<span class="pill {{ $envTone }}">{{ $envLabel }}</span></div>
        </header>
        <main id="main" class="page">
            {{-- status/success messages rendered as LuvuToast --}}
            @if($errors->any())<div class="c-alert" style="margin-bottom:24px;background:#fff0f1;border-color:#f7c3c8;color:#8f1d27">@if($errors->count() === 1){{ $errors->first() }}@else<ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</div>@endif
            @yield('content')
        </main>
    </div>
</div>

<script src="/js/loader.js?v={{ @filemtime(public_path('js/loader.js')) }}"></script>
<script src="/js/ui.js?v={{ @filemtime(public_path('js/ui.js')) }}" defer></script>



@stack('scripts')


@php
    $__obMerchant = Auth::guard('merchant_web')->user()?->merchant;
    $__obProject = \App\Support\MerchantProject::current();
    $__obHasProject = $__obProject->isSet();
    $__obHasPaypal = filled($__obMerchant?->paypal_merchant_id ?? null);
    $__obDriver = (string) config('luvupay.payment.driver', 'mock');
@endphp
<div id="luvu-onboarding" hidden
     data-has-project="{{ $__obHasProject ? '1' : '0' }}"
     data-has-paypal="{{ $__obHasPaypal ? '1' : '0' }}"
     data-payment-driver="{{ $__obDriver }}"></div>



@php
  $__toastQueue = [];
  if (session('toast_success')) $__toastQueue[] = ['type' => 'success', 'title' => session('toast_success')];
  if (session('toast_error')) $__toastQueue[] = ['type' => 'error', 'title' => session('toast_error')];
  if (session('toast_warning')) $__toastQueue[] = ['type' => 'warning', 'title' => session('toast_warning')];
  if (session('toast_info')) $__toastQueue[] = ['type' => 'info', 'title' => session('toast_info')];
  // migrate legacy flash labels
  if (session('status') && !session('toast_success')) $__toastQueue[] = ['type' => 'success', 'title' => session('status')];
  if (session('error') && !session('toast_error')) $__toastQueue[] = ['type' => 'error', 'title' => session('error')];
@endphp
<script type="application/json" id="toast-queue">@json($__toastQueue)</script>
<script src="/js/toast-queue.js" defer></script>


<script src="/js/toast.js?v={{ @filemtime(public_path('js/toast.js')) }}" defer></script>
<script src="/js/onboarding-track.js?v={{ @filemtime(public_path('js/onboarding-track.js')) }}" defer></script>

<script src="/js/dom-safe.js?v={{ @filemtime(public_path('js/dom-safe.js')) }}" defer></script>
<script src="/js/console-search.js" defer></script>
<script src="/js/alpine.min.js" defer></script>
</body>
</html>

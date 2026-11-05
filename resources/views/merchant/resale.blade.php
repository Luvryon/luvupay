@extends('layouts.console')
@section('title', 'Resale')
@section('heading', 'Resale')

@section('content')
<div class="stack" x-data="resalePage()">
    <div class="row">
        <div>
            <h1 class="page-title">Resale marketplace</h1>
            <p class="page-sub">Access that your subscribers resell appears on your public merchant page. Buyers click, pay with PayPal, and the token redeems instantly</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="c-btn c-btn-brand" x-bind:href="d.store_url" target="_blank" rel="noopener">Open public merchant page ↗</a>
            <button type="button" class="c-btn" x-on:click="copy(d.store_url)">Copy link</button>
        </div>
    </div>

    <div class="c-alert" x-show="loaded && !d.enabled" x-cloak>
        <p><strong>Marketplace Resale is off</strong> Turn it on in <a href="{{ route('settings') }}#project">Settings → Project</a> to list forgotten subscriptions</p>
    </div>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
        <div class="c-card" style="padding:18px 20px"><p class="dim" style="margin:0;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Live on your page</p><p style="margin:6px 0 0;font-size:1.6rem;font-weight:700" x-text="(d.counts||{}).active || 0"></p></div>
        <div class="c-card" style="padding:18px 20px"><p class="dim" style="margin:0;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Sold</p><p style="margin:6px 0 0;font-size:1.6rem;font-weight:700;color:#047857" x-text="(d.counts||{}).sold || 0"></p></div>
    </section>

    <section class="c-card">
        <div class="c-head"><div><h2 class="c-title">Listings</h2><p class="c-sub">Created when a subscriber picks Marketplace Resale during the grace period</p></div></div>
        <div class="c-pad">
            <x-ui.loading show="!loaded" title="Loading listings" hint="Checking your public merchant page" />
            <x-ui.load-error show="loaded && failed" title="Could not load listings" />
            <div x-show="loaded && !failed && !(d.listings||[]).length" x-cloak class="lp-state"><div><strong>No listings yet</strong>Listings appear when a subscriber picks Marketplace Resale during the grace period</div></div>
            <div id="grid-resale" style="width:100%" x-show="loaded && !failed && (d.listings||[]).length" x-cloak></div>
        </div>
    </section>
</div>
<script src="/js/resale.js?v={{ @filemtime(public_path('js/resale.js')) ?: time() }}" defer></script>
@endsection

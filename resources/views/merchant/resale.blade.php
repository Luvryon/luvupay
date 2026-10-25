@extends('layouts.console')
@section('title', 'Resale')
@section('heading', 'Resale')

@section('content')
<div class="stack" x-data="resalePage()" x-init="load()">
    <div class="row">
        <div>
            <h1 class="page-title">Resale marketplace</h1>
            <p class="page-sub">Access that your subscribers resell appears on your public merchant page. Buyers click, pay with PayPal, and the token redeems instantly.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="c-btn c-btn-brand" x-bind:href="d.store_url" target="_blank" rel="noopener">Open public merchant page ↗</a>
            <button type="button" class="c-btn" x-on:click="copy(d.store_url)">Copy link</button>
        </div>
    </div>

    <div class="c-alert" x-show="loaded && !d.enabled" x-cloak>
        <p><strong>Marketplace Resale is off.</strong> Turn it on in <a href="{{ route('settings') }}#project">Settings → Project</a> to list forgotten subscriptions.</p>
    </div>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
        <div class="c-card" style="padding:18px 20px"><p class="dim" style="margin:0;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Live on your page</p><p style="margin:6px 0 0;font-size:1.6rem;font-weight:700" x-text="(d.counts||{}).active || 0"></p></div>
        <div class="c-card" style="padding:18px 20px"><p class="dim" style="margin:0;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Sold</p><p style="margin:6px 0 0;font-size:1.6rem;font-weight:700;color:#047857" x-text="(d.counts||{}).sold || 0"></p></div>
    </section>

    <section class="c-card">
        <div class="c-head"><div><h2 class="c-title">Listings</h2><p class="c-sub">Created when a subscriber picks Marketplace Resale during the grace period.</p></div></div>
        <div class="c-pad" style="overflow-x:auto">
            <p x-show="loaded && !(d.listings||[]).length" class="dim" style="margin:0">No listings yet. Run <strong>Simulate 30 days</strong> on Overview, or choose Resale for a subscriber in grace period.</p>
            <table x-show="(d.listings||[]).length" style="width:100%;border-collapse:collapse;font-size:.86rem">
                <thead><tr class="dim" style="text-align:left"><th style="padding:6px 8px">Seller</th><th style="padding:6px 8px">Access</th><th style="padding:6px 8px">Price</th><th style="padding:6px 8px">Status</th><th style="padding:6px 8px">Buyer</th><th style="padding:6px 8px">Link</th></tr></thead>
                <tbody>
                    <template x-for="l in d.listings" :key="l.id">
                        <tr style="border-top:1px solid var(--line)">
                            <td style="padding:8px" x-text="l.seller"></td>
                            <td style="padding:8px" x-text="l.days ? l.days + ' days' : '—'"></td>
                            <td style="padding:8px;font-weight:600" x-text="money(l.price_minor, l.currency)"></td>
                            <td style="padding:8px"><span style="border-radius:999px;padding:2px 10px;font-size:.75rem;font-weight:700" x-bind:style="l.status==='active' ? 'background:#d1fae5;color:#047857' : (l.status==='sold' ? 'background:#e0e7ff;color:#4338ca' : 'background:#f1f5f9;color:#64748b')" x-text="l.status"></span></td>
                            <td style="padding:8px" x-text="l.buyer || '—'"></td>
                            <td style="padding:8px">
                                <template x-if="l.link && l.status === 'active'"><span><a x-bind:href="l.link" target="_blank" rel="noopener" style="font-weight:600">Open</a> · <a href="#" x-on:click.prevent="copy(l.link)">Copy</a></span></template>
                                <template x-if="!(l.link && l.status === 'active')"><span class="dim">—</span></template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="/js/resale.js?v={{ @filemtime(public_path('js/resale.js')) ?: time() }}" defer></script>
@endsection

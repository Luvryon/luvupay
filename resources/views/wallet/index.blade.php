@extends('layouts.console')
@section('title', 'Wallet & Transactions')
@section('heading', 'Wallet & Transactions')
@section('content')
<div class="stack" x-data="walletPage()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Wallet &amp; Transactions</h1>
            <p class="page-sub">Available balance and full ledger for this merchant</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="c-btn c-btn-sm" x-on:click="exportCsv()" x-bind:disabled="!filteredRows.length">Export CSV</button>
            <button type="button" class="c-btn c-btn-sm" x-on:click="exportExcel()" x-bind:disabled="!filteredRows.length">Export Excel</button>
            <button type="button" class="c-btn c-btn-sm" x-bind:class="loading ? 'is-busy' : ''" x-on:click="load()">Refresh</button>
        </div>
    </div>

    <section class="c-card">
        <div class="c-pad" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px">
            <div>
                <p class="dim" style="margin:0;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Available balance</p>
                <p style="margin:6px 0 0;font-size:2rem;font-weight:700;letter-spacing:-0.03em;color:#0f172a" x-show="loaded" x-text="balanceLabel"></p>
                <span class="lp-skeleton" x-show="!loaded" style="width:170px;height:34px;margin-top:8px"></span>
                <p class="dim" style="margin:6px 0 0;font-size:.8rem">Your earnings: completed charges − partial refunds − charity − withdrawals + your resale fees. Held by LuvuPay as merchant of record until you withdraw</p>
            </div>
            <button type="button" class="c-btn c-btn-brand" x-on:click="withdraw()" x-bind:disabled="withdrawing || balanceMinor <= 0">
                <span x-text="withdrawing ? 'Sending…' : 'Withdraw to PayPal'"></span>
            </button>
            <p class="dim" x-show="balanceMinor > 0" x-cloak style="margin:8px 0 0;font-size:.8rem">
                PayPal fee <strong x-text="'−' + money(quote.fee, currency)"></strong> (from your wallet) →
                you receive <strong style="color:#4338ca" x-text="money(quote.net, currency)"></strong>
                <span x-show="payoutEmail"> at <span x-text="payoutEmail"></span></span>
                <span x-show="!payoutEmail"> · <a href="{{ route('settings') }}#payouts">set your PayPal email</a></span>
            </p>
        </div>
        <div class="c-pad" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;border-top:1px solid var(--line)">
            <template x-for="b in breakdown" x-bind:key="b.label">
                <div>
                    <p class="dim" style="margin:0;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600" x-text="b.label"></p>
                    <p style="margin:4px 0 0;font-weight:700" x-bind:style="'color:' + b.color" x-text="b.value"></p>
                </div>
            </template>
        </div>
    </section>

    <section class="c-card">
        <div class="c-pad" style="display:flex;flex-wrap:wrap;gap:12px;align-items:end;border-bottom:1px solid var(--line)">
            <div class="field" style="margin:0;min-width:140px">
                <label class="label" for="w-from">From</label>
                <input id="w-from" type="date" class="input" x-model="fromDate" >
            </div>
            <div class="field" style="margin:0;min-width:140px">
                <label class="label" for="w-to">To</label>
                <input id="w-to" type="date" class="input" x-model="toDate" >
            </div>
            <div class="field" style="margin:0;flex:1;min-width:200px">
                <label class="label" for="w-q">Search</label>
                <input id="w-q" type="search" class="input" placeholder="ID, email, reference…" x-model="q" >
            </div>
        </div>
        <div style="padding:0" x-bind:class="loading && loaded ? 'lp-loading' : ''">
            <x-ui.loading show="!loaded" title="Loading transactions" hint="Reading your ledger" />
            <x-ui.load-error show="loaded && failed" title="Could not load transactions" />
            <div id="grid-wallet" style="width:100%" x-show="loaded && !failed" x-cloak></div>
        </div>
    </section>
</div>
@endsection
@push('scripts')
<script src="/js/wallet.js?v={{ @filemtime(public_path('js/wallet.js')) }}" defer></script>
@endpush

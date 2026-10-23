@extends('layouts.console')
@section('title', 'Wallet & Transactions')
@section('heading', 'Wallet & Transactions')
@section('content')
<div class="stack" x-data="walletPage()" x-init="load()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Wallet &amp; Transactions</h1>
            <p class="page-sub">Available balance and full ledger for this merchant.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="c-btn c-btn-sm" x-on:click="exportCsv()" x-bind:disabled="!filteredRows.length">Export CSV</button>
            <button type="button" class="c-btn c-btn-sm" x-on:click="load()" x-text="loading ? 'Refreshing…' : 'Refresh'"></button>
        </div>
    </div>

    <section class="c-card">
        <div class="c-pad" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px">
            <div>
                <p class="dim" style="margin:0;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600">Available balance</p>
                <p style="margin:6px 0 0;font-size:2rem;font-weight:700;letter-spacing:-0.03em;color:#0f172a" x-text="balanceLabel">$0.00</p>
                <p class="dim" style="margin:6px 0 0;font-size:.8rem">Funded by completed subscription charges and resale fees.</p>
            </div>
            <button type="button" class="c-btn c-btn-brand" x-on:click="withdraw()" x-bind:disabled="withdrawing || balanceMinor <= 0">
                <span x-text="withdrawing ? 'Sending…' : 'Withdraw to PayPal'"></span>
            </button>
        </div>
    </section>

    <section class="c-card">
        <div class="c-pad" style="display:flex;flex-wrap:wrap;gap:12px;align-items:end;border-bottom:1px solid var(--line)">
            <div class="field" style="margin:0;min-width:140px">
                <label class="label" for="w-from">From</label>
                <input id="w-from" type="date" class="input" x-model="fromDate" x-on:change="page = 1">
            </div>
            <div class="field" style="margin:0;min-width:140px">
                <label class="label" for="w-to">To</label>
                <input id="w-to" type="date" class="input" x-model="toDate" x-on:change="page = 1">
            </div>
            <div class="field" style="margin:0;flex:1;min-width:200px">
                <label class="label" for="w-q">Search</label>
                <input id="w-q" type="search" class="input" placeholder="ID, email, reference…" x-model="q" x-on:input.debounce.200ms="page = 1">
            </div>
        </div>
        <div class="c-pad" style="padding-top:0;overflow-x:auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Reference</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="!loading && !pageRows.length">
                        <tr><td colspan="5" class="px-3 py-10 text-center text-slate-500">No transactions in this range.</td></tr>
                    </template>
                    <template x-for="row in pageRows" :key="row.id">
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 text-slate-600 whitespace-nowrap" x-text="row.when"></td>
                            <td class="px-3 py-2 font-medium text-slate-900" x-text="row.type"></td>
                            <td class="px-3 py-2" x-text="row.status"></td>
                            <td class="px-3 py-2 text-xs text-slate-500 font-mono" x-text="row.ref"></td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums" x-text="row.amount"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap">
                <p class="dim" style="margin:0;font-size:.8rem" x-text="filteredRows.length + ' transaction(s)'"></p>
                <div style="display:flex;gap:6px;align-items:center">
                    <button type="button" class="c-btn c-btn-sm" x-on:click="page = Math.max(1, page - 1)" x-bind:disabled="page <= 1">Prev</button>
                    <span class="dim" style="font-size:.8rem" x-text="'Page ' + page + ' / ' + totalPages"></span>
                    <button type="button" class="c-btn c-btn-sm" x-on:click="page = Math.min(totalPages, page + 1)" x-bind:disabled="page >= totalPages">Next</button>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
@push('scripts')
<script src="/js/wallet.js?v={{ @filemtime(public_path('js/wallet.js')) }}" defer></script>
@endpush

@extends('layouts.console')
@section('title', 'Wallet & Transactions')
@section('heading', 'Wallet & Transactions')

@section('content')
<div class="stack" x-data="walletApp()" x-init="init()">
    <div class="row">
        <div>
            <h1 class="page-title">Wallet & Transactions</h1>
            <p class="page-sub">Available balance and ledger — money in, fees, and payouts.</p>
        </div>
    </div>

    {{-- Connected Wallet (merchant PayPal) — never show sb-* emails --}}
    <section class="c-card" style="padding:20px 24px">
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px">
            <div style="display:flex;align-items:center;gap:14px">
                <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#003087,#009cde);display:grid;place-items:center;color:#fff;font-weight:800;font-size:.85rem">PP</div>
                <div>
                    <p style="margin:0;font-weight:700;color:var(--ink)">My Business PayPal Account</p>
                    <p style="margin:4px 0 0;font-size:.8rem;color:var(--muted)">Payouts route via environment-configured receiver · zero hardcoded emails</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                <span style="width:6px;height:6px;border-radius:999px;background:#059669;display:inline-block"></span>
                Connected
            </span>
        </div>
    </section>

    {{-- Balance --}}
    <section class="c-card" style="padding:28px 32px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px">
        <div>
            <p style="margin:0;font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Available balance</p>
            <p class="page-title" style="margin:8px 0 0;font-size:2.25rem;font-variant-numeric:tabular-nums" x-text="balanceDisplay">$0.00</p>
            <p style="margin:6px 0 0;font-size:.85rem;color:var(--muted)">Held in LuvuPay escrow · ready to withdraw</p>
            <p x-show="lastPayoutId" x-cloak style="margin:8px 0 0;font-size:.8rem;color:var(--muted)">
                Last payout batch: <code x-text="lastPayoutId"></code>
            </p>
        </div>
        <button type="button" class="c-btn c-btn-brand" style="padding:12px 22px;font-size:.95rem"
                x-on:click="withdraw()" x-bind:disabled="withdrawing || balanceMinor <= 0">
            <span x-text="withdrawing ? 'Sending to PayPal…' : 'Withdraw to PayPal'"></span>
        </button>
    </section>

    {{-- Charity demo card --}}
    <section class="c-card" style="padding:20px 24px">
        <div class="c-head" style="padding:0;border:0;margin-bottom:12px">
            <div>
                <h2 class="c-title">Charity Split (demo)</h2>
                <p class="c-sub">Donate residual subscription value via PayPal Payouts — receiver email from .env only.</p>
            </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#fff">
            <div style="display:flex;align-items:center;gap:12px">
                <div style="width:44px;height:44px;border-radius:12px;background:#fce7f3;display:grid;place-items:center;font-size:1.25rem">❤️</div>
                <div>
                    <p style="margin:0;font-weight:700;color:#0f172a">American Red Cross</p>
                    <span class="bg-amber-100 text-amber-700 text-[10px] px-1.5 py-0.5 rounded border border-amber-200 font-semibold" style="display:inline-block;margin-top:4px">Sandbox Verified</span>
                </div>
            </div>
            <button type="button" class="c-btn c-btn-sm" style="background:#db2777;color:#fff;border:0"
                    x-on:click="donateCharity()" x-bind:disabled="donating">
                <span x-text="donating ? 'Sending…' : 'Donate via Payouts API'"></span>
            </button>
        </div>
        <p style="margin:10px 0 0;font-size:.75rem;color:var(--muted)">Uses the latest held / grace transaction residual when available.</p>
    </section>

    {{-- Ledger --}}
    <section class="c-card">
        <div class="c-head">
            <div>
                <h2 class="c-title">Transaction ledger</h2>
                <p class="c-sub">Charges, fees, withdrawals, and charity payouts.</p>
            </div>
            <button type="button" class="c-btn c-btn-sm" x-on:click="load()" x-bind:disabled="loading">
                <span x-text="loading ? 'Refreshing…' : 'Refresh'"></span>
            </button>
        </div>
        <div class="c-pad" style="padding-top:0;overflow-x:auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Reference</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in rows" :key="row.id">
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-2 text-slate-600" x-text="row.when"></td>
                            <td class="px-3 py-2 font-medium text-slate-900" x-text="row.type"></td>
                            <td class="px-3 py-2" x-text="row.status"></td>
                            <td class="px-3 py-2 text-xs text-slate-500 font-mono" x-text="row.ref"></td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums" x-text="row.amount"></td>
                        </tr>
                    </template>
                    <tr x-show="!loading && rows.length === 0">
                        <td colspan="5" class="px-3 py-10 text-center text-slate-400">No ledger rows yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="/js/wallet.js?v={{ @filemtime(public_path('js/wallet.js')) ?: time() }}" defer></script>
@endpush

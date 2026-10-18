@extends('layouts.console')
@section('title', 'Analytics')
@section('heading', 'Analytics')

@section('content')
<div class="stack" x-data="analyticsApp()" x-init="init()">
    <div class="row">
        <div>
            <h1 class="page-title">Analytics</h1>
            <p class="page-sub">Live metrics from your merchant ledger — real transactions, not mock data.</p>
        </div>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
            <div style="position:relative" x-on:click.outside="rangeOpen = false">
                <button type="button" class="c-btn c-btn-sm" x-on:click="rangeOpen = !rangeOpen" x-text="rangeLabel">Last 30 Days</button>
                <div x-show="rangeOpen" x-cloak class="dd-menu" style="display:none;right:0;left:auto;min-width:11rem">
                    <template x-for="r in ranges" :key="r.id">
                        <button type="button" class="dd-item" x-on:click="pickRange(r)" x-text="r.label"></button>
                    </template>
                </div>
            </div>
            <button type="button" class="c-btn c-btn-sm" x-on:click="downloadCsv()" x-bind:disabled="loading">Export CSV</button>
            <button type="button" class="c-btn c-btn-brand c-btn-sm" x-on:click="load()" x-bind:disabled="loading">
                <span x-text="loading ? 'Refreshing…' : 'Refresh'"></span>
            </button>
        </div>
    </div>

    {{-- Primary KPIs --}}
    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
        <div class="c-card" style="padding:20px 22px">
            <p style="margin:0;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Total revenue saved</p>
            <p style="margin:10px 0 0;font-size:1.75rem;font-weight:700;font-variant-numeric:tabular-nums;color:var(--ink)" x-text="kpi.totalRevenueSaved">$0.00</p>
            <p style="margin:6px 0 0;font-size:.75rem;color:var(--faint)">merchant retention</p>
        </div>
        <div class="c-card" style="padding:20px 22px">
            <p style="margin:0;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Churn rate</p>
            <p style="margin:10px 0 0;font-size:1.75rem;font-weight:700;font-variant-numeric:tabular-nums;color:var(--ink)" x-text="kpi.churnRate">0%</p>
            <p style="margin:6px 0 0;font-size:.75rem;color:var(--faint)">lifecycle + subscriptions</p>
        </div>
        <div class="c-card" style="padding:20px 22px">
            <p style="margin:0;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Marketplace volume</p>
            <p style="margin:10px 0 0;font-size:1.75rem;font-weight:700;font-variant-numeric:tabular-nums;color:var(--ink)" x-text="kpi.marketplaceVolume">$0.00</p>
            <p style="margin:6px 0 0;font-size:.75rem;color:var(--faint)">resale completed</p>
        </div>
        <div class="c-card" style="padding:20px 22px">
            <p style="margin:0;font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)">Charity impact</p>
            <p style="margin:10px 0 0;font-size:1.75rem;font-weight:700;font-variant-numeric:tabular-nums;color:var(--ink)" x-text="kpi.charityImpact">$0.00</p>
            <p style="margin:6px 0 0;font-size:.75rem;color:var(--faint)">charity payouts</p>
        </div>
    </section>

    {{-- Secondary metrics --}}
    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
        <div class="c-card" style="padding:16px 18px">
            <p style="margin:0;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">Revenue saved</p>
            <p style="margin:8px 0 0;font-size:1.15rem;font-weight:700;font-variant-numeric:tabular-nums" x-text="kpi.revenueSaved">$0.00</p>
        </div>
        <div class="c-card" style="padding:16px 18px">
            <p style="margin:0;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">Chargebacks prevented</p>
            <p style="margin:8px 0 0;font-size:1.15rem;font-weight:700;font-variant-numeric:tabular-nums" x-text="kpi.chargebacks">0</p>
        </div>
        <div class="c-card" style="padding:16px 18px">
            <p style="margin:0;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">Active subscribers</p>
            <p style="margin:8px 0 0;font-size:1.15rem;font-weight:700;font-variant-numeric:tabular-nums" x-text="kpi.subscriptions">0</p>
        </div>
        <div class="c-card" style="padding:16px 18px">
            <p style="margin:0;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">Charity donated</p>
            <p style="margin:8px 0 0;font-size:1.15rem;font-weight:700;font-variant-numeric:tabular-nums" x-text="kpi.charity">$0.00</p>
        </div>
        <div class="c-card" style="padding:16px 18px">
            <p style="margin:0;font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)">Resale volume</p>
            <p style="margin:8px 0 0;font-size:1.15rem;font-weight:700;font-variant-numeric:tabular-nums" x-text="kpi.resaleVolume">$0.00</p>
        </div>
    </section>

    {{-- Ledger table --}}
    <section class="c-card">
        <div class="c-head">
            <div>
                <h2 class="c-title">Recent ledger activity</h2>
                <p class="c-sub">Rows from the transactions API for this merchant</p>
            </div>
        </div>
        <div style="overflow-x:auto">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Lifecycle</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in rows" :key="row.id">
                        <tr>
                            <td style="white-space:nowrap;color:var(--muted);font-size:.85rem" x-text="fmtDate(row.created_at)"></td>
                            <td>
                                <span style="font-weight:600" x-text="row.customer_name || row.email || '—'"></span>
                                <span style="display:block;font-size:.75rem;color:var(--faint)" x-show="row.customer_name && row.email" x-text="row.email"></span>
                            </td>
                            <td x-text="row.type || '—'"></td>
                            <td>
                                <span class="tag" x-show="row.lifecycle" x-bind:class="lifeClass(row.lifecycle)" x-text="row.lifecycle"></span>
                                <span x-show="!row.lifecycle" style="color:var(--faint)">—</span>
                            </td>
                            <td style="font-variant-numeric:tabular-nums;font-weight:600" x-text="fmtMoney(row.amount_minor, row.currency)"></td>
                            <td><span class="tag" x-bind:class="statusClass(row.status)" x-text="row.status || '—'"></span></td>
                        </tr>
                    </template>
                    <tr x-show="!loading && rows.length === 0">
                        <td colspan="6" class="c-empty">No ledger rows yet. Run Simulate 30 days or a sandbox checkout.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="/js/analytics.js?v={{ @filemtime(public_path('js/analytics.js')) ?: time() }}" defer></script>
@endpush

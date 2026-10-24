@extends('layouts.console')
@section('title', 'Analytics')
@section('heading', 'Analytics')

@section('content')
<div class="stack" x-data="analyticsApp()" x-init="init()">
    <div class="row">
        <div>
            <h1 class="page-title">Analytics</h1>
            <p class="page-sub">Live metrics from your ledger for <strong x-text="d.project && d.project.name ? d.project.name : 'your project'"></strong>. Same numbers as Overview and Wallet.</p>
        </div>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px">
            <div style="display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden">
                <template x-for="r in ranges" :key="r">
                    <button type="button" class="c-btn c-btn-sm" style="border:0;border-radius:0"
                            x-bind:style="days === r ? 'background:var(--brand);color:#fff' : ''"
                            x-on:click="pick(r)" x-text="r + 'd'"></button>
                </template>
            </div>
            <button type="button" class="c-btn c-btn-sm" x-on:click="downloadCsv()" x-bind:disabled="loading || !hasData">Export CSV</button>
            <button type="button" class="c-btn c-btn-brand c-btn-sm" x-on:click="load()" x-bind:disabled="loading">
                <span x-text="loading ? 'Refreshing…' : 'Refresh'"></span>
            </button>
        </div>
    </div>

    <div class="c-alert" x-show="error" x-cloak><p x-text="error"></p></div>

    <div class="c-card" x-show="loaded && !hasData" x-cloak>
        <div class="c-pad" style="text-align:center;padding:36px 20px">
            <p style="margin:0;font-weight:700;font-size:1.05rem">No activity in the last <span x-text="days"></span> days</p>
            <p class="dim" style="margin:8px 0 16px">Open Overview and run <strong>Simulate 30 days</strong> (or a single checkout) to populate every chart here.</p>
            <a href="{{ route('dashboard') }}" class="c-btn c-btn-brand">Go to Overview</a>
        </div>
    </div>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px" x-show="hasData" x-cloak>
        <template x-for="k in kpis" :key="k.label">
            <div class="c-card" style="padding:18px 20px">
                <p class="dim" style="margin:0;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;font-weight:600" x-text="k.label"></p>
                <p style="margin:6px 0 0;font-size:1.6rem;font-weight:700;letter-spacing:-0.02em" x-bind:style="'color:' + k.color" x-text="k.value"></p>
                <p class="dim" style="margin:4px 0 0;font-size:.78rem" x-text="k.hint"></p>
            </div>
        </template>
    </section>

    <section class="c-card" x-show="hasData" x-cloak>
        <div class="c-head"><div><h2 class="c-title">Revenue vs. revenue saved</h2><p class="c-sub">Daily completed charges (bars) and value LuvuPay retained (line).</p></div>
            <div class="dim" style="font-size:.78rem;display:flex;gap:12px"><span><i style="display:inline-block;width:10px;height:10px;background:#6366f1;border-radius:2px"></i> Revenue</span><span><i style="display:inline-block;width:10px;height:10px;background:#10b981;border-radius:2px"></i> Saved</span></div></div>
        <div class="c-pad" style="overflow-x:auto"><div x-html="chartMain()"></div></div>
    </section>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px" x-show="hasData" x-cloak>
        <div class="c-card">
            <div class="c-head"><div><h2 class="c-title">Charity &amp; resale per day</h2><p class="c-sub">Donations (green) and marketplace resale volume (amber).</p></div></div>
            <div class="c-pad" style="overflow-x:auto"><div x-html="chartSecondary()"></div></div>
        </div>
        <div class="c-card">
            <div class="c-head"><div><h2 class="c-title">Lifecycle outcomes</h2><p class="c-sub">What happened to each charge.</p></div></div>
            <div class="c-pad stack-sm">
                <template x-for="o in outcomes" :key="o.label">
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:.84rem"><span x-text="o.label"></span><strong x-text="o.count"></strong></div>
                        <div style="height:8px;border-radius:99px;background:var(--line);margin-top:4px;overflow:hidden"><div style="height:100%;border-radius:99px" x-bind:style="'width:' + o.pct + '%;background:' + o.color"></div></div>
                    </div>
                </template>
            </div>
        </div>
    </section>

    <section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px" x-show="hasData" x-cloak>
        <div class="c-card">
            <div class="c-head"><div><h2 class="c-title">Where revenue saved comes from</h2></div></div>
            <div class="c-pad stack-sm">
                <template x-for="s in savedParts" :key="s.label">
                    <div style="display:flex;justify-content:space-between;font-size:.9rem"><span><i style="display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:8px" x-bind:style="'background:' + s.color"></i><span x-text="s.label"></span></span><strong x-text="s.value"></strong></div>
                </template>
                <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:10px;font-weight:700"><span>Total saved</span><span x-text="money(d.revenue_saved_minor)"></span></div>
            </div>
        </div>
        <div class="c-card">
            <div class="c-head"><div><h2 class="c-title">Subscribers</h2></div></div>
            <div class="c-pad stack-sm">
                <template x-for="s in subs" :key="s.label">
                    <div style="display:flex;justify-content:space-between;font-size:.9rem"><span x-text="s.label"></span><strong x-text="s.value"></strong></div>
                </template>
                <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:10px;font-weight:700"><span>Churn rate</span><span x-text="(d.churn_rate || 0) + '%'"></span></div>
            </div>
        </div>
        <div class="c-card">
            <div class="c-head"><div><h2 class="c-title">Project settings in effect</h2><p class="c-sub">Edited in <a href="{{ route('settings') }}#project">Settings</a>.</p></div></div>
            <div class="c-pad stack-sm" style="font-size:.9rem">
                <template x-for="p in projectRows" :key="p.label">
                    <div style="display:flex;justify-content:space-between"><span class="dim" x-text="p.label"></span><strong x-text="p.value"></strong></div>
                </template>
            </div>
        </div>
    </section>

    <section class="c-card" x-show="hasData && (d.insights || []).length" x-cloak>
        <div class="c-head"><div><h2 class="c-title">Insights</h2></div></div>
        <div class="c-pad stack-sm">
            <template x-for="(i, idx) in d.insights" :key="idx"><div class="c-item"><span x-text="i.icon"></span><p style="margin:0" x-text="i.text"></p></div></template>
        </div>
    </section>

    <section class="c-card" x-show="hasData" x-cloak>
        <div class="c-head"><div><h2 class="c-title">Recent activity</h2><p class="c-sub">Latest ledger rows in this range.</p></div></div>
        <div class="c-pad" style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.86rem">
                <thead><tr class="dim" style="text-align:left"><th style="padding:6px 8px">When</th><th style="padding:6px 8px">Customer</th><th style="padding:6px 8px">Type</th><th style="padding:6px 8px">Status</th><th style="padding:6px 8px;text-align:right">Amount</th></tr></thead>
                <tbody>
                    <template x-for="r in d.recent" :key="r.id">
                        <tr style="border-top:1px solid var(--line)">
                            <td style="padding:8px" x-text="new Date(r.created_at).toLocaleString()"></td>
                            <td style="padding:8px" x-text="r.customer_name || r.email || '—'"></td>
                            <td style="padding:8px" x-text="r.type"></td>
                            <td style="padding:8px" x-text="r.lifecycle || r.status"></td>
                            <td style="padding:8px;text-align:right;font-weight:600" x-text="money(r.amount_minor)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script src="/js/analytics.js?v={{ @filemtime(public_path('js/analytics.js')) ?: time() }}" defer></script>
@endsection

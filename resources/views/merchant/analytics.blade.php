@extends('layouts.console')
@section('title', 'Analytics')
@section('heading', 'Analytics')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="analyticsApp()" x-init="init()">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 mb-1">Insights</p>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Analytics</h1>
            <p class="mt-1 text-sm text-slate-500 max-w-xl">
                Live metrics from your ledger for
                <strong class="text-slate-700" x-text="d.project && d.project.name ? d.project.name : 'your project'"></strong>.
                Same source of truth as Overview and Wallet.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-0.5 shadow-sm">
                <template x-for="r in ranges" :key="r">
                    <button type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
                            x-bind:class="days === r ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                            x-on:click="pick(r)"
                            x-text="r + 'd'"></button>
                </template>
            </div>
            <button type="button"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-40"
                    x-on:click="downloadCsv()"
                    x-bind:disabled="loading || !hasData">Export CSV</button>
            <button type="button"
                    class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                    x-on:click="load()"
                    x-bind:disabled="loading">
                <span x-text="loading ? 'Refreshing…' : 'Refresh'"></span>
            </button>
        </div>
    </div>

    {{-- Error --}}
    <div x-show="error" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="error"></div>

    {{-- Empty --}}
    <div x-show="loaded && !hasData" x-cloak class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-2xl">📊</div>
        <p class="text-lg font-bold text-slate-900">No activity in the last <span x-text="days"></span> days</p>
        <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
            Run a subscription checkout from Overview to populate revenue, retention, and charity charts.
        </p>
        <a href="{{ route('dashboard') }}" class="mt-5 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Go to Overview</a>
    </div>

    {{-- KPI grid --}}
    <section x-show="hasData" x-cloak class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <template x-for="k in kpis" :key="k.label">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-slate-300 transition-colors">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400" x-text="k.label"></p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900" x-bind:style="k.color ? 'color:' + k.color : ''" x-text="k.value"></p>
                <p class="mt-1 text-xs text-slate-500" x-text="k.hint"></p>
            </div>
        </template>
    </section>

    {{-- Charts --}}
    <section x-show="hasData" x-cloak class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Revenue vs saved</h2>
                    <p class="text-xs text-slate-500">Charges and retention saves over time</p>
                </div>
                <div class="flex items-center gap-3 text-[10px] font-semibold">
                    <span class="flex items-center gap-1.5 text-slate-600"><span class="h-2 w-2 rounded-sm bg-indigo-500"></span> Revenue</span>
                    <span class="flex items-center gap-1.5 text-slate-600"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Saved</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <div x-html="chartMain()" class="min-w-[280px]"></div>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Charity &amp; resale</h2>
                    <p class="text-xs text-slate-500">Second Chance donations and marketplace volume</p>
                </div>
                <div class="flex items-center gap-3 text-[10px] font-semibold">
                    <span class="flex items-center gap-1.5 text-slate-600"><span class="h-2 w-2 rounded-sm bg-emerald-500"></span> Charity</span>
                    <span class="flex items-center gap-1.5 text-slate-600"><span class="h-2 w-2 rounded-sm bg-amber-500"></span> Resale</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <div x-html="chartSecondary()" class="min-w-[280px]"></div>
            </div>
        </div>
    </section>

    {{-- Insights + outcomes --}}
    <section x-show="hasData" x-cloak class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 mb-3">AI insights</h2>
            <ul class="space-y-2">
                <template x-for="(ins, i) in (d.insights || [])" :key="i">
                    <li class="flex gap-3 rounded-xl bg-slate-50 border border-slate-100 px-3 py-2.5">
                        <span class="text-base shrink-0" x-text="ins.icon || '💡'"></span>
                        <p class="text-sm text-slate-700 leading-relaxed" x-text="ins.text || ins"></p>
                    </li>
                </template>
                <li x-show="!(d.insights && d.insights.length)" class="text-sm text-slate-400 py-4 text-center">
                    Insights appear once there is enough ledger activity.
                </li>
            </ul>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900">Retention outcomes</h2>
            <template x-for="row in outcomes" :key="row.label">
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-medium text-slate-600" x-text="row.label"></span>
                        <span class="font-semibold text-slate-900" x-text="row.count + ' · ' + row.pct + '%'"></span>
                    </div>
                    <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full transition-all" x-bind:style="'width:' + row.pct + '%;background:' + row.color"></div>
                    </div>
                </div>
            </template>
            <p x-show="!outcomes.length" class="text-xs text-slate-400">No outcome mix yet.</p>
        </div>
    </section>

    {{-- Recent activity --}}
    <section x-show="hasData" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Recent activity</h2>
                <p class="text-xs text-slate-500">Latest ledger events in this window</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                        <th class="px-5 py-3 font-semibold">When</th>
                        <th class="px-5 py-3 font-semibold">Type</th>
                        <th class="px-5 py-3 font-semibold">Customer</th>
                        <th class="px-5 py-3 font-semibold text-right">Amount</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(r, i) in (d.recent || [])" :key="i">
                        <tr class="border-b border-slate-50 hover:bg-slate-50/80">
                            <td class="px-5 py-3 text-xs text-slate-500 whitespace-nowrap font-mono" x-text="(r.created_at || r.date || '').replace('T',' ').slice(0,16)"></td>
                            <td class="px-5 py-3 font-medium text-slate-800" x-text="r.type || r.event || '—'"></td>
                            <td class="px-5 py-3 text-slate-600" x-text="r.email || r.display_name || '—'"></td>
                            <td class="px-5 py-3 text-right font-semibold text-slate-900" x-text="money(r.amount_minor || r.amount || 0, r.currency)"></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                      x-bind:class="String(r.status || r.lifecycle || '').toLowerCase().indexOf('active') !== -1 || String(r.status).toLowerCase() === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                      x-text="r.status || r.lifecycle || '—'"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!(d.recent && d.recent.length)">
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No recent rows in this range.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="/js/analytics.js?v={{ @filemtime(public_path('js/analytics.js')) }}" defer></script>
@endpush

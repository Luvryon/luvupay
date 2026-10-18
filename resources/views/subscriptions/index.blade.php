@extends('layouts.console')
@section('title', 'Subscriptions')
@section('heading', 'Subscriptions')

@section('content')
<div class="stack" x-data="subscriptionsApp()" x-init="init()">
    <div class="row">
        <div>
            <h1 class="page-title">Subscriptions</h1>
            <p class="page-sub">Customers, lifecycle status, and LTV — CRM view of your project.</p>
        </div>
        <button type="button" class="c-btn c-btn-sm" x-on:click="load()" x-bind:disabled="loading">
            <span x-text="loading ? 'Refreshing…' : 'Refresh'"></span>
        </button>
    </div>

    {{-- Platform logic callout --}}
    <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 p-3 text-sm text-indigo-700">
        💡 LuviPay AI activates a <strong>48-hour grace period</strong>. Merchants can optionally enable
        <strong>Marketplace Resale</strong> or <strong>Charity Split</strong> for their projects.
        If the user ignores the window and those options are off, the subscription simply <strong>expires</strong> — no force.
    </div>

    {{-- Filters --}}
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:4px">
        <template x-for="f in filters" :key="f.id">
            <button type="button" class="c-btn c-btn-sm"
                    x-bind:class="filter === f.id ? 'c-btn-brand' : ''"
                    x-on:click="filter = f.id"
                    x-text="f.label"></button>
        </template>
    </div>

    <section class="c-card" style="padding:0;overflow:hidden">
        <div style="overflow-x:auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">AI Retention Action</th>
                        <th class="px-4 py-3">LTV</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in filtered" :key="row.id">
                        <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50/50">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-slate-900" x-text="row.name"></span>
                                <span class="mt-0.5 block text-xs text-slate-400" x-text="row.email" x-show="row.email"></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700" x-text="row.project"></td>
                            <td class="px-4 py-3 text-sm text-slate-700" x-text="row.plan"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                      x-bind:class="badgeClass(row.statusKey)"
                                      x-text="row.status"></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600" style="max-width:280px" x-text="row.action"></td>
                            <td class="px-4 py-3 text-sm font-semibold tabular-nums text-slate-900" x-text="row.ltv"></td>
                        </tr>
                    </template>
                    <tr x-show="!loading && filtered.length === 0">
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-400">
                            No subscribers match this filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="/js/subscriptions.js?v={{ @filemtime(public_path('js/subscriptions.js')) ?: time() }}" defer></script>
@endpush

@extends('layouts.console')
@section('title', 'Subscriptions')
@section('heading', 'Subscriptions')
@section('content')
<div class="stack" x-data="subsPage()" x-init="load()">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Subscriptions</h1>
            <p class="page-sub">Customers on your plans — active, at risk, retained by AI, or churned.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="f in filters" :key="f.id">
                <button type="button" class="c-btn c-btn-sm"
                        x-bind:class="filter === f.id ? 'c-btn-brand' : ''"
                        x-on:click="filter = f.id; apply()"
                        x-text="f.label"></button>
            </template>
            <button type="button" class="c-btn c-btn-sm" x-on:click="load()" x-text="loading ? 'Loading…' : 'Refresh'"></button>
        </div>
    </div>

    <div class="c-card" style="padding:0;overflow:hidden">
        <div x-show="loading && !rows.length" class="c-pad" style="text-align:center;padding:48px">
            <p class="dim">Loading subscribers…</p>
        </div>
        <div x-show="!loading && !rows.length" x-cloak class="c-pad" style="text-align:center;padding:48px">
            <p style="font-weight:600;margin:0">No subscribers yet</p>
            <p class="dim" style="margin:8px 0 0">Run Simulate 30 days on the dashboard or wait for live checkouts.</p>
        </div>
        <div id="grid-subs" class="ag-theme-quartz" style="width:100%;height:520px" x-show="rows.length" x-cloak></div>
    </div>
</div>
@endsection

@push('head')
<link rel="stylesheet" href="/vendor/ag-grid-32.3.3/ag-grid.min.css">
<link rel="stylesheet" href="/vendor/ag-grid-32.3.3/ag-theme-quartz.min.css">
@endpush
@push('scripts')
<script src="/vendor/ag-grid-32.3.3/ag-grid-community.min.noStyle.js" defer></script>
<script src="/js/subscriptions.js" defer></script>
@endpush

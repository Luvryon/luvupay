@extends('layouts.site')
@section('title', 'LuvuPay System Status')
@section('meta_description', 'Live public status for LuvuPay API, platform, payments, and webhooks')
@push('scripts')
    <script src="/js/alpine.min.js" defer></script>
    <script src="/js/status.js?v={{ @filemtime(public_path('js/status.js')) ?: time() }}" defer></script>
    <style>[x-cloak]{display:none!important}</style>
@endpush

@section('content')
<div class="mx-auto max-w-5xl px-5 py-16 sm:px-8 sm:py-20" x-data="statusPage()" x-init="init()">
    {{-- Header --}}
    <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-600">Live status</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-ink sm:text-4xl" x-text="title"></h1>
            <p class="mt-2 text-sm text-muted" x-text="subtitle"></p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-semibold"
                  x-bind:class="overall === 'operational'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : (overall === 'checking' ? 'border-line bg-wash text-muted' : 'border-red-200 bg-red-50 text-red-700')">
                <span class="size-2 rounded-full"
                      x-bind:class="overall === 'operational' ? 'bg-emerald-500' : (overall === 'checking' ? 'bg-slate-300' : 'bg-red-500')"
                      x-bind:style="overall === 'operational' ? 'box-shadow:0 0 0 3px rgba(16,185,129,0.25)' : ''"></span>
                <span x-text="overallLabel"></span>
            </span>
            <button type="button"
                    class="rounded-full border border-line bg-white px-3.5 py-1.5 text-xs font-semibold text-ink hover:bg-wash"
                    x-on:click="load()"
                    x-bind:disabled="overall === 'checking'">
                Refresh
            </button>
        </div>
    </div>

    {{-- Metric strip from real probe --}}
    <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <div class="text-[11px] font-medium uppercase tracking-wide text-muted">Probe</div>
            <div class="mt-1 text-lg font-semibold text-ink" x-text="probeName"></div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <div class="text-[11px] font-medium uppercase tracking-wide text-muted">HTTP</div>
            <div class="mt-1 text-lg font-semibold text-ink" x-text="httpCode || '—'"></div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <div class="text-[11px] font-medium uppercase tracking-wide text-muted">Latency</div>
            <div class="mt-1 text-lg font-semibold text-ink">
                <span x-text="latencyMs !== null ? latencyMs + ' ms' : '—'"></span>
            </div>
        </div>
        <div class="rounded-2xl border border-line bg-white p-4 shadow-sm">
            <div class="text-[11px] font-medium uppercase tracking-wide text-muted">Last check</div>
            <div class="mt-1 text-lg font-semibold text-ink" x-text="lastCheckedLabel"></div>
        </div>
    </div>

    {{-- Service cards --}}
    <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <template x-for="s in services" x-bind:key="s.name">
            <article class="rounded-2xl border border-line bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="mt-0.5 grid size-10 shrink-0 place-items-center rounded-xl"
                              x-bind:class="s.ok ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'">
                            <svg x-show="s.ok" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                            <svg x-show="!s.ok" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5M12 17h.01"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-ink" x-text="s.name"></h2>
                            <p class="mt-0.5 text-sm text-muted" x-text="s.detail"></p>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide"
                          x-bind:class="s.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                          x-text="s.ok ? 'Up' : 'Down'"></span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm">
                    <div>
                        <div class="text-[11px] text-muted">Response</div>
                        <div class="mt-0.5 font-semibold text-ink" x-text="s.latency"></div>
                    </div>
                    <div>
                        <div class="text-[11px] text-muted">Source</div>
                        <div class="mt-0.5 font-semibold text-ink" x-text="s.source"></div>
                    </div>
                </div>
            </article>
        </template>
    </div>

    <p class="mt-10 text-center text-xs leading-relaxed text-muted">
        Status is measured live from this app’s public liveness probe (<code class="rounded bg-wash px-1.5 py-0.5 font-mono text-[11px]">GET /up</code>).
        Auto-refreshes every 30 seconds. Internal component health is not exposed publicly.
    </p>
</div>
@endsection

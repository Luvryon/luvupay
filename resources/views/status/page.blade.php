@extends('layouts.site')
@section('title', 'LuvuPay System Status')
@section('meta_description', 'Public liveness and high-level component status for LuvuPay')
@push('scripts')
    <script src="/js/alpine.min.js" defer></script>
    <script src="/js/status.js?v={{ @filemtime(public_path('js/status.js')) ?: time() }}" defer></script>
    <style>[x-cloak]{display:none!important}</style>
@endpush

@section('content')
<div class="mx-auto max-w-3xl px-5 py-14 sm:px-8" x-data="statusPage()" x-init="init()">
    <div class="flex items-start gap-4">
        <span class="grid size-14 shrink-0 place-items-center rounded-2xl"
              x-bind:class="overall === 'operational' ? 'bg-emerald-50 text-emerald-600' : (overall === 'checking' ? 'bg-wash text-muted' : 'bg-red-50 text-red-600')">
            <span x-show="overall === 'checking'" class="lp-spinner lp-spinner--lg"></span>
            <svg x-show="overall === 'operational'" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
            <svg x-show="overall !== 'operational' && overall !== 'checking'" x-cloak width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5M12 17h.01"/></svg>
        </span>
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-600">System status</p>
            <h1 class="mt-0.5 text-3xl font-semibold tracking-tight text-ink" x-text="title"></h1>
            <p class="mt-1 text-sm text-muted" x-text="subtitle"></p>
        </div>
    </div>

    <div class="mt-10 overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
        <x-ui.loading show="overall === 'checking'" title="Checking systems" hint="Pinging the liveness probe" />
        <ul class="divide-y divide-line" aria-live="polite" x-show="overall !== 'checking'">
            <template x-for="s in services" x-bind:key="s.name">
                <li class="flex items-center justify-between gap-4 px-5 py-4">
                    <div>
                        <p class="font-semibold text-ink" x-text="s.name"></p>
                        <p class="text-xs text-muted" x-text="s.detail"></p>
                    </div>
                    <span class="rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide"
                          x-bind:class="s.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'"
                          x-text="s.ok ? 'Operational' : 'Outage'"></span>
                </li>
            </template>
        </ul>
    </div>
    <p class="mt-8 text-center text-xs text-muted">Auto-refreshes every 30 s · detailed internal health is not public</p>
</div>
@endsection

@extends('layouts.site')
@section('title', 'LuvuPay System Status')
@section('meta_description', 'Public liveness and high-level component status for LuvuPay')
@push('scripts')
    <script src="/js/alpine.min.js" defer></script>
    <script src="/js/status.js?v={{ @filemtime(public_path('js/status.js')) ?: time() }}" defer></script>
    <style>
      [x-cloak]{display:none!important}
      .st-bar{display:flex;gap:2px;height:28px;align-items:stretch}
      .st-bar span{flex:1 1 0;min-width:2px;border-radius:3px;transition:opacity .15s}
      .st-bar span:hover{opacity:1!important;transform:scaleY(1.08)}
      .st-ok{background:linear-gradient(180deg,#34d399,#10b981)}
      .st-degraded{background:linear-gradient(180deg,#fbbf24,#f59e0b)}
      .st-down{background:linear-gradient(180deg,#f87171,#ef4444)}
      .st-unknown{background:#e2e8f0}
    </style>
@endpush

@section('content')
<div class="mx-auto max-w-4xl px-5 py-14 sm:px-8" x-data="statusPage()" x-init="init()">
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
                <li class="px-5 py-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold text-ink" x-text="s.name"></p>
                            <p class="text-xs text-muted" x-text="s.detail"></p>
                        </div>
                        <span class="rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide"
                              x-bind:class="s.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'"
                              x-text="s.ok ? 'Operational' : 'Outage'"></span>
                    </div>
                    {{-- 90-day style history, different visual from Claude (rounded bars + gradient + hover) --}}
                    <div class="mt-3">
                        <div class="st-bar" role="img" x-bind:aria-label="90-day availability for " + s.name">
                            <template x-for="(day, i) in s.history" x-bind:key="i">
                                <span x-bind:class="day === 'ok' ? 'st-ok' : (day === 'degraded' ? 'st-degraded' : (day === 'down' ? 'st-down' : 'st-unknown'))"
                                      x-bind:style="'opacity:' + (day === 'ok' ? '0.92' : '1')"
                                      x-bind:title="dayLabel(day, i)"></span>
                            </template>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[11px] text-muted">
                            <span>90 days ago</span>
                            <span class="font-medium text-ink/70" x-text="s.uptime + ' % uptime'"></span>
                            <span>Today</span>
                        </div>
                    </div>
                </li>
            </template>
        </ul>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-[11px] text-muted">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm st-ok"></span> Operational</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm st-degraded"></span> Degraded</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm st-down"></span> Outage</span>
    </div>
    <p class="mt-4 text-center text-xs text-muted">Auto-refreshes every 30 s · detailed internal health is not public</p>
</div>
@endsection

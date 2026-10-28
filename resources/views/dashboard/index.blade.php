@extends('layouts.console')
@section('title', 'Overview')
@section('heading', 'Overview')
@section('csp_style', "'self' 'unsafe-inline'")

@push('head')
    <link rel="stylesheet" href="/assets/ag-grid-32.3.3/ag-grid.min.css">
    <link rel="stylesheet" href="/assets/ag-grid-32.3.3/ag-theme-quartz.min.css">
@endpush

@section('content')
@php
    $paypalConnected = (bool) ($paypalConnected ?? \App\Http\Middleware\EnsurePayPalConnected::isConnected(request()));
@endphp

<div x-data="consoleApp()" x-init="init()" class="stack">

    @if(! $paypalConnected)
        <div class="c-alert">
            <p><span class="font-semibold">PayPal not connected</span> Connect sandbox credentials to run real payouts and checkout simulations</p>
            <a href="{{ route('settings') }}" class="shrink-0 text-sm font-semibold text-stone-900 underline underline-offset-2">Open settings</a>
        </div>
    @endif

    {{-- Project bar --}}
    <x-ui.project-context :project="$project" />

    <div class="row">
        <div><h1 class="page-title">Overview</h1><p class="page-sub">Ledger and money movement for {{ $project->name ?? 'your project' }}</p></div>
        <div style="display:flex;align-items:center;gap:24px">
            <div class="usage" title="Monthly API quota"><div class="t"><span>API usage</span><span x-text="(usage.used || 0) + ' / ' + (usage.limit || 1000)"></span></div><div class="bar"><i x-bind:style="'width:' + usagePct + '%'"></i></div></div>
            <button type="button" class="c-btn" x-bind:class="loading ? 'is-busy' : ''" x-on:click="load()">Refresh</button>
        </div>
    </div>

    {{-- Empty state --}}
    <x-ui.dashboard-empty-state />

    <div x-show="hasData" x-cloak style="display:none" class="stack">

        <section class="cols cols-wallet">
            <div class="c-wallet">
                <div>
                    <p class="dim">Merchant wallet</p>
                    <p class="amt" x-text="walletBalance">$0.00</p>
                    <p class="dim" style="max-width:460px">Funded by resale fees and retention splits. Withdrawals go out as PayPal Payouts to recipients on your allow-list (Settings)</p>
                </div>
                <div class="act">
                    <button type="button" class="c-btn c-btn-light" x-show="paypalConnected" x-on:click="withdrawToPayPal()" x-bind:disabled="walletWithdrawing || walletBalanceMinor <= 0"><span x-text="walletWithdrawing ? 'Sending…' : 'Withdraw to PayPal'"></span></button>
                    <p class="dim" x-show="walletBalanceMinor > 0" x-cloak style="margin:8px 0 0;font-size:.8rem">PayPal fee <strong x-text="'−' + money(walletQuote.fee, walletCurrency)"></strong> from your wallet · you receive <strong x-text="money(walletQuote.net, walletCurrency)"></strong></p>
                    <a href="{{ route('settings') }}" class="c-btn c-btn-light" x-show="!paypalConnected" x-cloak>Connect PayPal to withdraw</a>
                    <span class="dim" style="color:#7ee2b8" x-show="walletBalanceMinor <= 0 && paypalConnected" x-cloak>Balance is clear. It refills on the next charge, resale, or retention</span>
                    <span class="dim" x-show="!paypalConnected" x-cloak>Link PayPal in Settings to enable withdrawals</span>
                </div>
            </div>
            <div class="c-card" aria-label="Insights">
                <div class="c-head"><div><h2 class="c-title">Insights</h2><p class="c-sub">From your ledger rows</p></div></div>
                <template x-for="(insight, idx) in aiInsights" :key="idx"><div class="c-item"><span x-text="insight.icon"></span><p style="margin:0" x-text="insight.text"></p></div></template>
                <p x-show="!aiInsights.length" class="c-empty">No insights yet. They appear once traffic comes in</p>
            </div>
        </section>

        <section class="c-card" aria-label="Key metrics"><div class="c-kpis">
            @foreach([['Revenue saved','revenueSaved','Retained via ledger'],['Chargebacks prevented','chargebacks','AI-retained rows'],['Active subscribers','subscriptions','From charges'],['Charity donated','charity','Real payouts'],['Resale volume','resaleVolume','Ledger sum']] as [$l,$k,$n])
            <div class="c-kpi"><p class="l" style="margin:0">{{ $l }}</p><p class="v" x-text="kpi.{{ $k }}">—</p><p class="n" style="margin:0">{{ $n }}</p></div>
            @endforeach
        </div></section>

        <div class="c-tabs" role="tablist">
            @foreach([['overview', 'Ledger'], ['resale', 'Resale'], ['deliveries', 'Webhooks']] as [$id, $label])
                <button type="button" role="tab" class="c-tab" x-on:click="setTab('{{ $id }}')" x-bind:aria-selected="tab === '{{ $id }}'">{{ $label }}</button>
            @endforeach
        </div>

        <section x-show="tab === 'overview'" class="cols cols-ledger">
            <div class="c-card">
                <div class="c-head"><div><h2 class="c-title">Live ledger</h2><p class="c-sub">Select a row to run lifecycle tests</p></div></div>
                <div id="grid-tx" class="ag-theme-quartz h-tx" style="width:100%"></div>
            </div>
            <div class="stack-sm" style="align-content:start">
                <div class="c-card c-pad">
                    <h2 class="c-title">Subscriber preview</h2><p class="c-sub">What the end user sees</p>
                    <div class="prev">
                        <div class="avatar" x-text="(preview.name || '?').charAt(0).toUpperCase()"></div>
                        <div style="flex:1;min-width:0"><b style="display:block" x-text="preview.name || 'No test run yet'"></b><span style="font-size:.82rem;color:var(--muted)" x-text="preview.email || 'Run a sandbox action to see a subscriber'"></span></div>
                        <span class="chip" x-text="preview.status || 'Idle'"></span>
                    </div>
                    <div class="box" x-show="preview.product" x-cloak><span style="color:var(--muted);font-size:.78rem">Product</span><br><b x-text="preview.product"></b></div>
                    <div class="box" x-show="preview.trust_score != null" x-cloak>
                        <span style="color:var(--muted);font-size:.78rem">Trust score</span>
                        <meter min="0" max="100" style="width:100%;margin-top:6px" x-bind:value="preview.trust_score || 0"></meter>
                        <span style="font-size:.82rem;color:var(--muted)">Churn risk: <b style="color:var(--ink)" x-text="preview.churn_risk ? preview.churn_risk.toUpperCase() : '—'"></b></span>
                    </div>
                </div>
                <div class="c-card c-pad">
                    <div class="row" style="margin-bottom:12px"><div><h2 class="c-title">Event log</h2><p class="c-sub">Webhook and platform events</p></div><button type="button" class="c-btn c-btn-sm" x-on:click="openWebhookLogs()">Open debugger</button></div>
                    <div style="max-height:200px;overflow:auto">
                        <template x-for="w in webhookLogs" :key="w.id"><div class="c-log"><span style="color:var(--faint)" x-text="(w.timestamp || '').replace('T', ' ').slice(11, 19)"></span><span style="color:var(--brand)" x-text="w.provider"></span><span x-text="w.event_type"></span><span style="margin-left:auto;color:var(--faint)" x-text="w.http_hint"></span></div></template>
                        <p x-show="!webhookLogs.length" class="c-empty" style="padding:28px 0">No events yet. Run a sandbox simulation</p>
                    </div>
                </div>
            </div>
        </section>

        <div x-show="tab === 'resale'" x-cloak class="c-card">
            <div class="c-head"><div><h2 class="c-title">Resale listings</h2><p class="c-sub">Remaining access listed by subscribers</p></div></div>
            <div id="grid-rs" class="ag-theme-quartz h-rs" style="width:100%"></div>
        </div>

        <div x-show="tab === 'deliveries'" x-cloak class="c-card">
            <div class="c-head"><div><h2 class="c-title">Webhook deliveries</h2><p class="c-sub">Outbound signed events</p></div><button type="button" class="c-btn c-btn-sm" x-on:click="loadDeliveries()">Refresh</button></div>
            <div style="overflow-x:auto"><table class="c-table">
                <thead><tr>@foreach(['Time','Event','Code','Attempts','Status',''] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                <template x-for="d in deliveries" :key="d.id"><tr>
                    <td class="mono" style="white-space:nowrap;color:var(--muted)" x-text="(d.created_at || '').replace('T', ' ').slice(0, 19)"></td>
                    <td x-text="d.event_type || d.event"></td><td class="mono" x-text="d.response_code || '—'"></td><td x-text="d.attempts ?? '—'"></td><td x-text="d.status"></td>
                    <td style="text-align:right"><button type="button" class="c-btn c-btn-sm" x-show="d.status === 'failed'" x-on:click="retryDelivery(d.id)">Retry</button></td>
                </tr></template>
                <tr x-show="!deliveriesLoaded"><td colspan="6" style="padding:0"><x-ui.loading show="true" title="Loading deliveries" compact /></td></tr>
                <tr x-show="deliveriesLoaded && !deliveries.length"><td colspan="6" class="c-empty">No deliveries yet</td></tr>
                </tbody>
            </table></div>
        </div>

@if(config('luvupay.demo_simulator'))
    {{-- Sandbox Controls — professional SaaS workflow triggers --}}
    <section class="bg-amber-50 border border-amber-200 rounded-xl p-6" x-show="demoEnabled" x-cloak>
        <p class="text-amber-800 text-sm font-medium mb-4">
            ⚠️ Sandbox controls. These run real backend logic against PayPal Sandbox and are disabled in production
        </p>

        <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-900 mb-1">
                {{ $project->product() }} · {{ $project->price() }}/{{ $project->interval === 'month' ? 'mo' : $project->interval }}
            </h2>
            <p class="text-sm text-slate-600 max-w-xl">
                Each action writes real database rows, revenue split math, an AI retention nudge, webhook events, and charity receipts
            </p>
            <p class="mt-1 text-xs font-mono text-slate-500" x-show="selectedId" x-cloak>
                Selected <span class="font-semibold" x-text="String(selectedId).slice(0, 8) + '…'"></span>
                · <span class="text-emerald-600 font-semibold" x-text="selectedLifecycle || '—'"></span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Primary: start of lifecycle --}}
            <button
                type="button"
                class="inline-flex items-center justify-center bg-indigo-600 text-white px-4 py-2.5 rounded-lg hover:bg-indigo-700 font-semibold shadow-sm disabled:opacity-50 disabled:cursor-not-allowed border border-indigo-700"
                style="background-color:#4f46e5;color:#fff"
                x-on:click="openCheckout()"
                x-bind:disabled="simBusy || checkoutPaying"
            >
                New subscription checkout
            </button>

            {{-- Secondary: retention flow / Dual-View Simulator --}}
            <button
                type="button"
                class="bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                x-on:click="openDecision()"
                x-bind:disabled="simBusy || decisionOpen || (selectedLifecycle && selectedLifecycle !== 'ACTIVE')"
            >
                Missed cancellation journey
            </button>

            {{-- Tertiary: debug --}}
            <button
                type="button"
                class="text-slate-500 hover:text-indigo-600 px-4 py-2 font-medium text-sm"
                x-on:click="openWebhookLogs()"
            >
                Webhook logs
            </button>

            <button
                type="button"
                class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 font-medium shadow-sm text-sm"
                x-on:click="campaignStudioOpen = true"
            >
                AI Retention Campaign Studio
            </button>
        </div>

        <p
            class="mt-3 text-sm"
            x-show="simMsg"
            x-cloak
            x-bind:class="simMsgOk ? 'text-emerald-600' : 'text-red-600'"
            x-text="simMsg"
            role="status"
        ></p>
    </section>

        </div>{{-- hasData --}}

    @include('dashboard.partials.checkout-modal')

    @include('dashboard.partials.journey-modal')

    @include('dashboard.partials.campaign-studio-modal')

    @include('dashboard.partials.evidence-modal')

    @include('dashboard.partials.withdrawal-receipt-modal')

    {{-- Receipt modal --}}
    <x-ui.modal show="receiptOpen" title="Transaction receipt" size="md">
        <div class="p-5">
            <dl class="space-y-2 font-mono text-sm">
                @foreach([['Amount', 'amount'], ['Store credit', 'store_credit'], ['Charity', 'charity'], ['Fee', 'fee'], ['Certificate', 'certificate_code'], ['Transaction', 'transaction_id'], ['PayPal payout', 'paypal_payout_id']] as [$label, $key])
                    <div class="flex justify-between gap-3"><dt class="text-muted">{{ $label }}</dt><dd class="max-w-[190px] truncate" x-text="receipt.{{ $key }}"></dd></div>
                @endforeach
            </dl>
        </div>
    </x-ui.modal>

    {{-- Webhook debugger --}}
    <x-ui.modal show="webhookOpen" title="Webhook debugger" size="xl">
        <div class="p-5">
            <p class="mb-3 text-xs text-muted">Last provider and platform events · proves the durable inbox and processing state</p>
            <x-ui.table>
                <x-slot:head><tr><th class="px-4 py-3">Time</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Payload</th></tr></x-slot:head>
                <template x-for="w in webhookLogs" x-bind:key="w.id">
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-muted" x-text="(w.timestamp || '').replace('T', ' ').slice(0, 19)"></td>
                        <td class="px-4 py-2.5"><span class="font-medium text-brand-soft" x-text="w.event_type"></span><span class="block text-xs text-faint" x-text="w.provider"></span></td>
                        <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold" x-bind:class="String(w.http_hint).includes('200') ? 'bg-ok/15 text-ok' : 'bg-bad/15 text-bad'" x-text="w.http_hint"></span></td>
                        <td class="max-w-xs truncate px-4 py-2.5 font-mono text-xs text-muted" x-text="w.payload_preview"></td>
                    </tr>
                </template>
                <tr x-show="!webhookLogs.length"><td colspan="4" class="px-4 py-10 text-center text-muted">No events yet — run a sandbox test</td></tr>
            </x-ui.table>
        </div>
    </x-ui.modal>

    @endif {{-- demo_simulator --}}

    


</div>

@endsection

@push('scripts')
    @php
        $lpConfig = [
            'apiBase' => '/api/v1/dashboard',
            'merchantName' => $merchantName ?? 'Merchant',
            'demoEnabled' => (bool) config('luvupay.demo_simulator'),
            'project' => isset($project) ? $project->toArray() : [],
            // paypal only when the driver says so AND credentials exist (same rule as DemoCheckoutController)
            'paymentMode' => (config('luvupay.payment.driver') === 'paypal' && filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret'))) ? 'paypal' : 'mock',
            'paypalEnv' => config('services.paypal.mode', 'sandbox'),
        ];
    @endphp
    <script type="application/json" id="lp-config">@json($lpConfig)</script>
    <script src="/assets/ag-grid-32.3.3/ag-grid-community.min.noStyle.js" defer></script>
    <script src="/js/dashboard.js?v={{ @filemtime(public_path('js/dashboard.js')) }}" defer></script>
@endpush

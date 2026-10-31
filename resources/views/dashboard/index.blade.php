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
        <div><h1 class="page-title">Overview</h1><p class="page-sub">Wallet, ledger, and retention tools for {{ $project->name ?? 'your project' }}</p></div>
        <div style="display:flex;align-items:center;gap:24px">
            <div class="usage" title="Monthly API quota"><div class="t"><span>API usage</span><span x-text="(usage.used || 0) + ' / ' + (usage.limit || 1000)"></span></div><div class="bar"><i x-bind:style="'width:' + usagePct + '%'"></i></div></div>
            <button type="button" class="c-btn" x-bind:class="loading ? 'is-busy' : ''" x-on:click="load()">Refresh</button>
        </div>
    </div>

    {{-- Empty state --}}
    <x-ui.dashboard-empty-state />

    <div x-show="hasData" x-cloak style="display:none" class="stack">

        <section class="cols cols-wallet">
            <style>
                /* ===== Merchant wallet: dompet kulit dengan kartu mengintip ===== */
                .wl{--leather-1:#1b1590;--leather-2:#09027b;--leather-3:#05024a;--gold-1:#f6e3a1;--gold-2:#e0b84f;--gold-3:#a8801f;position:relative;padding-top:3rem;min-width:0}
                .wl *{box-sizing:border-box}

                /* kartu yang mengintip dari slot dompet */
                .wl-cards{position:absolute;left:0;right:0;top:0;height:10rem;pointer-events:none}
                .wl-card{position:absolute;top:0;width:15.5rem;max-width:62%;aspect-ratio:1.586;border-radius:14px;transition:transform .5s cubic-bezier(.2,.8,.3,1);animation:wlRise .9s cubic-bezier(.2,.8,.3,1) both}
                .wl-c2{left:2.4rem;transform:rotate(-5deg);background:linear-gradient(135deg,#f7f3e6 0%,#e4dcc3 100%);box-shadow:0 10px 24px -12px rgba(5,2,74,.55),inset 0 0 0 1px rgba(168,128,31,.35);animation-delay:.05s}
                .wl-c1{left:5.2rem;transform:rotate(2deg);background:linear-gradient(135deg,#6d5cf6 0%,#3d2fd0 55%,#241a9e 100%);box-shadow:0 14px 28px -12px rgba(5,2,74,.7),inset 0 0 0 1px rgba(255,255,255,.18);animation-delay:.18s;overflow:hidden}
                .wl-c1 svg.wl-guil{position:absolute;right:-30%;top:-40%;width:90%;opacity:.5}
                .wl-c1 .wl-chip{position:absolute;left:12px;top:30px;width:30px;height:22px}
                .wl-c1 b{position:absolute;left:12px;top:9px;font-size:10px;font-weight:700;letter-spacing:-.01em;color:#fff}
                .wl-c1 small{position:absolute;left:12px;top:60px;font:500 9px/1 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.14em;color:rgba(255,255,255,.7)}
                .wl:hover .wl-c1{transform:translateY(-12px) rotate(3deg)}
                .wl:hover .wl-c2{transform:translateY(-6px) rotate(-8deg)}
                @keyframes wlRise{from{opacity:0;transform:translateY(34px) rotate(0)}}

                /* badan dompet */
                .wl-body{position:relative;height:100%;z-index:1;display:grid;gap:1.25rem;grid-template-columns:minmax(0,1fr);padding:1.6rem 1.6rem 1.7rem;border-radius:1.4rem;color:#fff;
                    background-image:
                        url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='180' height='180'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .55 0'/></filter><rect width='100%' height='100%' filter='url(%23n)' opacity='.22'/></svg>"),
                        linear-gradient(160deg,var(--leather-1) 0%,var(--leather-2) 52%,var(--leather-3) 100%);
                    box-shadow:0 34px 50px -30px rgba(5,2,74,.75),0 2px 0 rgba(255,255,255,.14) inset,0 -10px 18px -8px rgba(0,0,0,.35) inset}
                /* lipatan atas, tempat kartu masuk */
                .wl-body::before{content:"";position:absolute;left:1.2rem;right:1.2rem;top:0;height:14px;border-radius:0 0 14px 14px;background:linear-gradient(180deg,rgba(0,0,0,.4),rgba(0,0,0,0));pointer-events:none}
                /* jahitan */
                .wl-stitch{position:absolute;inset:9px;border-radius:1.05rem;border:1.5px dashed rgba(246,227,161,.55);pointer-events:none}

                .wl-main{position:relative;min-width:0}
                .wl-label{display:flex;align-items:center;gap:.5rem;margin:0;font-size:.82rem;color:rgba(255,255,255,.7)}
                .wl-coin{flex:none;width:22px;height:22px;transition:transform .6s cubic-bezier(.2,.8,.3,1)}
                .wl:hover .wl-coin{transform:rotateY(360deg)}
                .wl-amt{margin:.35rem 0 0;font-size:clamp(2.1rem,4.4vw,2.9rem);line-height:1.05;font-weight:700;letter-spacing:-.03em;color:#fff7e0;font-variant-numeric:tabular-nums;text-shadow:0 1px 0 rgba(0,0,0,.45),0 0 22px rgba(224,184,79,.28)}
                .wl-dim{margin:.7rem 0 0;max-width:460px;font-size:.82rem;line-height:1.55;color:rgba(255,255,255,.62)}

                /* kantong tempat tombol tarik dana */
                .wl-slot{position:relative;display:flex;flex-direction:column;align-items:flex-start;gap:.55rem;padding:.95rem 1.05rem;border-radius:1rem;background:rgba(3,1,40,.42);box-shadow:inset 0 3px 10px rgba(0,0,0,.45),0 1px 0 rgba(255,255,255,.1)}
                .wl-slot .wl-dim{margin:0;font-size:.78rem}
                .wl-slot strong{color:#fff;font-weight:600}
                .wl-btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;padding:.62rem 1.1rem;border:0;border-radius:99px;cursor:pointer;text-decoration:none;font:600 .85rem/1 inherit;color:#3b2a00;background:linear-gradient(180deg,var(--gold-1),var(--gold-2));box-shadow:0 8px 16px -8px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.7),inset 0 -2px 0 var(--gold-3);transition:transform .15s,filter .15s}
                .wl-btn:hover:not(:disabled){transform:translateY(-1px);filter:brightness(1.05)}
                .wl-btn:active:not(:disabled){transform:translateY(1px)}
                .wl-btn:disabled{opacity:.5;cursor:not-allowed}
                .wl-btn:focus-visible{outline:2px solid #fff;outline-offset:2px}

                /* tali dan kancing penutup di sisi kanan */
                .wl-strap{display:none;position:absolute;z-index:2;right:-10px;top:46%;width:3.4rem;height:2.7rem;border-radius:.5rem .9rem .9rem .5rem;
                    background:linear-gradient(180deg,#2a22b4,#120b8c);box-shadow:0 8px 14px -6px rgba(0,0,0,.5),inset 0 1px 0 rgba(255,255,255,.2)}
                .wl-strap::before{content:"";position:absolute;inset:4px;border-radius:.4rem .7rem .7rem .4rem;border:1.5px dashed rgba(246,227,161,.5)}
                .wl-snap{position:absolute;left:.65rem;top:50%;width:1.25rem;height:1.25rem;margin-top:-.625rem;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff6cf,var(--gold-2) 55%,var(--gold-3));box-shadow:0 2px 4px rgba(0,0,0,.5),inset 0 -2px 3px rgba(0,0,0,.25)}
                .wl-snap::after{content:"";position:absolute;inset:4px;border-radius:50%;border:1px solid rgba(90,60,0,.35)}

                @media (min-width:700px){
                    .wl-body{grid-template-columns:minmax(0,1fr) minmax(0,.85fr);align-items:center;padding:1.9rem 3.6rem 1.9rem 1.9rem}
                    .wl-strap{display:block}
                }
                @media (prefers-reduced-motion:reduce){
                    .wl-card{animation:none}
                    .wl:hover .wl-c1,.wl:hover .wl-c2,.wl:hover .wl-coin{transform:none}
                    .wl-c1{transform:rotate(2deg)}.wl-c2{transform:rotate(-5deg)}
                }
            </style>

            <div class="wl">
                {{-- kartu yang mengintip dari slot --}}
                <div class="wl-cards" aria-hidden="true">
                    <div class="wl-card wl-c2"></div>
                    <div class="wl-card wl-c1">
                        <svg class="wl-guil" viewBox="-100 -100 200 200" fill="none" stroke="#fff" stroke-width=".5" stroke-opacity=".5">
                            <ellipse rx="90" ry="24"/><ellipse rx="90" ry="24" transform="rotate(30)"/><ellipse rx="90" ry="24" transform="rotate(60)"/><ellipse rx="90" ry="24" transform="rotate(90)"/><ellipse rx="90" ry="24" transform="rotate(120)"/><ellipse rx="90" ry="24" transform="rotate(150)"/><circle r="36"/>
                        </svg>
                        <b>LuvuPay</b>
                        <svg class="wl-chip" viewBox="0 0 30 22" fill="none"><rect width="30" height="22" rx="4" fill="#e0b84f"/><path d="M0 8h30M0 14h30M10 0v22M20 0v22" stroke="#a8801f" stroke-width=".9"/><rect x="10" y="8" width="10" height="6" rx="1.5" fill="#f6e3a1"/></svg>
                        <small>•••• 0048</small>
                    </div>
                </div>

                <div class="wl-body">
                    <span class="wl-stitch" aria-hidden="true"></span>

                    <div class="wl-main">
                        <p class="wl-label">
                            <svg class="wl-coin" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="11" fill="#a8801f"/>
                                <circle cx="12" cy="12" r="9.6" fill="#e0b84f"/>
                                <circle cx="12" cy="12" r="7.2" fill="none" stroke="#a8801f" stroke-width=".9" stroke-dasharray="1.6 1.4"/>
                                <text x="12" y="16" font-size="10.5" font-weight="800" text-anchor="middle" fill="#6b4c00" font-family="ui-sans-serif,system-ui,sans-serif">$</text>
                            </svg>
                            Merchant wallet
                        </p>
                        <p class="wl-amt" x-text="walletBalance">$0.00</p>
                        <p class="wl-dim">Balance from settled charges (minus refunds, charity, withdrawals).</p>
                    </div>

                    <div class="wl-slot">
                        <button type="button" class="wl-btn" x-show="paypalConnected" x-on:click="withdrawToPayPal()" x-bind:disabled="walletWithdrawing || walletBalanceMinor <= 0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                            <span x-text="walletWithdrawing ? 'Sending…' : 'Withdraw to PayPal'"></span>
                        </button>
                        <p class="wl-dim" x-show="walletBalanceMinor > 0" x-cloak>PayPal fee <strong x-text="'−' + money(walletQuote.fee, walletCurrency)"></strong> from your wallet · you receive <strong x-text="money(walletQuote.net, walletCurrency)"></strong></p>
                        <a href="{{ route('settings') }}" class="wl-btn" x-show="!paypalConnected" x-cloak>Connect PayPal to withdraw</a>
                        <span class="wl-dim" style="color:#7ee2b8" x-show="walletBalanceMinor <= 0 && paypalConnected" x-cloak>Balance is clear. It refills on the next charge, resale, or retention</span>
                        <span class="wl-dim" x-show="!paypalConnected" x-cloak>Link PayPal in Settings to enable withdrawals</span>
                    </div>

                    <div class="wl-strap" aria-hidden="true"><i class="wl-snap"></i></div>
                </div>
            </div>

            <div class="c-card" aria-label="Insights">
                <div class="c-head"><div><h2 class="c-title">Insights</h2><p class="c-sub">From your ledger rows</p></div></div>
                <template x-for="(insight, idx) in aiInsights" :key="idx">
                    <div class="c-item" style="display:flex;gap:10px;align-items:flex-start">
                        <span class="grid place-items-center rounded-lg bg-brand-50 text-brand-600" style="width:32px;height:32px;flex-shrink:0" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="insightIcon(insight.icon)"></svg>
                        </span>
                        <p style="margin:0" x-text="insight.text"></p>
                    </div>
                </template>
                <p x-show="!aiInsights.length" class="c-empty">No insights yet. They appear after your first settled charges.</p>
            </div>
        </section>

        <section class="c-card" aria-label="Key metrics"><div class="c-kpis">
            @foreach([['Revenue saved','revenueSaved','Retained via ledger'],['Chargebacks prevented','chargebacks','AI-retained rows'],['Active subscribers','subscriptions','From charges'],['Charity donated','charity','Real payouts'],['Resale volume','resaleVolume','Ledger sum']] as [$l,$k,$n])
            <div class="c-kpi"><p class="l" style="margin:0">{{ $l }}</p><p class="v" x-text="kpi.{{ $k }}">-</p><p class="n" style="margin:0">{{ $n }}</p></div>
            @endforeach
        </div></section>

        <div class="c-tabs" role="tablist">
            @foreach([['overview', 'Ledger'], ['resale', 'Resale'], ['deliveries', 'Webhooks']] as [$id, $label])
                <button type="button" role="tab" class="c-tab" x-on:click="setTab('{{ $id }}')" x-bind:aria-selected="tab === '{{ $id }}'">{{ $label }}</button>
            @endforeach
        </div>

        <section x-show="tab === 'overview'" class="cols cols-ledger">
            <div class="c-card">
                <div class="c-head"><div><h2 class="c-title">Live ledger</h2><p class="c-sub">Select a row to run sandbox lifecycle actions</p></div></div>
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
                        <span style="font-size:.82rem;color:var(--muted)">Churn risk: <b style="color:var(--ink)" x-text="preview.churn_risk ? preview.churn_risk.toUpperCase() : '-'"></b></span>
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
                    <td x-text="d.event_type || d.event"></td><td class="mono" x-text="d.response_code || '-'"></td><td x-text="d.attempts ?? '-'"></td><td x-text="d.status"></td>
                    <td style="text-align:right"><button type="button" class="c-btn c-btn-sm" x-show="d.status === 'failed'" x-on:click="retryDelivery(d.id)">Retry</button></td>
                </tr></template>
                <tr x-show="!deliveriesLoaded"><td colspan="6" style="padding:0"><x-ui.loading show="true" title="Loading deliveries" compact /></td></tr>
                <tr x-show="deliveriesLoaded && !deliveries.length"><td colspan="6" class="c-empty">No deliveries yet</td></tr>
                </tbody>
            </table></div>
        </div>

@if(config('luvupay.demo_simulator'))
    {{-- Sandbox Controls - professional SaaS workflow triggers --}}
    <section class="bg-amber-50 border border-amber-200 rounded-xl p-6" x-show="demoEnabled" x-cloak>
        <p class="text-amber-800 text-sm font-medium mb-4">
            Sandbox controls. These run real backend logic against PayPal Sandbox and are disabled in production
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
                · <span class="text-emerald-600 font-semibold" x-text="selectedLifecycle || '-'"></span>
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

            <button
                type="button"
                class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 font-medium shadow-sm text-sm disabled:opacity-50"
                x-show="selectedRow && selectedRow.split && selectedRow.split.status === 'active'" x-cloak
                x-on:click="collectInstallment(selectedRow)"
                x-bind:disabled="simBusy"
            >
                Collect next instalment
            </button>

            {{-- Tertiary: debug --}}
            <button
                type="button"
                class="text-[#2c2b2b] bg-[#c5c5c5] hover:text-indigo-600 px-4 py-2 font-medium text-sm"
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

    @include('dashboard.partials.negotiator-modal')

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
                <tr x-show="!webhookLogs.length"><td colspan="4" class="px-4 py-10 text-center text-muted">No events yet - run a sandbox test</td></tr>
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
    <script src="/js/campaign-studio.js?v={{ @filemtime(public_path('js/campaign-studio.js')) }}" defer></script>
    <script src="/js/dashboard.js?v={{ @filemtime(public_path('js/dashboard.js')) }}" defer></script>
@endpush

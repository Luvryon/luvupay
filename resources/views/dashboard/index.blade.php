@extends('layouts.console')
@section('title', 'Overview')
@section('heading', 'Overview')
@section('csp_style', "'self' 'unsafe-inline'")

@push('head')
    <link rel="stylesheet" href="/vendor/ag-grid-32.3.3/ag-grid.min.css">
    <link rel="stylesheet" href="/vendor/ag-grid-32.3.3/ag-theme-quartz.min.css">
@endpush

@section('content')
@php
    $paypalConnected = (bool) ($paypalConnected ?? \App\Http\Middleware\EnsurePayPalConnected::isConnected(request()));
@endphp

<div x-data="consoleApp()" x-init="init()" class="stack">

    @if(! $paypalConnected)
        <div class="c-alert">
            <p><span class="font-semibold">PayPal not connected.</span> Connect sandbox credentials to run real payouts and checkout simulations.</p>
            <a href="{{ route('settings') }}" class="shrink-0 text-sm font-semibold text-stone-900 underline underline-offset-2">Open settings</a>
        </div>
    @endif

    {{-- Project bar --}}
    <x-ui.project-context :project="$project" />

    <div class="row">
        <div><h1 class="page-title">Overview</h1><p class="page-sub">Ledger and money movement for {{ $project->name ?? 'your project' }}</p></div>
        <div style="display:flex;align-items:center;gap:24px">
            <div class="usage" title="Monthly API quota"><div class="t"><span>API usage</span><span x-text="(usage.used || 0) + ' / ' + (usage.limit || 1000)"></span></div><div class="bar"><i x-bind:style="'width:' + usagePct + '%'"></i></div></div>
            <button type="button" class="c-btn" x-on:click="load()"><span x-text="loading ? 'Refreshing…' : 'Refresh'"></span></button>
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
                    <p class="dim" style="max-width:460px">Funded by resale fees and retention splits. Withdrawals go out as PayPal Payouts to recipients on your allow-list (Settings).</p>
                </div>
                <div class="act">
                    <button type="button" class="c-btn c-btn-light" x-on:click="withdrawToPayPal()" x-bind:disabled="walletWithdrawing || walletBalanceMinor <= 0"><span x-text="walletWithdrawing ? 'Sending…' : 'Withdraw to PayPal'"></span></button>
                    <span class="dim" style="color:#7ee2b8" x-show="walletBalanceMinor <= 0" x-cloak>Balance is clear. It refills on the next resale or retention.</span>
                </div>
            </div>
            <div class="c-card" aria-label="Insights">
                <div class="c-head"><div><h2 class="c-title">Insights</h2><p class="c-sub">From your ledger rows</p></div></div>
                <template x-for="(insight, idx) in aiInsights" :key="idx"><div class="c-item"><span x-text="insight.icon"></span><p style="margin:0" x-text="insight.text"></p></div></template>
                <p x-show="!aiInsights.length" class="c-empty">No insights yet. They appear once traffic comes in.</p>
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
                        <p x-show="!webhookLogs.length" class="c-empty" style="padding:28px 0">No events yet. Run a sandbox simulation.</p>
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
                <tr x-show="!deliveries.length"><td colspan="6" class="c-empty">No deliveries yet</td></tr>
                </tbody>
            </table></div>
        </div>

@if(config('luvupay.demo_simulator'))
    {{-- Sandbox / Demo Controls — clearly marked, professional labels --}}
    <section class="c-card demo" x-show="demoEnabled" x-cloak>
        <div class="demo-bar"><x-ui.icon name="alert" :size="16" /><span><b>Sandbox controls.</b> These run real backend logic against PayPal Sandbox and are disabled in production.</span></div>
        <div class="demo-body">
            <div class="row" style="align-items:flex-start">
                <div>
                    <h2 class="c-title" style="font-size:1.1rem">{{ $project->product() }} · {{ $project->price() }}/{{ $project->interval === 'month' ? 'mo' : $project->interval }}</h2>
                    <p class="c-sub" style="max-width:560px">Each simulation writes real database rows, split math, an AI nudge, webhook events and charity receipts.</p>
                </div>
                <p class="mono" style="margin:0;color:var(--muted)" x-show="selectedId" x-cloak>Selected <b x-text="String(selectedId).slice(0, 8) + '…'"></b> · <b style="color:var(--ok)" x-text="selectedLifecycle || '—'"></b></p>
            </div>
            <div class="demo-btns">
                <button type="button" class="c-btn c-btn-brand" x-on:click="openCheckout()" x-bind:disabled="simBusy || checkoutPaying">New subscription checkout</button>
                <button type="button" class="c-btn c-btn-warn" x-on:click="openDecision()" x-bind:disabled="simBusy || decisionOpen || (selectedLifecycle && selectedLifecycle !== 'ACTIVE')">Missed cancellation journey</button>
                <button type="button" class="c-btn" x-on:click="openWebhookLogs()">Webhook logs</button>
                <button type="button" class="c-btn c-btn-bad" x-on:click="maxRateLimit()" x-bind:disabled="simBusy"><span x-text="simBusy === 'ratelimit' ? 'Working…' : 'Trigger rate limit'"></span></button>
                <button type="button" class="c-btn" x-on:click="resetRateLimit()">Reset quota</button>
            </div>
            <p style="margin:0;font-size:.9rem" x-show="simMsg" x-cloak x-bind:style="simMsgOk ? 'color:#0f8f5f' : 'color:#b4232f'" x-text="simMsg" role="status"></p>
        </div>
    </section>

    {{-- Checkout modal --}}
    <x-ui.modal show="showCheckoutModal" title="New Subscription Checkout" size="md">
        <div class="space-y-4 p-5">
            <p class="text-sm text-muted">Simulates a subscriber completing checkout for <strong class="text-white">{{ $project->product() }}</strong>.</p>
            <div>
                <label class="mb-1 block text-xs font-medium text-muted">Display name</label>
                <input type="text" x-model="checkoutForm.name" placeholder="John Doe"
                       class="block w-full rounded-xl border border-edge bg-black/30 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-muted">Email</label>
                <input type="email" x-model="checkoutForm.email" placeholder="john@example.com"
                       class="block w-full rounded-xl border border-edge bg-black/30 px-3.5 py-2.5 text-sm text-ink placeholder:text-faint focus:border-brand focus:ring-2 focus:ring-brand/30 focus:outline-none" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="ghost" x-on:click="showCheckoutModal = false" x-bind:disabled="checkoutPaying">Cancel</x-ui.button>
                <x-ui.button icon="bolt" x-on:click="confirmCheckout()" x-bind:disabled="checkoutPaying">
                    <span x-text="checkoutPaying ? 'Processing…' : 'Confirm & charge'"></span>
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    {{-- Missed Cancellation decision modal — Demo Mode: Mock vs Real PayPal Sandbox --}}
    <x-ui.modal show="decisionOpen" title="Missed Cancellation Journey" size="lg">
        <div class="p-5">
            <p class="mb-4 text-sm text-muted">
                H-7 → Day H flow for <span class="font-semibold text-white" x-text="decisionCustomer"></span>.
            </p>

            {{-- Demo Mode toggle --}}
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-edge bg-black/30 px-4 py-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">Demo Mode</p>
                    <p class="mt-0.5 text-xs text-faint" x-text="apiMode === 'mock' ? 'Instant UI — no PayPal network wait' : 'Routes through PayPal Sandbox latency path'"></p>
                </div>
                <div class="inline-flex rounded-lg border border-edge bg-slate-900/80 p-0.5 text-xs font-semibold" role="group" aria-label="Demo mode">
                    <button type="button"
                            class="rounded-md px-3 py-1.5 transition"
                            x-bind:class="apiMode === 'mock' ? 'bg-emerald-500/20 text-emerald-300 shadow-sm' : 'text-slate-400 hover:text-slate-200'"
                            x-on:click="apiMode = 'mock'"
                            x-bind:disabled="decisionBusy">Mock (Instant)</button>
                    <button type="button"
                            class="rounded-md px-3 py-1.5 transition"
                            x-bind:class="apiMode === 'paypal' ? 'bg-emerald-500/20 text-emerald-300 shadow-sm' : 'text-slate-400 hover:text-slate-200'"
                            x-on:click="apiMode = 'paypal'"
                            x-bind:disabled="decisionBusy">Real PayPal Sandbox</button>
                </div>
            </div>

            {{-- PayPal connecting spinner --}}
            <div x-show="paypalConnecting" x-cloak x-transition class="mb-4 flex items-center gap-3 rounded-xl border border-brand/30 bg-brand/10 px-4 py-3">
                <svg class="size-5 animate-spin text-brand-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9"/></svg>
                <p class="text-sm font-medium text-slate-100">Connecting to PayPal Sandbox API…</p>
            </div>

            <div id="decision-log" class="mb-4 max-h-48 space-y-1.5 overflow-auto rounded-xl border border-edge bg-black/40 p-3 font-mono text-[11px]">
                <template x-for="(line, i) in decisionLogs" x-bind:key="i">
                    <p x-bind:class="decisionToneClass(line.tone)"><span class="text-faint">▸ </span><span x-text="line.text"></span></p>
                </template>
            </div>

            <div class="flex flex-wrap gap-2" x-show="decisionReady && !decisionOutcome" x-cloak>
                <x-ui.button variant="success" x-on:click="choose('renew')" x-bind:disabled="decisionBusy">
                    <span x-text="decisionBusy && !paypalConnecting ? 'Working…' : 'Retry payment → ACTIVE'"></span>
                </x-ui.button>
                <x-ui.button variant="warn" x-on:click="choose('resale')" x-bind:disabled="decisionBusy">
                    Resell remaining → PENDING RESALE
                </x-ui.button>
                <x-ui.button variant="danger" x-on:click="choose('cancel')" x-bind:disabled="decisionBusy">
                    Let expire → CHURNED
                </x-ui.button>
            </div>

            <div x-show="decisionOutcome" x-cloak x-transition class="rounded-xl border border-ok/30 bg-ok/10 p-4">
                <p class="font-semibold text-ok" x-text="decisionOutcomeLabel"></p>
                <p class="mt-1 text-xs text-muted" x-text="decisionResultLog"></p>
            </div>
        </div>
    </x-ui.modal>

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
            <p class="mb-3 text-xs text-muted">Last provider and platform events · proves the durable inbox and processing state.</p>
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
                <tr x-show="!webhookLogs.length"><td colspan="4" class="px-4 py-10 text-center text-muted">No events yet — run a sandbox test.</td></tr>
            </x-ui.table>
        </div>
    </x-ui.modal>

    @endif {{-- demo_simulator --}}

    
    {{-- Seed / Simulate 30 Days review modal --}}
    <x-ui.toasts />
        </div>{{-- hasData --}}

    {{-- Seed modal must live outside hasData so empty-state can open it --}}
    {{-- Simulate 30 days modal (outside hasData) --}}
    <div x-show="seedModalOpen" x-cloak class="modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="seed-modal-title">
        <div class="modal-scrim" x-on:click="seedModalOpen = false"></div>
        <div class="modal-box modal-lg" x-on:click.stop
             x-show="seedModalOpen"
             x-transition:enter="m-enter" x-transition:enter-start="m-from" x-transition:enter-end="m-to">
            <div class="modal-head">
                <div>
                    <h3 id="seed-modal-title">Simulate 30 days</h3>
                    <p style="margin:4px 0 0;font-size:.82rem;color:var(--muted);font-weight:400">Review rows, then send real PayPal Sandbox checkouts for this project.</p>
                </div>
                <button type="button" class="modal-x" x-on:click="seedModalOpen = false" aria-label="Close">
                    <x-ui.icon name="x" :size="18" />
                </button>
            </div>
            <div class="modal-body" style="padding:20px 24px">
                <div class="field" style="margin-bottom:16px">
                    <label class="label" for="seed-project">Project</label>
                    <input id="seed-project" type="text" x-model="seedProjectName" class="input" readonly
                           style="background:var(--bg);font-weight:600" />
                    <p class="help" x-show="!seedProjectName" style="color:#d92d20">Create a project first before simulating.</p>
                </div>
                <p class="label" style="margin-bottom:8px">Subscribers to charge <span style="font-weight:400;color:var(--muted)">(edit name / email)</span></p>
                <div style="max-height:280px;overflow:auto;display:grid;gap:8px">
                    <template x-for="(row, idx) in seedDraftRows" :key="idx">
                        <label class="flex items-start gap-3" style="border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff;cursor:pointer">
                            <input type="checkbox" x-model="row.selected" style="margin-top:10px;accent-color:var(--brand)" />
                            <div style="flex:1;min-width:0;display:grid;gap:8px;grid-template-columns:1fr 1fr">
                                <input type="text" x-model="row.name" class="input" placeholder="Display name" style="padding:9px 12px" />
                                <input type="email" x-model="row.email" class="input" placeholder="Email" style="padding:9px 12px" />
                            </div>
                        </label>
                    </template>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="c-btn" x-on:click="seedModalOpen = false">Cancel</button>
                <button type="button" class="c-btn c-btn-brand"
                        x-bind:disabled="loadingSeed || !seedProjectName"
                        x-on:click="confirmSeedSandbox()">
                    <span x-show="!loadingSeed">Send to PayPal Sandbox</span>
                    <span x-show="loadingSeed" x-cloak style="display:none">Sending…</span>
                </button>
            </div>
        </div>
    </div>


</div>

    <x-ui.live-user-simulator />
@endsection

@push('scripts')
    @php
        $lpConfig = [
            'apiBase' => '/api/v1/dashboard',
            'merchantName' => $merchantName ?? 'Merchant',
            'demoEnabled' => (bool) config('luvupay.demo_simulator'),
            'project' => isset($project) ? $project->toArray() : [],
        ];
    @endphp
    <script type="application/json" id="lp-config">@json($lpConfig)</script>
    <script src="/vendor/ag-grid-32.3.3/ag-grid-community.min.noStyle.js" defer></script>
    <script src="/js/dashboard.js?v={{ @filemtime(public_path('js/dashboard.js')) }}" defer></script>
@endpush

@php
    $m = $e['model']; $r = $e['reasoning']; $s = $e['split']; $p = $e['paypal']; $a = $e['audit'];
    $fmt = fn ($minor) => number_format(((int) $minor) / 100, 2);
    $cur = $s['currency'] ?? 'USD';
    $tone = match (true) {
        in_array($e['status'], ['failed', 'rejected'], true) => 'bad',
        $e['status'] === 'proposed' => 'warn',
        $e['path'] === 'fallback' => 'bad',
        $p['simulated'] => 'warn',
        default => 'ok',
    };
    $total = max(1, (int) $s['unused_value_minor']);
    $pct = fn ($v) => round(((int) $v) / $total * 100, 1);
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>AI Negotiation Evidence · {{ substr($e['run_id'], 0, 8) }} · LuvuPay</title>
<style>
    :root { --bg:#f6f7fb; --card:#fff; --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --brand:#4f46e5; --ok:#059669; --okbg:#ecfdf5; --warn:#b45309; --warnbg:#fffbeb; --bad:#b91c1c; --badbg:#fef2f2; --mono:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; color-scheme:light dark; }
    @media (prefers-color-scheme: dark) { :root { --bg:#0b1020; --card:#131a2e; --ink:#e8ecf8; --muted:#94a3b8; --line:#26304d; --brand:#818cf8; --ok:#34d399; --okbg:#0d2a22; --warn:#fbbf24; --warnbg:#2b2210; --bad:#f87171; --badbg:#2e1417; } }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; padding:24px 16px 56px; }
    .wrap { max-width:960px; margin:0 auto; }
    header { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start; margin-bottom:16px; }
    h1 { font-size:22px; margin:0 0 2px; letter-spacing:-.01em; }
    .sub { color:var(--muted); font-size:13px; }
    .banner { border-radius:12px; padding:14px 18px; font-weight:700; font-size:16px; border:1px solid; margin-bottom:16px; }
    .banner.ok { background:var(--okbg); color:var(--ok); border-color:var(--ok); }
    .banner.warn { background:var(--warnbg); color:var(--warn); border-color:var(--warn); }
    .banner.bad { background:var(--badbg); color:var(--bad); border-color:var(--bad); }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @media (max-width:760px) { .grid { grid-template-columns:1fr; } }
    .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px 18px; }
    .card.full { grid-column:1 / -1; }
    .step { font-size:11px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; color:var(--brand); margin-bottom:8px; }
    dl { margin:0; display:grid; grid-template-columns:auto 1fr; gap:6px 14px; }
    dt { color:var(--muted); font-size:13px; }
    dd { margin:0; font-family:var(--mono); font-size:13px; word-break:break-all; text-align:right; }
    dd.big { font-size:15px; font-weight:700; }
    ul.steps { margin:0; padding-left:20px; } ul.steps li { margin:5px 0; }
    .bar { display:flex; height:30px; border-radius:8px; overflow:hidden; border:1px solid var(--line); margin:10px 0 6px; }
    .bar span { display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; min-width:0; overflow:hidden; white-space:nowrap; }
    .b1 { background:#6366f1; } .b2 { background:#10b981; } .b3 { background:#f59e0b; }
    table { width:100%; border-collapse:collapse; font-size:13px; margin-top:6px; }
    th, td { padding:6px 8px; text-align:right; border-bottom:1px solid var(--line); font-family:var(--mono); }
    th:first-child, td:first-child { text-align:left; font-family:inherit; color:var(--muted); }
    th { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); font-weight:700; }
    .pill { display:inline-block; font-size:11px; font-weight:800; padding:2px 9px; border-radius:99px; letter-spacing:.04em; }
    .pill.ok { background:var(--okbg); color:var(--ok); } .pill.warn { background:var(--warnbg); color:var(--warn); } .pill.bad { background:var(--badbg); color:var(--bad); }
    .trace { font-family:var(--mono); font-size:12px; background:var(--bg); border:1px solid var(--line); border-radius:8px; padding:10px 12px; white-space:pre-wrap; max-height:180px; overflow:auto; margin-top:8px; }
    .note { color:var(--muted); font-size:12px; margin-top:8px; }
    .flow { display:flex; gap:8px; flex-wrap:wrap; align-items:center; font-size:12px; font-weight:700; margin-bottom:16px; }
    .flow span.node { padding:5px 11px; border-radius:99px; border:1px solid var(--line); background:var(--card); }
    .flow span.node.on { border-color:var(--brand); color:var(--brand); }
    footer { margin-top:20px; color:var(--muted); font-size:12px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    a { color:var(--brand); }
</style>
@include('partials.ag-grid-head')
<style>.lg-table{margin-top:6px}.ag-theme-quartz{--ag-wrapper-border-radius:8px;--ag-font-size:13px;--ag-font-family:var(--mono)}</style>
</head>
<body>
<div class="wrap">
    <header>
        <div>
            <h1>AI Negotiation Evidence</h1>
            <div class="sub">LuvuPay Smart-Convert · run <span style="font-family:var(--mono)">{{ $e['run_id'] }}</span></div>
        </div>
        <div class="sub" style="text-align:right">Generated server-side from stored records<br>{{ $e['executed_at'] ?? $e['created_at'] }}</div>
    </header>

    <div class="banner {{ $tone }}">{{ $e['headline'] }}</div>

    @php
        $decided = in_array($e['status'], ['executing', 'executed', 'rejected', 'failed'], true);
    @endphp
    <div class="flow" aria-label="Flow">
        <span class="node on">1 · AI proposes</span> →
        <span class="node {{ $decided ? 'on' : '' }}">2 · {{ $e['mode'] === 'propose' ? 'Merchant decides' : 'Policy guardrails' }}</span> →
        <span class="node {{ $e['status'] === 'executed' ? 'on' : '' }}">3 · PayPal Payouts executes</span> →
        <span class="node {{ in_array($e['donation']['status'], ['sent', 'simulated'], true) ? 'on' : '' }}">4 · Donation</span> →
        <span class="node {{ $e['status'] === 'executed' ? 'on' : '' }}">5 · Audit trail</span>
    </div>

    <div class="grid">
        <section class="card">
            <div class="step">1 · Model</div>
            <dl>
                <dt>Model</dt><dd class="big">{{ $m['name'] ?? 'n/a' }}</dd>
                <dt>Provider</dt><dd>{{ $m['provider'] ?? 'n/a' }}</dd>
                <dt>Live model call</dt><dd><span class="pill {{ $m['live'] ? 'ok' : 'bad' }}">{{ $m['live'] ? 'YES' : 'NO' }}</span></dd>
                <dt>Tokens (prompt + completion)</dt><dd class="big">{{ $m['tokens_used'] !== null ? number_format($m['tokens_used']) : 'n/a' }}</dd>
                <dt>Latency</dt><dd class="big">{{ $m['latency_ms'] !== null ? number_format($m['latency_ms']).' ms' : 'n/a' }}</dd>
                <dt>ai_request_logs id</dt><dd>{{ $m['ai_request_log_id'] ?? 'n/a' }}</dd>
                @if($m['error'])<dt>Error</dt><dd style="color:var(--bad)">{{ $m['error'] }}</dd>@endif
            </dl>
        </section>

        <section class="card">
            <div class="step">2 · Reasoning</div>
            @if(!empty($r['rationale_steps']))
                <ul class="steps">@foreach($r['rationale_steps'] as $step)<li>{{ $step }}</li>@endforeach</ul>
                <div class="note">{{ $r['rationale_source'] }}.</div>
            @else
                <div class="note">No rationale recorded.</div>
            @endif
            @if($r['narrative'])<p style="margin:10px 0 0"><strong>Summary:</strong> {{ $r['narrative'] }}</p>@endif
            @if($r['risk_score'] !== null)<p style="margin:4px 0 0"><strong>Risk score:</strong> {{ $r['risk_score'] }}/100</p>@endif
            @if($r['provider_trace'])
                <div class="trace">{{ $r['provider_trace'] }}</div>
            @endif
            <div class="note">{{ $r['provider_trace_source'] }}.</div>
        </section>

        <section class="card full">
            <div class="step">3 · Split decided</div>
            <div class="bar" role="img" aria-label="Split bar">
                @if($s['store_credit_minor'] > 0)<span class="b1" style="width:{{ $pct($s['store_credit_minor']) }}%">Store credit {{ $pct($s['store_credit_minor']) }}%</span>@endif
                @if($s['charity_minor'] > 0)<span class="b2" style="width:{{ $pct($s['charity_minor']) }}%">Charity {{ $pct($s['charity_minor']) }}%</span>@endif
                @if($s['fee_minor'] > 0)<span class="b3" style="width:{{ $pct($s['fee_minor']) }}%">Fee {{ $pct($s['fee_minor']) }}%</span>@endif
            </div>
            <table data-ag-table>
                <tr><th></th><th>Store credit</th><th>Charity</th><th>Fee</th><th>Total</th></tr>
                @if($s['model_raw'])
                <tr><td>Model proposed (raw)</td><td>{{ $fmt($s['model_raw']['store_credit_minor']) }}</td><td>{{ $fmt($s['model_raw']['charity_minor']) }}</td><td>{{ $fmt($s['model_raw']['fee_minor']) }}</td><td>{{ $fmt(array_sum($s['model_raw'])) }}</td></tr>
                @endif
                <tr><td><strong>Final (executed)</strong></td><td><strong>{{ $fmt($s['store_credit_minor']) }}</strong></td><td><strong>{{ $fmt($s['charity_minor']) }}</strong></td><td><strong>{{ $fmt($s['fee_minor']) }}</strong></td><td><strong>{{ $fmt($s['unused_value_minor']) }} {{ $cur }}</strong></td></tr>
            </table>
            @if(!empty($s['guardrail_adjustments']))
                <div class="note" style="margin-top:10px"><strong>Policy / merchant adjustments applied to the raw proposal:</strong></div>
                <ul class="steps">@foreach($s['guardrail_adjustments'] as $g)<li>{{ $g }}</li>@endforeach</ul>
            @else
                <div class="note">No adjustments: the model's proposal was used as returned.</div>
            @endif
        </section>

        <section class="card">
            <div class="step">4 · PayPal Payouts</div>
            <dl>
                <dt>Status</dt><dd><span class="pill {{ $p['real'] ? 'ok' : ($p['simulated'] ? 'warn' : 'bad') }}">{{ $p['real'] ? 'REAL · '.strtoupper((string) $p['mode']) : ($p['simulated'] ? 'SIMULATED' : 'NOT EXECUTED') }}</span></dd>
                <dt>payout_batch_id</dt><dd class="big">{{ $p['payout_batch_id'] ?? '—' }}</dd>
                <dt>sender_batch_id</dt><dd>{{ $p['sender_batch_id'] ?? '—' }}</dd>
                <dt>Batch status</dt><dd>{{ $p['batch_status'] ?? '—' }}</dd>
                <dt>Receiver</dt><dd>{{ $p['receiver_masked'] ?? '—' }}</dd>
                <dt>Amount</dt><dd class="big">{{ $fmt($s['charity_minor']) }} {{ $cur }}</dd>
                <dt>API</dt><dd>{{ $p['endpoint'] }}</dd>
                @if($p['error'])<dt>Note</dt><dd style="color:var(--{{ $p['simulated'] ? 'warn' : 'bad' }})">{{ $p['error'] }}</dd>@endif
            </dl>
            @if($p['real'])<div class="note">Cross-check: PayPal Developer Dashboard → Apps &amp; Credentials → your app → API calls, filter <code>/v1/payments/payouts</code>.</div>@endif
        </section>

        @php
            $dn = $e['donation'];
            $dnLabel = ['sent' => 'SENT', 'simulated' => 'SIMULATED', 'awaiting_approval' => 'WAITING FOR APPROVAL', 'processing' => 'PROCESSING', 'none' => 'NO DONATION', 'not_sent' => 'NOT SENT'][$dn['status']] ?? strtoupper($dn['status']);
            $dnTone = in_array($dn['status'], ['sent'], true) ? 'ok' : (in_array($dn['status'], ['not_sent'], true) ? 'bad' : 'warn');
        @endphp
        <section class="card full" id="donation">
            <div class="step">5 · Donation @if($dn['example_only'])<span class="pill warn" style="margin-left:6px">EXAMPLE · SANDBOX</span>@endif</div>
            <dl>
                <dt>Status</dt><dd><span class="pill {{ $dnTone }}">{{ $dnLabel }}</span></dd>
                <dt>Recipient</dt><dd class="big">{{ $dn['recipient'] }}</dd>
                <dt>Amount</dt><dd class="big">{{ $fmt($dn['amount_minor']) }} {{ $dn['currency'] }} <span style="color:var(--muted);font-weight:400">({{ $dn['share_percent'] }}% of unused value)</span></dd>
                <dt>Receipt id</dt><dd>{{ $dn['receipt_id'] }}</dd>
                <dt>Moved through</dt><dd>{{ $dn['network'] ?? 'nothing moved' }}</dd>
                <dt>PayPal payout_batch_id</dt><dd>{{ $dn['payout_batch_id'] ?? '—' }}</dd>
            </dl>
            <div class="note">{{ $dn['recipient_note'] }}</div>
        </section>

        <section class="card">
            <div class="step">5 · Human oversight &amp; audit</div>
            <dl>
                <dt>Mode</dt><dd>{{ $e['mode'] === 'propose' ? 'AI proposes → merchant approves' : 'Auto (one-click demo)' }}</dd>
                <dt>Decided by</dt><dd>{{ $a['decided_by'] ?? '—' }}</dd>
                <dt>Decided at</dt><dd>{{ $a['decided_at'] ?? '—' }}</dd>
                <dt>Ledger transaction</dt><dd>{{ $a['transaction_id'] ?? '—' }}</dd>
                <dt>Input (hashed)</dt><dd>{{ $e['input']['unused_days'] ?? '?' }} days · {{ $fmt($e['input']['unused_value_minor'] ?? 0) }} {{ $cur }} · {{ $e['input']['email_hash'] ?? '' }}</dd>
            </dl>
            <div class="note">Merchants can reject a proposal or lower the donation before PayPal moves any money. The model never touches money directly.</div>
        </section>
    </div>

    <footer>
        <span>Machine-readable: <a href="{{ $jsonUrl }}">{{ $jsonUrl }}</a></span>
        <span>No emails, tokens or secrets are shown on this page.</span>
    </footer>
</div>
@include('partials.ag-grid-scripts')
</body>
</html>

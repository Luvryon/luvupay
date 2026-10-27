<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice {{ $d['number'] }} · {{ $d['from']['name'] }}</title>
    <style>
        :root{--accent:#4f46e5;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--soft:#eef2ff}
        *{box-sizing:border-box}
        body{margin:0;background:#f1f5f9;color:var(--ink);font-family:Inter,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .bar{max-width:820px;margin:24px auto 12px;padding:0 16px;display:flex;gap:10px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);background:#fff;color:var(--ink);border-radius:10px;padding:9px 16px;font:600 13px inherit;font-family:inherit;text-decoration:none;cursor:pointer}
        .btn.pri{background:var(--accent);border-color:var(--accent);color:#fff}
        .sheet{max-width:820px;margin:0 auto 40px;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 10px 40px rgba(15,23,42,.08);padding:48px;position:relative}
        .top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start}
        .brand{display:flex;gap:16px;align-items:flex-start}
        .logo{width:64px;height:64px;border-radius:12px;object-fit:contain;flex:none}
        .initials{width:64px;height:64px;border-radius:12px;background:var(--soft);color:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:22px;flex:none}
        .bn{margin:0;font-size:18px;font-weight:800;letter-spacing:-.01em}
        .sm{margin:2px 0 0;color:var(--muted);font-size:12.5px;white-space:pre-line}
        .title{text-align:right}
        .title h1{margin:0;font-size:34px;letter-spacing:.02em;color:var(--accent);font-weight:800}
        .title p{margin:4px 0 0;color:var(--muted);font-size:13px}
        .meta{display:flex;justify-content:space-between;gap:24px;margin-top:36px}
        .lab{margin:0 0 4px;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
        .to b{font-size:16px}
        dl{margin:0;display:grid;grid-template-columns:auto auto;gap:6px 28px}
        dt{color:var(--muted)}dd{margin:0;text-align:right;font-weight:600}
        .badge{display:inline-block;margin-top:10px;border-radius:6px;background:#d1fae5;color:#047857;font-weight:800;font-size:12px;letter-spacing:.08em;padding:4px 14px}
        .badge.due{background:#fef3c7;color:#b45309}
        table{width:100%;border-collapse:collapse;margin-top:32px}
        thead th{background:var(--accent);color:#fff;font-size:11px;letter-spacing:.07em;text-transform:uppercase;padding:11px 14px;text-align:right}
        thead th:first-child{text-align:left;border-radius:8px 0 0 8px}thead th:last-child{border-radius:0 8px 8px 0}
        td{padding:16px 14px;border-bottom:1px solid var(--line);text-align:right}
        td:first-child{text-align:left;font-weight:600}
        .tot{margin:20px 0 0 auto;width:min(320px,100%)}
        .tot div{display:flex;justify-content:space-between;padding:6px 0;color:var(--muted)}
        .tot .grand{background:var(--soft);border-radius:10px;padding:12px 14px;margin:6px 0;color:var(--ink);font-weight:800;font-size:16px}
        .tot .grand span:last-child{color:var(--accent);font-size:20px}
        .tot .bal{color:var(--ink);font-weight:700}
        .info{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:36px;padding-top:24px;border-top:1px solid var(--line)}
        .foot{margin-top:40px;padding-top:16px;border-top:1px solid var(--line);color:var(--muted);font-size:11.5px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
        @media(max-width:640px){.sheet{padding:24px}.top,.meta,.info{flex-direction:column;grid-template-columns:1fr}.title{text-align:left}}
        @media print{body{background:#fff}.bar{display:none}.sheet{box-shadow:none;border:0;margin:0;max-width:none;padding:0}}
    </style>
</head>
<body>
<div class="bar">
    <a class="btn pri" href="{{ $pdfUrl }}">⬇ Download PDF</a>
    <button class="btn" type="button" data-print>🖨 Print</button>
    @if(!empty($d['access_code']))
        <button class="btn" type="button" data-copy-access>🎫 Copy access code</button>
    @endif
    @if(!empty($backUrl))<a class="btn" href="{{ $backUrl }}">← Back to console</a>@endif
</div>
@if(!empty($d['access_code']))
<div class="bar" style="margin-top:0">
    <div style="flex:1;background:linear-gradient(135deg,#eef2ff,#f5f3ff);border:1px solid #c7d2fe;border-radius:14px;padding:16px 18px">
        <p style="margin:0;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#4f46e5">Payment successful</p>
        <p style="margin:6px 0 0;font-size:15px;font-weight:700;color:#0f172a">
            @if(!empty($d['access_code']))
                Your access is ready
                @if(!empty($d['access_days'])) · {{ (int) $d['access_days'] }} days @endif
                @if(!empty($d['product'])) of {{ $d['product'] }} @endif
            @else
                Subscription confirmed — invoice below
            @endif
        </p>
        @if(!empty($d['access_code']))
            <p style="margin:12px 0 0;font-size:12px;color:#64748b">Access / coupon code</p>
            <p id="access-code-value" style="margin:4px 0 0;font-family:ui-monospace,monospace;font-size:22px;font-weight:800;letter-spacing:.08em;color:#312e81">{{ $d['access_code'] }}</p>
            <p style="margin:8px 0 0;font-size:12px;color:#64748b">Save this code. It is also on your email receipt when delivery succeeds.</p>
        @endif
    </div>
</div>
@endif
<article class="sheet">
    <header class="top">
        <div class="brand">
            @if($d['from']['logo_uri'])
                <img class="logo" src="{{ $d['from']['logo_uri'] }}" alt="{{ $d['from']['name'] }} logo">
            @else
                <div class="initials" aria-hidden="true">{{ $d['from']['initials'] }}</div>
            @endif
            <div>
                <p class="bn">{{ $d['from']['name'] }}</p>
                @if($d['from']['address'])<p class="sm">{{ implode("\n", $d['from']['address']) }}</p>@endif
                @if($d['from']['tax_id'])<p class="sm">Tax ID: {{ $d['from']['tax_id'] }}</p>@endif
                @if($d['from']['email'])<p class="sm">{{ $d['from']['email'] }}</p>@endif
            </div>
        </div>
        <div class="title"><h1>INVOICE</h1><p>#{{ $d['number'] }}</p></div>
    </header>

    <section class="meta">
        <div class="to">
            <p class="lab">Billed to</p>
            <b>{{ $d['to']['name'] }}</b>
            @if($d['to']['email'])<p class="sm">{{ $d['to']['email'] }}</p>@endif
        </div>
        <div>
            <dl>
                <dt>Invoice date</dt><dd>{{ $d['issued'] }}</dd>
                <dt>Due date</dt><dd>{{ $d['due'] }}</dd>
                <dt>Payment</dt><dd>{{ $d['payment_method'] }}</dd>
            </dl>
            <div style="text-align:right"><span class="badge {{ $d['paid'] ? '' : 'due' }}">{{ $d['status'] }}</span></div>
        </div>
    </section>

    <table>
        <thead><tr><th>Description</th><th>Qty</th><th>Price</th><th>Amount</th></tr></thead>
        <tbody>
        @foreach($d['items'] as $it)
            <tr><td>{{ $it['description'] }}</td><td>{{ $it['qty'] }}</td><td>{{ $it['unit'] }}</td><td><b>{{ $it['amount'] }}</b></td></tr>
        @endforeach
        </tbody>
    </table>

    <div class="tot">
        <div><span>Subtotal</span><span>{{ $d['subtotal'] }}</span></div>
        @if($d['has_tax'])<div><span>Tax</span><span>{{ $d['tax'] }}</span></div>@endif
        <div class="grand"><span>Total</span><span>{{ $d['total'] }}</span></div>
        <div><span>Amount paid</span><span>{{ $d['amount_paid'] }}</span></div>
        <div class="bal"><span>Balance due</span><span>{{ $d['balance'] }}</span></div>
    </div>

    <section class="info">
        <div><p class="lab">Payment information</p><p class="sm" style="white-space:normal;color:var(--ink)">Paid with {{ $d['payment_method'] }}@if($d['payment_ref']) · Ref {{ $d['payment_ref'] }}@endif</p></div>
        <div><p class="lab">Notes</p><p class="sm" style="white-space:normal;color:var(--ink)">Thank you for your business. This invoice was paid in full.</p></div>
    </section>

    <footer class="foot"><span>Invoice generated by LuvuPay on behalf of {{ $d['from']['name'] }}</span><span>#{{ $d['number'] }}</span></footer>
</article>
<script src="/js/invoice-print.js" defer></script>
<script>
document.querySelector('[data-print]')?.addEventListener('click', function () { window.print(); });
document.querySelector('[data-copy-access]')?.addEventListener('click', async function () {
  var el = document.getElementById('access-code-value');
  var code = el ? el.textContent.trim() : '';
  try {
    await navigator.clipboard.writeText(code);
    this.textContent = '✓ Copied';
  } catch (e) {
    prompt('Copy access code', code);
  }
});
</script>
</body>
</html>

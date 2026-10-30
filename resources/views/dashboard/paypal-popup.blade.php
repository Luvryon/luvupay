<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @include('partials.favicon')
    <title>LuvuPay · PayPal checkout</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f9;
               font-family: Inter, "Segoe UI", system-ui, sans-serif; color: #0f172a; padding: 24px; }
        .card { width: 100%; max-width: 380px; background: #fff; border: 1px solid #e2e8f0; border-radius: 20px;
                padding: 32px 28px; text-align: center; box-shadow: 0 20px 50px -20px rgba(15, 23, 42, .25); }
        .logo { height: 28px; margin-bottom: 20px; }
        .icon { width: 56px; height: 56px; border-radius: 999px; margin: 0 auto 16px; display: grid; place-items: center;
                font-size: 28px; font-weight: 700; color: #fff; }
        .ok { background: #10b981; } .wait { background: #f59e0b; } .bad { background: #ef4444; } .off { background: #64748b; }
        h1 { margin: 0 0 6px; font-size: 18px; }
        p { margin: 0; font-size: 14px; line-height: 1.55; color: #475569; }
        .hint { margin-top: 18px; font-size: 12px; color: #94a3b8; }
        button { margin-top: 18px; border: 0; border-radius: 10px; background: #4f46e5; color: #fff; font-weight: 600;
                 padding: 10px 18px; font-size: 14px; cursor: pointer; }
    </style>
</head>
<body>
@php
    $map = [
        'captured'   => ['ok',   '✓', 'Payment confirmed'],
        'processing' => ['wait', '…', 'Approved, confirming payment'],
        'cancelled'  => ['off',  '×', 'Checkout cancelled'],
    ];
    [$cls, $glyph, $title] = $map[$state] ?? ['bad', '!', 'Payment not completed'];
@endphp
<main class="card" id="lp-popup-result"
      data-state="{{ $state }}" data-tx="{{ $tx }}" data-message="{{ $message }}" data-error="{{ $error }}">
    <img class="logo" src="/images/logo-mark.webp" alt="LuvuPay" width="28" height="28">
    <div class="icon {{ $cls }}" aria-hidden="true">{{ $glyph }}</div>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    @if($error)<p class="hint">{{ $error }}</p>@endif
    <p class="hint" id="lp-popup-hint">This window closes automatically</p>
    <button type="button" id="lp-popup-close" hidden>Close window</button>
</main>
<script src="/js/paypal-popup-return.js?v={{ @filemtime(public_path('js/paypal-popup-return.js')) }}"></script>
</body>
</html>

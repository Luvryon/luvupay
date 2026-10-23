@extends('layouts.console')
@section('title', 'Developers')
@section('heading', 'Developers')
@section('content')
@php
  $merchant = $merchant ?? Auth::guard('merchant_web')->user()?->merchant;
  $pub = $merchant->publishable_key ?? 'pk_test_••••••••';
  $prefix = $merchant->api_key_prefix ?? 'sk_live';
  $webhookUrl = url('/webhooks/paypal');
  $whsec = 'whsec_' . substr(hash('sha256', ($merchant->id ?? 'demo').config('app.key')), 0, 24);
@endphp
<div class="stack" x-data="{ reveal: false }">
    <div>
        <h1 class="page-title">Developers</h1>
        <p class="page-sub">API keys and webhook endpoints for integrating LuvuPay into your product.</p>
    </div>

    <section class="c-card">
        <div class="c-head"><div><h2 class="c-title">API keys</h2><p class="c-sub">Publishable key is safe in the browser. Secret key stays on your server.</p></div></div>
        <div class="c-pad stack-sm">
            <div class="field">
                <label class="label">Publishable key</label>
                <div style="display:flex;gap:8px">
                    <input class="input" readonly value="{{ $pub }}" id="dev-pub">
                    <button type="button" class="c-btn" x-on:click="navigator.clipboard.writeText(document.getElementById('dev-pub').value); window.LuvuToast && LuvuToast.success('Copied', 'Publishable key')">Copy</button>
                </div>
            </div>
            <div class="field">
                <label class="label">Secret key</label>
                <div style="display:flex;gap:8px">
                    <input class="input" readonly :type="reveal ? 'text' : 'password'" value="{{ $prefix }}_••••••••••••••••" id="dev-sec">
                    <button type="button" class="c-btn" x-on:click="reveal = !reveal" x-text="reveal ? 'Hide' : 'Reveal'"></button>
                    <button type="button" class="c-btn" x-on:click="window.LuvuToast && LuvuToast.info('Secret key', 'Rotate from Settings to get a new full secret. Prefix only is shown here.')">Copy</button>
                </div>
                <p class="help">Full secret is only shown once at issue / rotate time.</p>
            </div>
            <div class="field">
                <label class="label">Merchant ID</label>
                <input class="input" readonly value="{{ $merchant->id ?? '—' }}">
            </div>
        </div>
    </section>

    <section class="c-card">
        <div class="c-head"><div><h2 class="c-title">Webhooks</h2><p class="c-sub">PayPal and LuvuPay platform events.</p></div></div>
        <div class="c-pad stack-sm">
            <div class="field">
                <label class="label">Webhook endpoint URL</label>
                <div style="display:flex;gap:8px">
                    <input class="input" readonly value="{{ $webhookUrl }}" id="dev-wh">
                    <button type="button" class="c-btn" x-on:click="navigator.clipboard.writeText(document.getElementById('dev-wh').value); window.LuvuToast && LuvuToast.success('Copied', 'Webhook URL')">Copy</button>
                </div>
            </div>
            <div class="field">
                <label class="label">Webhook secret</label>
                <div style="display:flex;gap:8px">
                    <input class="input" readonly value="{{ $whsec }}" id="dev-whsec">
                    <button type="button" class="c-btn" x-on:click="navigator.clipboard.writeText(document.getElementById('dev-whsec').value); window.LuvuToast && LuvuToast.success('Copied', 'Webhook secret')">Copy</button>
                </div>
            </div>
            <p class="dim" style="margin:0;font-size:.85rem">Verify signatures with the webhook secret. Events appear in the dashboard Event log after Simulate / live traffic.</p>
        </div>
    </section>

    <section class="c-card">
        <div class="c-head"><div><h2 class="c-title">Quick start</h2></div></div>
        <div class="c-pad">
            <pre style="margin:0;padding:16px;background:#0f172a;color:#e2e8f0;border-radius:12px;font-size:12px;overflow:auto">curl -X POST {{ url('/api/v1/subscriptions') }} \
  -H "Authorization: Bearer sk_***" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"email":"user@example.com","plan":"premium_monthly"}'</pre>
        </div>
    </section>
</div>
@endsection

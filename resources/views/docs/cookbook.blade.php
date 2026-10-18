@extends('layouts.docs')
@section('title', 'Integration cookbook · LuvuPay')
@section('guides')
@endsection
@section('content')
<h1>Integration cookbook</h1>
<p>LuvuPay is an <strong>HTTP API</strong>. Your app can be Next.js, React, Laravel, Express, mobile, or anything that can call REST and receive webhooks.</p>

<h2>1. Create a merchant</h2>
<p>Register at <a href="/register">/register</a> with a <strong>work email</strong>, verify the 6-digit code, then copy the secret API key shown once on the dashboard.</p>

<h2>2. Call the API (any stack)</h2>
<pre>curl -X POST https://your-luvupay-host/api/v1/subscriptions \\
  -H "Authorization: Bearer lp_live_..." \\
  -H "Content-Type: application/json" \\
  -H "Idempotency-Key: $(uuidgen)" \\
  -d '{
    "merchant_user_id": "user_123",
    "email": "customer@example.com",
    "plan_id": "plan_basic",
    "amount_minor": 2000,
    "currency": "USD",
    "billing_interval": "month",
    "return_url": "https://yourapp.com/billing/return",
    "cancel_url": "https://yourapp.com/billing/cancel"
  }'</pre>

<h3>Next.js / React (fetch)</h3>
<pre>const res = await fetch(`${process.env.LUVUPAY_BASE}/api/v1/subscriptions`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.LUVUPAY_SECRET_KEY}`,
    'Content-Type': 'application/json',
    'Idempotency-Key': crypto.randomUUID(),
  },
  body: JSON.stringify({ /* same fields */ }),
});</pre>

<h3>Laravel (HTTP client)</h3>
<pre>Http::withToken(config('services.luvupay.secret'))
    ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
    ->post(config('services.luvupay.base').'/api/v1/subscriptions', $payload);</pre>

<h2>3. Checkout badge (optional)</h2>
<p>Add the publishable snippet on your pricing page so buyers see “Protected by LuvuPay”.</p>

<h2>4. Webhooks (Entitlement Bridge)</h2>
<p>Expose an HTTPS endpoint. Verify HMAC SHA-256 + timestamp + nonce. On <code>entitlement.transfer</code>, revoke seller access and grant buyer access in <em>your</em> database.</p>
<pre>// Node example: verify signature header before trusting body
// See /docs/webhooks for full PHP + Node samples</pre>

<h2>5. Second Chance & resale</h2>
<ul>
<li>Second Chance: user gets a 48h window after a forgotten charge — split 30/20/50.</li>
<li>Resale: P2P sale of remaining days; merchant’s original cycle charge stays intact.</li>
</ul>
<p>Details: <a href="/docs/resale-money">Resale money flow</a> · <a href="/docs/api">API reference</a> · <a href="/docs/webhooks">Webhooks</a></p>

<h2>Local / sandbox payments</h2>
<pre>php artisan luvupay:sandbox-check</pre>
<p>Drop-in Laravel product integration: <code>samples/laravel-merchant/</code>. Full PayPal steps: <a href="/docs/sandbox">PayPal sandbox</a>.</p>
<p>Set <code>LUVUPAY_PAYMENT_DRIVER=mock</code> while building UI. Switch to PayPal sandbox credentials when you are ready for real money-path tests.</p>
@endsection

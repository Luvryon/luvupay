@extends('layouts.docs')
@section('title', 'Resale money flow · LuvuPay')
@section('meta_description', 'How LuvuPay resale marketplace money moves: buyer payment, seller payout, platform fee, entitlement transfer, and what never reverses on the original merchant charge.')
@section('guides')
@endsection
@section('content')
<p class="text-sm font-semibold tracking-wide uppercase text-brand-600 mb-2">Product</p>
<h1>Resale marketplace — money flow</h1>
<p class="lead text-muted">Peer-to-peer sale of <strong>remaining access days</strong> in the <strong>current</strong> billing cycle. This is not a refund of the merchant’s original subscription charge.</p>

<div class="mt-10 space-y-6 not-prose">
<section class="rounded-2xl border border-line bg-wash p-6">
  <h2 class="font-bold text-lg text-ink">What stays with the merchant</h2>
  <p class="text-sm text-muted mt-2 leading-relaxed">User A was already charged the full cycle (e.g. $20). <strong>That capture is not reversed.</strong> LuvuPay does not refund User A from the merchant’s original revenue for a resale. The merchant keeps the original cycle charge.</p>
</section>

<section class="rounded-2xl border border-line bg-wash p-6">
  <h2 class="font-bold text-lg text-ink">What User B pays</h2>
  <p class="text-sm text-muted mt-2 leading-relaxed">The buyer pays the <strong>resale price</strong> (e.g. $10) via a new PayPal Order/Capture to the platform. This is a separate payment from the original $20. Minimum price and reservation windows are configurable platform defaults.</p>
</section>

<section class="rounded-2xl border border-line bg-wash p-6">
  <h2 class="font-bold text-lg text-ink">Split of the resale price</h2>
  <ul class="mt-3 text-sm list-disc pl-5 space-y-2 text-ink/80">
    <li><strong>90%</strong> → PayPal Payouts → User A (seller) — subject to payout allow-list</li>
    <li><strong>10%</strong> → LuvuPay platform fee (<code class="text-xs font-mono">fee_type=resale_marketplace</code>)</li>
  </ul>
  <p class="mt-3 text-sm text-muted">Basis points are configured under <code class="text-xs">config/luvupay.php</code> <code class="text-xs">resale.seller_bps</code> (default 9000).</p>
</section>

<section class="rounded-2xl border border-line bg-wash p-6">
  <h2 class="font-bold text-lg text-ink">Access transfer</h2>
  <p class="text-sm text-muted mt-2 leading-relaxed">Entitlement Bridge webhook: revoke A, grant B until <code class="text-xs font-mono">access_expires_at</code> (end of the current cycle). On the next cycle the merchant bills B at the standard plan price. Your product must apply the entitlement change on receipt of the signed webhook.</p>
</section>

<section class="rounded-2xl border border-warn/30 bg-warn/10 p-6">
  <h2 class="font-bold text-lg text-amber-100">Not Second Chance</h2>
  <p class="text-sm text-amber-100/90 mt-2 leading-relaxed">Resale does <strong>not</strong> use the 30/20/50 (or configured) Second Chance split. There is no charity line and no clawback of merchant revenue for this flow. Second Chance is a separate product path for forgotten renewals within the recovery window.</p>
</section>

<section class="rounded-2xl border border-line bg-wash p-6">
  <h2 class="font-bold text-lg text-ink">API sequence</h2>
  <ol class="mt-3 text-sm list-decimal pl-5 space-y-1 text-ink/80">
    <li><code class="text-xs">POST /api/v1/resale-listings</code> — create listing</li>
    <li><code class="text-xs">POST .../checkout</code> — reserve + create buyer order</li>
    <li>Buyer approves payment (PayPal)</li>
    <li><code class="text-xs">POST .../complete</code> — capture, payout seller, emit entitlement webhook</li>
  </ol>
  <p class="mt-3 text-sm text-muted">All write steps require <code class="text-xs">Idempotency-Key</code> and <code class="text-xs">resale:write</code> scope. See <a href="/docs/api" class="text-brand-600 underline">API reference</a>.</p>
</section>
</div>

<p class="mt-10 not-prose flex flex-wrap gap-4 text-sm">
  <a href="/docs/api" class="text-brand-600 font-medium">← API reference</a>
  <a href="/docs/webhooks" class="text-brand-600 font-medium">Webhooks →</a>
  <a href="/docs/security" class="text-brand-600 font-medium">Security →</a>
</p>
@endsection

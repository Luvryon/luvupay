@extends('layouts.console')
@section('title', 'Privacy Policy')
@section('heading', 'Privacy Policy')
@section('content')
<article class="legal-doc c-card" style="max-width:720px;margin:0 auto;padding:40px 36px;background:#fff;color:#0f172a;border:1px solid #e2e8f0;border-radius:16px">
  <h1 style="margin:0 0 8px;font-size:1.75rem;font-weight:700;letter-spacing:-0.02em">Privacy Policy</h1>
  <p style="margin:0 0 28px;font-size:0.875rem;color:#64748b">Last updated October 2026</p>
  <div class="legal-body" style="font-size:0.95rem;line-height:1.7;color:#334155">
    
<p>LuvuPay (“we”, “us”) processes payment and subscription data so merchants can retain customers and settle payouts.</p>
<h2>Data we process</h2>
<ul>
<li>Account email and name for merchants and end users</li>
<li>Subscription, transaction, and payout records</li>
<li>Technical logs required for security and fraud prevention</li>
</ul>
<h2>How we use data</h2>
<p>To operate the platform, run retention workflows, issue invoices, and meet legal obligations. We do not sell personal data.</p>
<h2>Processors</h2>
<p>Payment processing is handled by PayPal under their terms. Hosting and email providers process data under written agreements.</p>
<h2>Retention</h2>
<p>We keep records for as long as your account is active and as required for compliance, then delete or anonymize them.</p>
<h2>Contact</h2>
<p>Questions: privacy@luvupay.example</p>

  </div>
</article>
<style>
.legal-body h2{margin:28px 0 10px;font-size:1.05rem;font-weight:650;color:#0f172a}
.legal-body ul{margin:0 0 16px;padding-left:1.2rem}
.legal-body li{margin:6px 0}
.legal-body p{margin:0 0 14px}
</style>
@endsection

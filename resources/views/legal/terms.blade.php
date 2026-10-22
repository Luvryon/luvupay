@extends('layouts.console')
@section('title', 'Terms of Service')
@section('heading', 'Terms of Service')
@section('content')
<article class="legal-doc c-card" style="max-width:720px;margin:0 auto;padding:40px 36px;background:#fff;color:#0f172a;border:1px solid #e2e8f0;border-radius:16px">
  <h1 style="margin:0 0 8px;font-size:1.75rem;font-weight:700;letter-spacing:-0.02em">Terms of Service</h1>
  <p style="margin:0 0 28px;font-size:0.875rem;color:#64748b">Last updated October 2026</p>
  <div class="legal-body" style="font-size:0.95rem;line-height:1.7;color:#334155">
    
<p>By using LuvuPay you agree to these terms.</p>
<h2>Service</h2>
<p>LuvuPay provides subscription retention, marketplace resale tooling, and payout orchestration. Sandbox features are for testing only.</p>
<h2>Merchants</h2>
<p>You are responsible for your products, pricing, customer communications, and compliance with PayPal and local law.</p>
<h2>Acceptable use</h2>
<p>No illegal content, abuse of the API, or attempts to circumvent security or rate limits.</p>
<h2>Liability</h2>
<p>The service is provided as available. We are not liable for indirect losses or third-party payment network failures.</p>
<h2>Changes</h2>
<p>We may update these terms; continued use after notice means acceptance.</p>

  </div>
</article>
<style>
.legal-body h2{margin:28px 0 10px;font-size:1.05rem;font-weight:650;color:#0f172a}
.legal-body ul{margin:0 0 16px;padding-left:1.2rem}
.legal-body li{margin:6px 0}
.legal-body p{margin:0 0 14px}
</style>
@endsection

@extends('layouts.public')
@section('title', 'Privacy Policy · LuvuPay')
@section('content')
<article style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:40px 36px">
  <h1 style="margin:0 0 6px;font-size:1.75rem;font-weight:700;letter-spacing:-0.02em">Privacy Policy</h1>
  <p style="margin:0 0 28px;font-size:.875rem;color:#64748b">Last updated October 2026</p>
  <div style="font-size:.95rem;line-height:1.7;color:#334155">
  
<p>LuvuPay processes account, subscription, and payout data so merchants can retain customers and settle funds.</p>
<h2>What we collect</h2>
<ul>
<li>Merchant and end-user email and name</li>
<li>Subscription, invoice, and ledger records</li>
<li>Security logs and device metadata needed to prevent abuse</li>
</ul>
<h2>Why we use it</h2>
<p>To run retention flows, issue invoices, process PayPal payouts, and meet legal duties. We do not sell personal data.</p>
<h2>Sharing</h2>
<p>PayPal processes payments under their terms. Infrastructure and email providers act as processors under contract.</p>
<h2>Retention</h2>
<p>Data is kept while the account is active and as required for compliance, then deleted or anonymized.</p>
<h2>Contact</h2>
<p>privacy@luvupay.example</p>

  </div>
</article>
<style>
article h2{margin:28px 0 10px;font-size:1.05rem;font-weight:650;color:#0f172a}
article ul{margin:0 0 16px;padding-left:1.2rem}
article li{margin:6px 0}
article p{margin:0 0 14px}
</style>
@endsection

@extends('layouts.docs')
@section('title', 'Legal · LuvuPay')
@section('guides')
@endsection
@section('content')
<h1 class="text-3xl font-extrabold">Legal & licensing positioning</h1>
<div class="mt-8 space-y-6 text-sm text-ink/80">
<section class="rounded-2xl border bg-wash p-6">
<h2 class="font-bold text-lg">PayPal as custodian</h2>
<p class="mt-2">LuvuPay does <strong>not</strong> hold customer funds. Money moves in the merchant’s PayPal account via API instructions (refunds, payouts, captures). LuvuPay is positioned as a <strong>payment facilitation technology</strong>, not a money transmitter that custodians balances</p>
</section>
<section class="rounded-2xl border bg-wash p-6">
<h2 class="font-bold text-lg">Tax</h2>
<ul class="mt-2 list-disc pl-5 space-y-1">
<li>Charity line items are treated as merchant-directed donations (not LuvuPay’s own gifts)</li>
<li>Resale sellers are responsible for their own income tax reporting</li>
<li>Merchants remain responsible for local compliance; LuvuPay provides operational records/export</li>
</ul>
</section>
<section class="rounded-2xl border bg-wash p-6">
<h2 class="font-bold text-lg">Data portability</h2>
<p class="mt-2"><code class="font-mono text-xs">GET /api/v1/export</code> returns JSON of mapping, transactions, audit, trust scores. Deactivate retains data 90 days then purge</p>
</section>
</div>
@endsection

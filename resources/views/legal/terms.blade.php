@extends('layouts.site')
@section('title', 'Terms of Service · LuvuPay')
@section('meta_description', 'LuvuPay terms of service for merchant customers: service description, responsibilities, acceptable use, fees, and limitation of liability.')
@section('guides')
{{-- sidebar from layout --}}
@endsection
@section('content')
<p class="text-sm font-semibold tracking-wide uppercase text-brand-600 mb-2">Legal</p>
<h1>Terms of Service</h1>
<p class="text-sm text-muted">Last updated: October 2026</p>
<p class="lead text-muted">By creating a merchant account or calling the LuvuPay API you agree to these terms as a business customer of a payment-facilitation technology service.</p>

<h2>1. Service</h2>
<p>LuvuPay provides APIs and consoles for Second Chance flows, resale marketplace coordination, signed webhooks (Entitlement Bridge), AI-assisted insights (where enabled), and related tooling. Funds move through the merchant’s payment provider (e.g. PayPal). LuvuPay does not custody end-customer balances as a bank or e-money institution beyond operational settlement in documented flows.</p>

<h2>2. Accounts &amp; access</h2>
<ul>
  <li>You must provide accurate registration information and keep team access current.</li>
  <li>You are responsible for safeguarding API keys, webhook secrets, and console credentials.</li>
  <li>MFA may be required. You must not share individual login credentials.</li>
  <li>We may suspend access that presents security, fraud, or legal risk.</li>
</ul>

<h2>3. Merchant responsibilities</h2>
<ul>
  <li>Comply with applicable law in every jurisdiction where you offer subscriptions.</li>
  <li>Provide accurate subscription and user mapping data; you remain the controller of your end-user relationships.</li>
  <li>Implement webhook verification and entitlement updates correctly.</li>
  <li>Honour consumer rights, refunds, and disclosures required by your local law beyond LuvuPay product flows.</li>
  <li>Do not use Second Chance or resale to circumvent card-network, provider, or legal obligations.</li>
</ul>

<h2>4. Acceptable use</h2>
<p>Prohibited: fraud; abuse of Second Chance or resale loops; unlawful content or products; attempts to bypass rate limits, scopes, allow-lists, or audit controls; reverse engineering except as allowed by law; or any activity that harms end users or the platform.</p>

<h2>5. Fees</h2>
<p>Platform fees (including resale marketplace fee) are disclosed in product documentation and/or your commercial order form. Provider fees (PayPal etc.) are charged by the provider under their terms. Sandbox environments do not move real money.</p>

<h2>6. Data</h2>
<p>Our handling of personal data is described in the <a href="/privacy">Privacy Policy</a>. You represent that you have lawful grounds to transmit any end-user data you send to the API.</p>

<h2>7. Intellectual property</h2>
<p>LuvuPay retains IP in the platform, APIs, and documentation. You retain IP in your products and customer relationships. Feedback may be used to improve the service without obligation.</p>

<h2>8. Disclaimers &amp; liability</h2>
<p>The service is provided on an “as available” basis for the sandbox and subject to your production agreement. To the maximum extent permitted by law we exclude indirect, incidental, and consequential damages. Aggregate liability is limited as set out in your order form or, if none, to fees paid for the service in the three months preceding the claim.</p>

<h2>9. Termination</h2>
<p>You may deactivate via Console subject to outstanding obligations. We may suspend or terminate for breach, legal requirement, or prolonged inactivity. Export tools are provided to assist orderly offboarding.</p>

<h2>10. Changes</h2>
<p>We may update these terms with notice via the console or email for material changes. Continued use after the effective date constitutes acceptance.</p>

<h2>11. Contact</h2>
<p><a href="/support">Support</a> · Legal notices should include your merchant ID and registered company details.</p>
@endsection

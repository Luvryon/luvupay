@extends('layouts.site')
@section('title', 'Privacy Policy · LuvuPay')
@section('meta_description', 'LuvuPay privacy policy: data we process, retention, merchant responsibilities, and contact for privacy questions.')
@section('guides')
{{-- sidebar from layout --}}
@endsection
@section('content')
<p class="text-sm font-semibold tracking-wide uppercase text-brand-600 mb-2">Legal</p>
<h1>Privacy Policy</h1>
<p class="text-sm text-muted">Last updated: October 2026</p>
<p class="lead text-muted">LuvuPay provides B2B subscription infrastructure. This policy describes how we handle merchant account data and limited end-user data supplied by merchants in the course of API usage.</p>

<h2>Who we are</h2>
<p>LuvuPay operates as a technology platform for ethical subscription tooling (Second Chance, resale coordination, webhooks, and related APIs). Payment capture and payouts are performed via the configured payment provider (e.g. PayPal). We do not act as a bank or custodial wallet for merchant customer balances beyond the operational flows described in product documentation.</p>

<h2>Data we process</h2>
<ul>
  <li><strong>Merchant account data</strong> — name, work email, company details, team invitations, role assignments, MFA enrollment metadata</li>
  <li><strong>API credentials &amp; usage</strong> — hashed or tokenized keys, scopes, rate-limit counters, request metadata, idempotency records</li>
  <li><strong>Operational payloads</strong> — subscription mapping IDs, emails, plan identifiers, amounts, and status fields that merchants send to the API</li>
  <li><strong>Webhook configuration</strong> — endpoint URLs, secrets (stored securely), delivery attempts and outcomes</li>
  <li><strong>Audit logs</strong> — hash-chained records of security-relevant and financial actions</li>
  <li><strong>Payment references</strong> — provider order/capture/payout IDs; we do not store full card data</li>
</ul>

<h2>What we do not do</h2>
<ul>
  <li>We do not hold merchant user databases or master product license keys</li>
  <li>We do not sell personal data</li>
  <li>We do not use merchant customer emails for marketing outside the product flows the merchant initiates</li>
</ul>

<h2>Legal bases &amp; purposes</h2>
<p>We process data to provide the contracted service, secure accounts, prevent fraud and abuse, comply with law, and improve reliability. Merchants are responsible for ensuring they have appropriate rights and notices for any end-user data they transmit to LuvuPay.</p>

<h2>Retention</h2>
<p>After merchant deactivation, operational data may be retained up to 90 days for abuse investigation and legal obligations, then purged or anonymized, subject to mandatory retention requirements (e.g. financial records). Audit chains may be retained longer in reduced form for integrity verification.</p>

<h2>Security</h2>
<p>We apply technical and organisational measures including encryption in transit, access control, least-privilege API scopes, payout allow-lists, webhook URL cooling-off, and optional MFA. See <a href="/docs/security">Security</a>.</p>

<h2>International transfers</h2>
<p>If infrastructure or subprocessors operate in other regions, we use appropriate safeguards consistent with applicable law. Details of subprocessors are available on request for enterprise merchants.</p>

<h2>Your rights</h2>
<p>Merchant contacts may request access, correction, or deletion of account data subject to legal holds and technical constraints of audit/financial records. End users should contact the merchant that collected their data first.</p>

<h2>Contact</h2>
<p>Privacy questions: <a href="/support">Support</a>. For security incidents, mark the subject <strong>[SECURITY]</strong> and include merchant ID and timestamps.</p>
@endsection

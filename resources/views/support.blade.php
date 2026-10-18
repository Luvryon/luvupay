@extends('layouts.site')
@section('title', 'Support · LuvuPay')
@section('meta_description', 'LuvuPay merchant support: contact channels, incident reporting, documentation, and status.')
@section('content')
@php $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'domainku.com'; @endphp
<div class="mx-auto max-w-5xl px-5 py-14 sm:px-8">
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-600">Help</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink">Support</h1>
    <p class="mt-3 max-w-2xl text-muted leading-relaxed">
        We support merchant integrations, production incidents, and platform questions.
        End-user subscription issues should go through the merchant’s own support first.
    </p>

    <div class="mt-10 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-line bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-ink">Email</h2>
            <p class="mt-1 text-sm text-muted">Primary channel for integration and production issues.</p>
            <code class="mt-3 block rounded-lg border border-line bg-wash px-3 py-2 font-mono text-sm">support@{{ $host }}</code>
        </div>
        <div class="rounded-2xl border border-line bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-ink">Documentation</h2>
            <p class="mt-1 text-sm text-muted">API, webhooks, sandbox, security, and money flows.</p>
            <a href="/docs" class="lp-btn lp-btn-outline mt-4 text-sm">Open docs</a>
        </div>
        <div class="rounded-2xl border border-line bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-ink">Platform status</h2>
            <p class="mt-1 text-sm text-muted">Public liveness and high-level component status.</p>
            <a href="/status" class="lp-btn lp-btn-outline mt-4 text-sm">Status page</a>
        </div>
        <div class="rounded-2xl border border-line bg-white p-6 shadow-sm">
            <h2 class="font-semibold text-ink">Security &amp; billing</h2>
            <p class="mt-1 text-sm text-muted">Include merchant ID, timestamps, and <code class="text-xs">X-Request-Id</code> values.</p>
            <p class="mt-3 text-sm text-muted">Start the subject with <strong class="text-ink">[SECURITY]</strong> or <strong class="text-ink">[BILLING]</strong>.</p>
        </div>
    </div>

    <div class="prose-site mt-12 max-w-none">
        <h2>When you contact us</h2>
        <ul>
            <li><strong>Merchant ID</strong> (from Console → Settings)</li>
            <li><strong>Approximate timestamps</strong> (UTC preferred)</li>
            <li><strong>Request IDs</strong> from the <code>X-Request-Id</code> response header</li>
            <li>Environment (mock / sandbox / live) and a short reproduction path</li>
        </ul>
        <p>Never send secret API keys, passwords, or full card or account numbers. We will never ask for them.</p>
    </div>
</div>
@endsection

@extends('layouts.docs')
@section('title', 'Documentation · LuvuPay')
@section('meta_description', 'Developer documentation for LuvuPay — Second Chance, resale, webhooks, security, and sandbox.')

{{-- Full-width index (no guides sidebar) --}}
@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-600">Documentation</p>
    <h1 class="mt-2 text-4xl font-semibold tracking-tight text-ink">Build ethical subscriptions</h1>
    <p class="mt-4 max-w-2xl text-lg leading-relaxed text-muted">
        API-first infrastructure for Second Chance refunds, peer-to-peer resale of remaining access, signed entitlement webhooks, and audit-ready money movement — without holding your user database.
    </p>

    <div class="mt-10 grid gap-4 sm:grid-cols-2">
        @foreach([
            ['/docs/api', 'API reference', 'REST endpoints, auth, scopes, Idempotency-Key, rate limits, and response shapes.', 'Reference'],
            ['/docs/webhooks', 'Webhooks', 'HMAC verification, event types, Entitlement Bridge, retries, and URL cooling-off.', 'Integration'],
            ['/docs/sandbox', 'Sandbox & environments', 'Mock vs PayPal sandbox vs live, env vars, and local product wiring.', 'Setup'],
            ['/docs/cookbook', 'Integration cookbook', 'Next.js, React, Laravel, and curl recipes for the same API surface.', 'Guides'],
            ['/docs/resale-money', 'Resale money flow', 'Buyer payments, seller payouts, platform fees — and what never reverses.', 'Product'],
            ['/docs/security', 'Security model', 'Payout allow-lists, webhook locking, least-privilege scopes, MFA.', 'Security'],
            ['/docs/ai', 'AI layer', 'Churn prediction, dynamic resale pricing, subscription health via Nebius.', 'Optional'],
            ['/docs/errors', 'Errors & status codes', 'Stable error codes, 422 validation shape, idempotency conflicts.', 'Reference'],
            ['/docs/postman', 'Postman collection', 'Importable collection and environment variables.', 'Tools'],
            ['/support', 'Support', 'Contact channels and incident reporting for merchant integrations.', 'Help'],
        ] as [$href, $title, $desc, $tag])
            <a href="{{ $href }}" class="group block rounded-2xl border border-line bg-white p-6 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted">{{ $tag }}</span>
                    <span class="text-muted transition group-hover:text-brand-600">→</span>
                </div>
                <div class="mt-2 text-lg font-semibold text-ink">{{ $title }}</div>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $desc }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-14 rounded-2xl border border-line bg-wash p-8">
        <h2 class="text-xl font-semibold text-ink">Quick start</h2>
        <ol class="mt-4 list-decimal space-y-3 pl-5 text-sm text-ink/80">
            <li>Register a merchant account and complete email verification.</li>
            <li>Create an API key in <strong>Console → Settings</strong> (secret key for server-side calls).</li>
            <li>Point webhooks at your app and verify HMAC signatures (see <a href="/docs/webhooks" class="text-brand-700 underline underline-offset-2">Webhooks</a>).</li>
            <li>Start with <code class="rounded border border-line bg-white px-1.5 py-0.5 font-mono text-xs">LUVUPAY_PAYMENT_DRIVER=mock</code>, then move to PayPal sandbox.</li>
            <li>Exercise Second Chance and resale flows before requesting live credentials.</li>
        </ol>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="/docs/api" class="lp-btn lp-btn-primary text-sm">API reference</a>
            <a href="/docs/sandbox" class="lp-btn lp-btn-outline text-sm">Sandbox setup</a>
            <a href="/register" class="lp-btn lp-btn-outline text-sm">Create account</a>
        </div>
    </div>

    <div class="mt-10 grid gap-4 text-sm sm:grid-cols-3">
        <div class="rounded-xl border border-line bg-white p-5">
            <div class="font-semibold text-ink">Base URL</div>
            <code class="mt-2 block break-all font-mono text-xs text-muted">{{ rtrim(config('app.url'), '/') }}/api/v1</code>
        </div>
        <div class="rounded-xl border border-line bg-white p-5">
            <div class="font-semibold text-ink">Auth</div>
            <p class="mt-2 text-muted">Bearer secret key · scopes enforced</p>
        </div>
        <div class="rounded-xl border border-line bg-white p-5">
            <div class="font-semibold text-ink">Idempotency</div>
            <p class="mt-2 text-muted">Required on all money-moving writes</p>
        </div>
    </div>
</div>
@endsection

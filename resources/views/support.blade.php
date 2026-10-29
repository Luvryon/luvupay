@extends('layouts.site')
@section('title', 'Support · LuvuPay')
@section('meta_description', 'LuvuPay merchant support: contact channels, incident reporting, documentation, and status')
@section('content')
@php
    $supportEmail = 'contact@luvryon.com';
@endphp

<section class="border-b border-line bg-wash">
    <div class="mx-auto max-w-6xl px-5 py-16 sm:px-8 sm:py-20">
        <p class="lp-eyebrow">Help center</p>
        <h1 class="mt-3 max-w-2xl text-4xl font-bold tracking-tight text-ink sm:text-5xl">
            How can we help?
        </h1>
        <p class="mt-4 max-w-xl text-lg leading-relaxed text-muted">
            Support for merchant integrations, production incidents, and platform questions.
            End-user subscription issues should go through the merchant’s own support first.
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="mailto:{{ $supportEmail }}" class="lp-btn lp-btn-dark lp-btn-lg">Email support</a>
            <a href="/docs" class="lp-btn lp-btn-outline lp-btn-lg">Browse docs</a>
        </div>
    </div>
</section>

<section class="py-16 sm:py-20">
    <div class="mx-auto max-w-6xl px-5 sm:px-8">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <article class="group rounded-2xl border border-line bg-white p-6 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <div class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-ink">Email</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">Primary channel for integration and production issues. We aim to reply within one business day.</p>
                <a href="mailto:{{ $supportEmail }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700">
                    {{ $supportEmail }}
                    <span aria-hidden="true">→</span>
                </a>
            </article>

            <article class="group rounded-2xl border border-line bg-white p-6 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <div class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-ink">Documentation</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">API reference, webhooks, sandbox setup, security model, and money flows.</p>
                <a href="/docs" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700">
                    Open docs <span aria-hidden="true">→</span>
                </a>
            </article>

            <article class="group rounded-2xl border border-line bg-white p-6 shadow-sm transition hover:border-brand-200 hover:shadow-md">
                <div class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-600">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-ink">Platform status</h2>
                <p class="mt-1.5 text-sm leading-relaxed text-muted">Public liveness checks and high-level component health.</p>
                <a href="/status" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700">
                    View status <span aria-hidden="true">→</span>
                </a>
            </article>
        </div>
    </div>
</section>

<section class="border-t border-line bg-wash py-16 sm:py-20">
    <div class="mx-auto max-w-6xl px-5 sm:px-8">
        <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-ink">When you contact us</h2>
                <p class="mt-3 text-muted leading-relaxed">Include these so we can investigate faster. Never send secret API keys, passwords, or full card numbers.</p>
                <ul class="mt-6 space-y-3">
                    @foreach([
                        ['Merchant ID', 'From Console → Settings'],
                        ['Timestamps', 'UTC preferred, approximate is fine'],
                        ['Request IDs', 'From the X-Request-Id response header'],
                        ['Environment', 'mock / sandbox / live, plus a short reproduction path'],
                    ] as [$title, $desc])
                        <li class="flex gap-3">
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-500"></span>
                            <div>
                                <div class="text-sm font-semibold text-ink">{{ $title }}</div>
                                <div class="text-sm text-muted">{{ $desc }}</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-line bg-white p-6 sm:p-8 shadow-sm">
                <h3 class="text-lg font-semibold text-ink">Security &amp; billing</h3>
                <p class="mt-2 text-sm leading-relaxed text-muted">
                    For security reports or billing disputes, start the email subject with
                    <strong class="text-ink">[SECURITY]</strong> or <strong class="text-ink">[BILLING]</strong>
                    so the right team sees it first.
                </p>
                <div class="mt-6 rounded-xl border border-line bg-wash p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-muted">Support email</div>
                    <a href="mailto:{{ $supportEmail }}" class="mt-1 block font-mono text-sm font-semibold text-brand-600 break-all hover:underline">{{ $supportEmail }}</a>
                </div>
                <a href="mailto:{{ $supportEmail }}?subject=%5BSUPPORT%5D%20" class="lp-btn lp-btn-dark mt-6 w-full justify-center sm:w-auto">
                    Write to support
                </a>
            </div>
        </div>
    </div>
</section>
@endsection

@extends('layouts.site')
@section('title', 'Ethical Merchant Directory · LuvuPay')
@section('content')
<div class="mx-auto max-w-5xl px-5 py-14 sm:px-8">
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-600">Directory</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink">Ethical Merchant Directory</h1>
    <p class="mt-3 max-w-2xl text-muted leading-relaxed">
        Merchants who ship with LuvuPay can opt into a public trust badge — a signal that Second Chance and fair resale practices are on
    </p>

    <div class="mt-10 grid gap-4 sm:grid-cols-2">
        @forelse(($merchants ?? []) as $m)
            <div class="rounded-2xl border border-line bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                        Protected by LuvuPay
                    </span>
                    @if(($m->subscription_tier ?? '') === 'enterprise')
                        <span class="inline-flex rounded-full border border-brand-200 bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">
                            {{ $m->subscription_tier }}
                        </span>
                    @endif
                </div>
                <h2 class="mt-3 text-lg font-semibold text-ink">{{ $m->name }}</h2>
                <p class="mt-1 text-sm text-muted">Ethical subscription practices · Second Chance enabled</p>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-line bg-wash px-6 py-16 text-center">
                <p class="font-semibold text-ink">No directory members yet</p>
                <p class="mt-1 text-sm text-muted">Merchants opt in from Settings (<code class="text-xs">show_in_ethical_directory</code>)</p>
            </div>
        @endforelse
    </div>
</div>
@endsection

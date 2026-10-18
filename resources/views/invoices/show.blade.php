@php
    $fmt = fn ($minor, $c = null) => number_format(($minor ?? 0) / 100, 2).' '.($c ?? $invoice->currency);
    $issued = \Illuminate\Support\Carbon::parse($invoice->issued_at ?? $invoice->created_at)->toFormattedDateString();
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'none'; form-action 'none'; base-uri 'self'; object-src 'none'">
    <title>Invoice {{ $invoice->invoice_number }} · LuvuPay</title>
    <link rel="stylesheet" href="/css/ui.css?v={{ @filemtime(public_path('css/ui.css')) }}">
</head>
<body class="min-h-full px-4 py-8">
<div class="mx-auto max-w-3xl">
    <div class="no-print mb-4 flex flex-wrap gap-3">
        <x-ui.button type="button" icon="print" data-print>Download PDF / Print</x-ui.button>
        <x-ui.button variant="secondary" icon="arrow" href="/dashboard">Back to console</x-ui.button>
    </div>

    <article class="invoice-sheet relative rounded-2xl border border-slate-200 p-8 shadow-2xl shadow-black/40 sm:p-10">
        @if($invoice->status === 'paid')
            <span class="pointer-events-none absolute right-8 top-28 -rotate-12 rounded-lg border-[3px] border-emerald-600 px-4 py-1 text-xl font-black tracking-[0.12em] text-emerald-600/85">PAID</span>
        @endif

        <header class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-2.5 text-lg font-extrabold tracking-tight text-slate-900"><x-ui.logo :size="34" />LuvuPay</div>
            <div class="text-right"><p class="font-bold text-slate-900">{{ $merchant->name ?? 'Merchant' }}</p><p class="mt-0.5 text-xs text-slate-500">Merchant of record</p></div>
        </header>

        <h1 class="mt-7 text-3xl font-extrabold tracking-tight text-slate-900">Invoice</h1>
        <div class="mt-1 space-y-0.5 text-sm text-slate-500">
            <p><strong class="text-slate-800">{{ $invoice->invoice_number }}</strong></p>
            <p>Issued {{ $issued }}</p>
            <p>Status: {{ strtoupper($invoice->status) }}</p>
        </div>

        <div class="my-7 grid gap-6 sm:grid-cols-2">
            <div>
                <h2 class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">Bill to</h2>
                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $invoice->billing_name }}@if($invoice->billing_address)
{{ $invoice->billing_address }}@endif @if($invoice->tax_id)
Tax ID: {{ $invoice->tax_id }}@endif @if(!empty($meta['email']))
{{ $meta['email'] }}@endif</p>
            </div>
            <div>
                <h2 class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">Remit to</h2>
                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-800">LuvuPay Platform
Ethical Subscription Infrastructure
support@luvupay.local
Payments via PayPal (merchant of record)</p>
            </div>
        </div>

        <table class="w-full text-sm">
            <thead><tr class="border-b-2 border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                <th class="py-2 pr-2 font-semibold">Description</th><th class="px-2 py-2 text-right font-semibold">Qty</th><th class="px-2 py-2 text-right font-semibold">Unit</th><th class="py-2 pl-2 text-right font-semibold">Amount</th></tr></thead>
            <tbody class="text-slate-800">
            @foreach($lineItems as $item)
                <tr class="border-b border-slate-200 align-top">
                    <td class="py-3 pr-2">{{ $item['description'] ?? 'Line item' }}@if(!empty($item['note']))<p class="mt-0.5 text-xs text-slate-500">{{ $item['note'] }}</p>@endif</td>
                    <td class="px-2 py-3 text-right tabular-nums">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="px-2 py-3 text-right tabular-nums">{{ $fmt($item['unit_amount_minor'] ?? $item['amount_minor'] ?? 0) }}</td>
                    <td class="py-3 pl-2 text-right tabular-nums">{{ $fmt($item['amount_minor'] ?? 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <dl class="ml-auto mt-4 w-64 text-sm text-slate-800">
            <div class="flex justify-between py-1"><dt>Subtotal</dt><dd class="tabular-nums">{{ $fmt($invoice->subtotal_minor) }}</dd></div>
            <div class="flex justify-between py-1"><dt>Tax</dt><dd class="tabular-nums">{{ $fmt($invoice->tax_amount_minor) }}</dd></div>
            <div class="mt-1 flex justify-between border-t-2 border-slate-900 pt-2 text-base font-extrabold"><dt>Total</dt><dd class="tabular-nums">{{ $fmt($invoice->total_minor) }}</dd></div>
        </dl>

        <footer class="mt-8 flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-4 text-xs text-slate-500">
            <p>Transaction {{ $invoice->transaction_id }}@if(!empty($meta['provider_reference'])) · {{ $meta['provider_reference'] }}@endif</p>
            <p>Generated by LuvuPay · Sandbox</p>
        </footer>
    </article>
</div>
<script src="/js/ui.js?v={{ @filemtime(public_path('js/ui.js')) }}" defer></script>
</body>
</html>

@props(['tone' => 'neutral', 'dot' => false])
@php
    $tones = [
        'neutral' => 'border-edge bg-white/6 text-slate-300',
        'ok' => 'border-ok/25 bg-ok/12 text-ok',
        'warn' => 'border-warn/25 bg-warn/12 text-warn',
        'bad' => 'border-bad/25 bg-bad/12 text-bad',
        'info' => 'border-info/25 bg-info/12 text-info',
        'brand' => 'border-brand/30 bg-brand/15 text-brand-soft',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold '.($tones[$tone] ?? $tones['neutral'])]) }}>
    @if($dot)<span class="size-1.5 rounded-full bg-current"></span>@endif{{ $slot }}
</span>

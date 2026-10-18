@props(['label', 'icon' => 'chart', 'tone' => 'brand', 'caption' => null])
@php
    $tones = ['brand' => 'bg-brand/15 text-brand-soft', 'ok' => 'bg-ok/15 text-ok', 'warn' => 'bg-warn/15 text-warn', 'bad' => 'bg-bad/15 text-bad', 'info' => 'bg-info/15 text-info'];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-edge bg-surface/80 p-5 shadow-xl shadow-black/20 animate-rise']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ $label }}</p>
        <span class="grid size-9 place-items-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}"><x-ui.icon :name="$icon" :size="17" /></span>
    </div>
    <p class="mt-3 text-2xl font-bold tracking-tight text-white tabular-nums sm:text-3xl">{{ $slot }}</p>
    @if($caption)<p class="mt-1 text-xs text-muted">{{ $caption }}</p>@endif
</div>

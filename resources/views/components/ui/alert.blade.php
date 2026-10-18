@props(['tone' => 'info', 'title' => null])
@php
    $tones = [
        'info' => ['border-info/30 bg-info/10 text-sky-100', 'info'],
        'ok' => ['border-ok/30 bg-ok/10 text-emerald-100', 'check'],
        'warn' => ['border-warn/30 bg-warn/10 text-amber-100', 'alert'],
        'bad' => ['border-bad/30 bg-bad/10 text-rose-100', 'alert'],
    ];
    [$class, $icon] = $tones[$tone] ?? $tones['info'];
@endphp
<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm '.$class, 'role' => $tone === 'bad' ? 'alert' : 'status']) }}>
    <x-ui.icon :name="$icon" :size="18" class="mt-0.5" />
    <div class="min-w-0">@if($title)<p class="font-semibold">{{ $title }}</p>@endif<div class="{{ $title ? 'mt-0.5 opacity-90' : '' }}">{{ $slot }}</div></div>
</div>

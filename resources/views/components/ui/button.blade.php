@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button', 'icon' => null])
@php
    $sizes = ['sm' => 'px-3 py-1.5 text-xs', 'md' => 'px-4 py-2.5 text-sm', 'lg' => 'px-5 py-3 text-sm'];
    $variants = [
        'primary' => 'bg-brand text-white shadow-lg shadow-brand/25 hover:bg-violet-500',
        'secondary' => 'border border-edge bg-white/4 text-ink hover:bg-white/8',
        'ghost' => 'text-muted hover:bg-white/6 hover:text-white',
        'danger' => 'border border-bad/30 bg-bad/15 text-bad hover:bg-bad/25',
        'warn' => 'border border-warn/30 bg-warn/15 text-warn hover:bg-warn/25',
        'success' => 'bg-ok text-slate-950 hover:brightness-110',
        'paypal' => 'bg-[#ffc439] text-[#003087] shadow-lg shadow-amber-500/20 hover:brightness-105',
    ];
    $class = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold whitespace-nowrap transition disabled:pointer-events-none disabled:opacity-50 '
        .($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>@if($icon)<x-ui.icon :name="$icon" :size="16" />@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>@if($icon)<x-ui.icon :name="$icon" :size="16" />@endif{{ $slot }}</button>
@endif

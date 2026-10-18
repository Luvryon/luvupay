@props(['label', 'mono' => false])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-edge bg-white/3 p-4']) }}>
    <dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">{{ $label }}</dt>
    <dd class="mt-1 break-all text-sm text-ink {{ $mono ? 'font-mono text-xs' : '' }}">{{ $slot }}</dd>
</div>

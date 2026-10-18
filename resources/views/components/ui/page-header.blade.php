@props(['title', 'subtitle' => null, 'eyebrow' => null])
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        @if($eyebrow)<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-soft">{{ $eyebrow }}</p>@endif
        <h1 class="mt-0.5 text-2xl font-bold tracking-tight text-white">{{ $title }}</h1>
        @if($subtitle)<p class="mt-1 max-w-2xl text-sm text-muted">{{ $subtitle }}</p>@endif
    </div>
    @if(trim((string) $slot) !== '')<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>

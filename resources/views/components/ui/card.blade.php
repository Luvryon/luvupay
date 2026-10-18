@props(['title' => null, 'subtitle' => null, 'icon' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'rounded-2xl border border-edge bg-surface/80 shadow-xl shadow-black/20 backdrop-blur']) }}>
    @if($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5">
            <div class="min-w-0">
                @if($title)
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-white">
                        @if($icon)<x-ui.icon :name="$icon" :size="16" class="text-brand-soft" />@endif{{ $title }}
                    </h2>
                @endif
                @if($subtitle)<p class="mt-1 text-xs text-muted">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div class="{{ $flush ? 'mt-4' : 'p-5 '.(($title || isset($actions)) ? 'pt-4' : '') }}">{{ $slot }}</div>
</section>

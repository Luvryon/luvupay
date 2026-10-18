@props(['icon' => 'info', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 px-6 py-10 text-center']) }}>
    <span class="grid size-11 place-items-center rounded-2xl bg-white/5 text-muted"><x-ui.icon :name="$icon" :size="20" /></span>
    <p class="text-sm font-semibold text-white">{{ $title }}</p>
    @if($text)<p class="max-w-sm text-xs text-muted">{{ $text }}</p>@endif
    {{ $slot }}
</div>

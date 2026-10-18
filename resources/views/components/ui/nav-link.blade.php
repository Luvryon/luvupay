@props(['href', 'icon', 'active' => false, 'external' => false])
<a href="{{ $href }}"
   @if($active) aria-current="page" @endif
   @if($external) target="_blank" rel="noopener noreferrer" @endif
   {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition '.($active ? 'bg-[var(--lx-brand-50,#f1eeff)] text-[var(--lx-brand-700,#4a2ed6)]' : 'text-[var(--lx-muted,#5f6472)] hover:bg-[var(--lx-bg,#f7f7fb)] hover:text-[var(--lx-ink,#14151a)]')]) }}>
    <x-ui.icon :name="$icon" :size="16" class="{{ $active ? 'text-[var(--lx-brand,#5b3df5)]' : 'opacity-60 group-hover:opacity-100' }}" />
    <span class="truncate">{{ $slot }}</span>
    @if($external)<x-ui.icon name="external" :size="11" class="ml-auto opacity-40" />@endif
</a>

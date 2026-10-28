@props(['show', 'title' => null, 'locked' => 'false'])
<div x-show="{{ $show }}" x-cloak class="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif
     x-on:keydown.escape.window="if (!({{ $locked }})) {{ $show }} = false">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" x-on:click="if (!({{ $locked }})) {{ $show }} = false"></div>
    <div x-show="{{ $show }}" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="relative flex h-full w-full max-w-lg flex-col border-l border-edge bg-surface shadow-2xl shadow-black/60">
        {{ $slot }}
    </div>
</div>

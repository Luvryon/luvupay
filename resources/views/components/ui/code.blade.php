@props(['copy' => null])
<div class="flex items-start gap-2 rounded-xl border border-edge bg-black/40 p-3">
    <code {{ $attributes->merge(['class' => 'min-w-0 flex-1 select-all break-all text-xs leading-5 text-slate-300']) }}>{{ $slot }}</code>
    @if($copy)
        <button type="button" data-copy="{{ $copy }}" class="shrink-0 rounded-lg border border-edge p-1.5 text-muted transition hover:bg-white/8 hover:text-white" aria-label="Copy to clipboard"><x-ui.icon name="copy" :size="14" /></button>
    @endif
</div>

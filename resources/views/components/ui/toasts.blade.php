{{-- Expects `toasts` ([{id,message,icon,leaving}]) in the enclosing Alpine scope. Rendered as text only (x-text), never HTML.
     z-[200] sits above modal (z-[100]) so toasts always remain visible. --}}
<div class="pointer-events-none fixed right-4 top-4 z-[200] flex w-[min(100vw-2rem,380px)] flex-col gap-2" aria-live="polite">
    <template x-for="t in toasts" x-bind:key="t.id">
        <div
            class="pointer-events-auto flex items-start gap-2.5 rounded-xl border border-edge bg-raised/95 px-4 py-3 text-[13px] text-white shadow-2xl shadow-black/50 backdrop-blur-xl"
            x-bind:class="t.leaving ? 'animate-toast-out' : 'animate-toast-in'"
        >
            <span aria-hidden="true" x-text="t.icon"></span>
            <span class="font-medium leading-snug" x-text="t.message"></span>
        </div>
    </template>
</div>

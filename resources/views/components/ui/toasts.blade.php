{{-- Toastify-style: white cards, top-right, above modals (z-99999) --}}
<div class="luvu-toasts" aria-live="polite">
    <template x-for="t in toasts" x-bind:key="t.id">
        <div class="luvu-toast" x-bind:class="t.leaving ? 'leaving' : ''">
            <span class="ico" aria-hidden="true" x-text="t.icon || '✓'"></span>
            <span class="msg" x-text="t.message"></span>
        </div>
    </template>
</div>

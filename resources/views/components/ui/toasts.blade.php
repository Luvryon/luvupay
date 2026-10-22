{{-- Toastify-style stack (expects Alpine `toasts: [{id,message,icon,type,leaving}]`) --}}
<div class="Toastify Toastify--top-right" aria-live="polite" aria-atomic="false">
    <template x-for="t in toasts" x-bind:key="t.id">
        <div class="Toastify__toast"
             x-bind:class="{
                'Toastify__toast--success': (t.type||'') === 'success' || t.icon === '✅',
                'Toastify__toast--error': (t.type||'') === 'error' || t.icon === '⚠️',
                'Toastify__toast--warning': (t.type||'') === 'warning' || t.icon === '⏳',
                'Toastify__toast--info': (t.type||'') === 'info',
                'Toastify__toast--leaving': t.leaving
             }">
            <span class="Toastify__toast-icon" x-text="t.icon || '✓'"></span>
            <div class="Toastify__toast-body" x-text="t.message"></div>
        </div>
    </template>
</div>

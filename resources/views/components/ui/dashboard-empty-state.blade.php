{{-- Loading --}}
<div x-show="loading && !hasData" x-cloak class="c-card" style="padding:48px 32px;text-align:center">
    <div style="margin:0 auto 16px;width:40px;height:40px;border:3px solid #e2e8f0;border-top-color:#5b3df5;border-radius:50%;animation:spin .8s linear infinite"></div>
    <p style="margin:0;font-weight:600;color:var(--ink)">Loading ledger…</p>
    <p style="margin:8px 0 0;font-size:.875rem;color:var(--muted)">Fetching live transactions from the API</p>
</div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

{{-- True empty (not loading) --}}
<div x-show="!loading && !hasData" x-cloak class="c-card" style="padding:48px 32px;text-align:center">
    <div style="margin:0 auto 16px;width:56px;height:56px;border-radius:16px;background:#eef2ff;display:grid;place-items:center;font-size:1.5rem">📊</div>
    <p style="margin:0;font-size:1.1rem;font-weight:700;color:var(--ink)">Your ledger is empty</p>
    <p style="margin:10px auto 0;max-width:380px;font-size:.9rem;color:var(--muted);line-height:1.5">
        Once subscribers pay, transactions appear here automatically.
        Start with a new subscription checkout to see the full lifecycle.
    </p>
    <button type="button"
            class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 font-medium shadow-sm mt-6 disabled:opacity-50"
            x-on:click="openCheckout()"
            x-bind:disabled="simBusy || checkoutPaying">
        New subscription checkout
    </button>
</div>

{{-- AI Retention Campaign Studio — preview mirrors the real email layout --}}
<x-ui.modal show="campaignStudioOpen" title="AI Retention Campaign Studio" size="xl">
    <div class="campaign-studio space-y-4 text-slate-900 px-6 pt-4 pb-6"
         x-data="campaignStudio()">
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Live email preview (same structure as sent HTML) --}}
            <section class="rounded-xl border border-slate-200 bg-slate-100 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2 px-1">Email preview (as sent)</p>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="px-5 py-4 text-white" style="background:#4f46e5">
                        <div class="text-[11px] uppercase tracking-wide opacity-90" x-text="brand"></div>
                        <div class="mt-1 text-lg font-extrabold leading-snug">
                            Your <span x-text="brand"></span> access can still be saved
                        </div>
                    </div>
                    <div class="space-y-3 px-5 py-5 text-sm leading-relaxed text-slate-700">
                        <template x-if="customImage">
                            <img x-bind:src="customImage" alt="" class="w-full max-h-40 rounded-xl object-cover">
                        </template>
                        <p class="text-[15px] text-slate-900">Hi <strong x-text="previewName"></strong>,</p>
                        <p class="text-slate-600" x-text="bodyLead"></p>
                        <p class="text-slate-600">We know payments fail for everyday reasons - an expired card, a bank hold, or a busy week. You do not have to start over. Finish recovery in one step and keep the benefits you already earned.</p>
                        <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                            <div class="text-[10px] font-bold uppercase tracking-wide text-indigo-700">Merchant-approved offer</div>
                            <div class="mt-0.5 text-xl font-extrabold text-indigo-900" x-text="discount + '% off'"></div>
                            <div class="mt-1 text-xs text-slate-600">Limited recovery window. Renew now to restore access without losing your place.</div>
                        </div>
                        <div class="flex justify-center py-1">
                            <span class="inline-block rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white"
                                  x-text="'Renew with ' + discount + '% off'"></span>
                        </div>
                        <p class="text-xs text-slate-500">Prefer another option? Reply to this email and we can help with a split payment, a temporary pause, or a safe partial refund where policy allows.</p>
                        <p class="border-t border-slate-100 pt-3 text-[11px] text-slate-400">
                            Sent by <strong x-text="brand"></strong> via LuvuPay Retention Studio.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Controls --}}
            <section class="space-y-4">
                <div>
                    <label class="text-xs font-semibold text-slate-700" for="campaign-discount">Discount offer (merchant controlled)</label>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-sm font-bold text-indigo-600" x-text="discount + '%'"></span>
                    </div>
                    <input id="campaign-discount" type="range" min="5" max="40" step="5" x-model.number="discount" class="w-full accent-indigo-600">
                </div>

                <div>
                    <label class="text-xs font-semibold text-slate-700" for="campaign-subject">Subject</label>
                    <input id="campaign-subject" type="text" x-model="subject" maxlength="200"
                           class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="text-xs font-semibold text-slate-700" for="campaign-body">Body</label>
                    <textarea id="campaign-body" x-model="bodyLead" rows="4" maxlength="5000"
                              class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm leading-relaxed"></textarea>
                </div>

                <div>
                    <p class="text-xs font-semibold text-slate-700 mb-2">Campaign image</p>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="img in images" :key="img.id">
                            <button type="button"
                                    class="rounded-lg border px-3 py-2 text-xs font-medium"
                                    x-bind:class="selectedImage === img.id && !customImage ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'"
                                    x-on:click="selectedImage = img.id; customImage = null"
                                    x-text="img.label"></button>
                        </template>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        <span x-text="customImage ? 'Image ready (compressed)' : 'Upload image (auto-compressed)'"></span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" x-on:change="onFile($event)">
                    </label>
                    <p class="mt-1 text-[11px] text-slate-400">Image is embedded in the email (works even if APP_URL is localhost).</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="c-btn c-btn-sm" x-on:click="regenerateCopy()" x-bind:disabled="generating">
                        <span x-text="generating ? 'Generating…' : 'Generate text'"></span>
                    </button>
                    <button type="button" class="c-btn c-btn-sm c-btn-primary" x-on:click="approveAndSend()" x-bind:disabled="sending || sent">
                        <span x-text="sent ? 'Queued' : (sending ? 'Sending…' : 'Approve and send')"></span>
                    </button>
                </div>
                <p class="text-[11px] text-slate-500">Sends only to users with failed payment or refund requests after you approve. No automatic send without merchant consent.</p>
            </section>
        </div>
    </div>
</x-ui.modal>

{{-- AI Retention Campaign Studio — color picker + live email preview --}}
<x-ui.modal show="campaignStudioOpen" title="AI Retention Campaign Studio" size="xl">
    <div class="campaign-studio space-y-4 text-slate-900 px-6 pt-4 pb-6"
         x-data="campaignStudio()">
        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Live email preview --}}
            <section class="rounded-xl border border-slate-200 bg-slate-100 p-3">
                <p class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Email preview (as sent)</p>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="px-5 py-4 text-white" x-bind:style="'background:' + brandColor">
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
                        <div class="rounded-xl px-4 py-3"
                             x-bind:style="'background:' + brandSoft + ';border:1px solid ' + brandBorder">
                            <div class="text-[10px] font-bold uppercase tracking-wide"
                                 x-bind:style="'color:' + brandColor">Merchant-approved offer</div>
                            <div class="mt-0.5 text-xl font-extrabold text-slate-900" x-text="discount + '% off'"></div>
                            <div class="mt-1 text-xs text-slate-600">Limited recovery window. Renew now to restore access without losing your place.</div>
                        </div>
                        <div class="flex justify-center py-1">
                            <span class="inline-block rounded-full px-5 py-2.5 text-sm font-bold text-white"
                                  x-bind:style="'background:' + brandColor"
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
                    <p class="text-xs font-semibold text-slate-700 mb-2">Brand color</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-for="c in colorPresets" :key="c.id">
                            <button type="button"
                                    class="size-8 rounded-full border-2 shadow-sm"
                                    x-bind:style="'background:' + c.value"
                                    x-bind:class="brandColor === c.value ? 'border-slate-900 ring-2 ring-offset-1 ring-slate-400' : 'border-white'"
                                    x-bind:title="c.label"
                                    x-on:click="setPreset(c.value)"
                                    x-bind:aria-label="c.label"></button>
                        </template>
                        <label class="relative ml-1 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                            <span class="size-5 rounded-md border border-slate-200" x-bind:style="'background:' + brandColor"></span>
                            Custom
                            <input type="color" class="absolute inset-0 cursor-pointer opacity-0"
                                   x-bind:value="brandColor" x-on:input="onColorInput($event)">
                        </label>
                        <input type="text" maxlength="7" x-model="brandColor" x-on:change="brandColor = (brandColor || '#4f46e5')"
                               class="w-[5.5rem] rounded-lg border border-slate-200 px-2 py-1.5 font-mono text-xs"
                               placeholder="#4f46e5" aria-label="Hex color">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-slate-700" for="campaign-discount">Discount offer (merchant controlled)</label>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-sm font-bold" x-bind:style="'color:' + brandColor" x-text="discount + '%'"></span>
                    </div>
                    <input id="campaign-discount" type="range" min="5" max="40" step="5" x-model.number="discount" class="w-full"
                           x-bind:style="'accent-color:' + brandColor">
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
                    <p class="mb-2 text-xs font-semibold text-slate-700">Campaign image</p>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        <span x-text="customImage ? 'Image ready (compressed)' : 'Upload image (auto-compressed)'"></span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" x-on:change="onFile($event)">
                    </label>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="c-btn c-btn-sm" x-on:click="regenerateCopy()" x-bind:disabled="generating">
                        <span x-text="generating ? 'Generating…' : 'Generate text'"></span>
                    </button>
                    <button type="button" class="c-btn c-btn-sm text-white"
                            x-bind:style="'background:' + brandColor"
                            x-on:click="approveAndSend()" x-bind:disabled="sending || sent">
                        <span x-text="sent ? 'Queued' : (sending ? 'Sending…' : 'Approve and send')"></span>
                    </button>
                </div>
                <p class="text-[11px] text-slate-500">Sends only to users with failed payment or refund requests after you approve. Brand color applies to the real email header and CTA.</p>
            </section>
        </div>
    </div>
</x-ui.modal>

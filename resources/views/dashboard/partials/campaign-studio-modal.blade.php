{{-- AI Retention Campaign Studio — logic in /js/campaign-studio.js (never inline x-data JS) --}}
<x-ui.modal show="campaignStudioOpen" title="AI Retention Campaign Studio" size="xl">
    <div class="campaign-studio space-y-4 text-slate-900 px-6 pt-4 pb-6"
         x-data="campaignStudio()">
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email preview</p>
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="inline-block size-8 rounded-lg"
                              x-bind:style="'background:' + (selectedImage >= 0 && images[selectedImage] ? images[selectedImage].color : '#e2e8f0')"></span>
                        <div>
                            <p class="text-sm font-bold text-slate-900" x-text="brand"></p>
                            <p class="text-[11px] text-slate-500">Retention offer</p>
                        </div>
                    </div>
                    <template x-if="customImage">
                        <img x-bind:src="customImage" alt="" class="mb-3 max-h-28 w-full rounded-lg object-cover">
                    </template>
                    <p class="text-sm font-semibold text-slate-900" x-text="subject"></p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600" x-text="bodyLead"></p>
                    <p class="mt-3 text-xs font-semibold text-indigo-600" x-text="discount + '% off · limited time'"></p>
                </div>
                <p class="text-[11px] text-slate-500">Tone: <span x-text="tone"></span></p>
            </section>

            <section class="space-y-4">
                <div>
                    <label class="text-xs font-semibold text-slate-700" for="campaign-discount">Discount offer (merchant controlled)</label>
                    <div class="mt-1 flex items-center justify-between">
                        <span class="text-sm font-bold text-indigo-600" x-text="discount + '%'"></span>
                    </div>
                    <input id="campaign-discount" type="range" min="5" max="40" step="5" x-model.number="discount" class="w-full accent-indigo-600">
                </div>

                <div>
                    <p class="text-xs font-semibold text-slate-700 mb-2">Campaign image</p>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="img in images" :key="img.id">
                            <button type="button"
                                    class="rounded-lg border px-3 py-2 text-xs font-medium"
                                    x-bind:class="selectedImage === img.id ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600'"
                                    x-on:click="selectedImage = img.id"
                                    x-text="img.label"></button>
                        </template>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Upload image (auto-compressed)
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" x-on:change="onFile($event)">
                    </label>
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

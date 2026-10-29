{{-- AI Retention Campaign Studio - image upload + per-campaign AI copy (icons, no emoji, no em dash) --}}
<x-ui.modal show="campaignStudioOpen" title="AI Retention Campaign Studio" size="xl">
    <div class="campaign-studio space-y-4 text-slate-900 px-6 pt-4 pb-6"
         x-data="{
            discount: 20,
            selectedImage: 0,
            customImage: null,
            images: [
                { id: 0, label: 'Warm abstract', color: '#fef3c7' },
                { id: 1, label: 'Product lifestyle', color: '#e0e7ff' },
                { id: 2, label: 'Loyalty badge', color: '#ede9fe' },
            ],
            sending: false,
            sent: false,
            generating: false,
            subject: 'We miss you, Mario. Keep your access active',
            bodyLead: 'You have been with us for a long time. Your last payment failed, but your access can still be saved.',
            previewName: 'Mario',
            brand: 'StreamNetFly',
            tone: 'Empathetic (loyal user)',
            onFile(e) {
                var f = e.target.files && e.target.files[0];
                if (!f) return;
                var reader = new FileReader();
                reader.onload = (ev) => { this.customImage = ev.target.result; this.selectedImage = -1; };
                reader.readAsDataURL(f);
            },
            async regenerateCopy() {
                this.generating = true;
                await new Promise(r => setTimeout(r, 1200));
                var variants = [
                    { s: 'Important: keep your ' + this.brand + ' access', b: 'Hi ' + this.previewName + ', we noticed a payment issue. Renew now with ' + this.discount + '% off to keep uninterrupted access.' },
                    { s: 'We miss you, ' + this.previewName, b: 'Hi ' + this.previewName + ', your plan is still recoverable. As a valued member you get ' + this.discount + '% off if you renew today.' },
                    { s: this.previewName + ', a quick way to restore access', b: 'Payment failed once. Finish in one click and keep your benefits. Offer: ' + this.discount + '% off for 24 hours.' },
                ];
                var v = variants[Math.floor(Math.random() * variants.length)];
                this.subject = v.s;
                this.bodyLead = v.b;
                this.generating = false;
            },
            async approveAndSend() {
                if (this.sending || this.sent) return;
                this.sending = true;
                try {
                    const res = await fetch('/api/v1/dashboard/demo/retention-campaign', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            subject: this.subject,
                            body: this.bodyLead || this.body || '',
                            discount: this.discount,
                            tone: this.tone || 'empathetic',
                            image_data_url: this.customImage || null,
                            cohort: 'failed_and_refund'
                        })
                    });
                    const data = await res.json().catch(() => ({}));
                    this.sending = false;
                    if (!res.ok || data.ok === false) {
                        if (window.LuvuToast) window.LuvuToast.error('Send failed', data.message || 'Check mail config');
                        else alert(data.message || 'Send failed');
                        return;
                    }
                    this.sent = true;
                    if (window.LuvuToast) window.LuvuToast.success('Campaign sent', data.message || ('Sent to ' + (data.sent||0) + ' users'));
                } catch (e) {
                    this.sending = false;
                    if (window.LuvuToast) window.LuvuToast.error('Send failed', String(e.message || e));
                }
            }
         }">

        <p class="text-sm text-slate-500">
            Hyper-personalized win-back email driven by Nebius signals.
            <span class="text-slate-400">Merchant approval required before any send.</span>
        </p>

        <div class="grid grid-cols-2 gap-6 max-md:grid-cols-1">
            <section aria-label="Email preview" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Email Preview</p>
                    <span class="text-[10px] text-slate-400 font-mono">to: failed / refund cohort</span>
                </div>
                <div class="p-5 space-y-3">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Subject</p>
                        <p class="text-sm font-bold text-slate-900" x-text="subject"></p>
                    </div>
                    <div class="border-t border-slate-100 pt-3">
                        <div class="rounded-lg h-28 mb-4 border border-slate-100 overflow-hidden flex items-center justify-center"
                             x-bind:style="customImage && selectedImage === -1 ? '' : ('background:' + (images[selectedImage] && images[selectedImage].color || '#f1f5f9'))">
                            <template x-if="customImage && selectedImage === -1">
                                <img x-bind:src="customImage" alt="" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!(customImage && selectedImage === -1)">
                                <span class="text-xs font-medium text-slate-500" x-text="images[selectedImage] ? images[selectedImage].label : 'Image'"></span>
                            </template>
                        </div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                            Hi <strong x-text="previewName">Mario</strong>,
                        </p>
                        <p class="text-sm text-slate-600 leading-relaxed mt-2" x-text="bodyLead"></p>
                        <div class="mt-4">
                            <span class="inline-block rounded-lg bg-indigo-600 text-white text-xs font-semibold px-4 py-2.5">
                                Renew with <span x-text="discount + '%'"></span> off
                            </span>
                        </div>
                        <p class="mt-4 text-[11px] text-slate-400">The <span x-text="brand"></span> team · powered by LuvuPay AI</p>
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="grid place-items-center rounded-md bg-violet-50 text-violet-700" style="width:28px;height:28px">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        </span>
                        <p class="text-xs font-semibold text-slate-800">Tone</p>
                    </div>
                    <span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded-full font-medium" x-text="tone"></span>
                    <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                        Loyal users get an empathetic tone. New or high-risk users get a short transactional tone.
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-700" for="campaign-discount">Discount offer (merchant controlled)</label>
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
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Upload image
                        <input type="file" accept="image/*" class="hidden" x-on:change="onFile($event)">
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

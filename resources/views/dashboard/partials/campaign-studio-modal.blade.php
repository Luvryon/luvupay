{{-- AI Retention Campaign Studio — email preview + campaign settings + AI tone / subject --}}
<x-ui.modal show="campaignStudioOpen" title="AI Retention Campaign Studio" size="xl">
    <div class="campaign-studio space-y-4 text-slate-900 px-6 pt-4 pb-6"
         x-data="{
            discount: 20,
            selectedImage: 0,
            images: [
                { id: 0, label: 'Warm abstract', emoji: '🌅' },
                { id: 1, label: 'Product lifestyle', emoji: '📺' },
                { id: 2, label: 'Loyalty badge', emoji: '💜' },
            ],
            sending: false,
            sent: false,
            subject: 'We miss you, Mario! Do not lose your StreamNetFly access',
            previewName: 'Mario',
            brand: 'StreamNetFly',
            async approveAndSend() {
                if (this.sending || this.sent) return;
                this.sending = true;
                await new Promise(r => setTimeout(r, 900));
                this.sending = false;
                this.sent = true;
                if (typeof this.pushToast === 'function') {
                    this.pushToast('Campaign approved & queued for ' + this.previewName, '📧');
                } else if (window.LuvuToast) {
                    window.LuvuToast.success('Campaign sent', 'Retention email queued for ' + this.previewName);
                }
            }
         }">

        <p class="text-sm text-slate-500">
            Hyper-personalized win-back email driven by Nebius LTV signals.
            <span class="text-slate-400">Demo studio — no live send until Approve &amp; Send</span>
        </p>

        <div class="grid grid-cols-2 gap-6 max-md:grid-cols-1">
            {{-- LEFT: Email Preview --}}
            <section aria-label="Email preview" class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Email Preview</p>
                    <span class="text-[10px] text-slate-400 font-mono">to: mario@fitclub.io</span>
                </div>
                <div class="p-5 space-y-3">
                    {{-- Feature 2: Hyper-Personalized Subject --}}
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Subject</p>
                        <div class="flex flex-wrap items-start gap-2">
                            <p class="text-sm font-bold text-slate-900 mb-0 flex-1 min-w-0" x-text="subject">
                                We miss you, Mario! Do not lose your StreamNetFly access
                            </p>
                            <span class="bg-amber-100 text-amber-700 text-[10px] px-1.5 py-0.5 rounded font-medium shrink-0">
                                Optimized for Mario (Open Rate +40%)
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-3">
                        {{-- Hero image from selector --}}
                        <div class="rounded-lg bg-gradient-to-br from-indigo-50 to-purple-50 h-28 flex items-center justify-center mb-4 border border-slate-100">
                            <span class="text-4xl" x-text="images[selectedImage].emoji"></span>
                        </div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                            Hi <strong x-text="previewName">Mario</strong>,
                        </p>
                        <p class="text-sm text-slate-600 leading-relaxed mt-2">
                            You have been with <strong x-text="brand">StreamNetFly</strong>
                            for a long time. Your last payment failed, but your access can still be saved.
                            As a loyal customer you get a
                            <strong class="text-indigo-600" x-text="discount + '%'">20%</strong>
                            discount if you renew now
                        </p>
                        <div class="mt-4">
                            <span class="inline-block rounded-lg bg-indigo-600 text-white text-xs font-semibold px-4 py-2.5">
                                Renew with <span x-text="discount + '%'"></span> off
                            </span>
                        </div>
                        <p class="mt-4 text-[11px] text-slate-400">— The <span x-text="brand"></span> · powered by LuvuPay AI</p>
                    </div>
                </div>
            </section>

            {{-- RIGHT: Campaign Settings --}}
            <section aria-label="Campaign settings" class="rounded-xl border border-slate-200 bg-white shadow-sm p-5 space-y-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Campaign Settings</p>

                {{-- Feature 1: AI Tone Analysis --}}
                <div class="rounded-lg border border-purple-100 bg-purple-50/60 p-3.5">
                    <p class="text-xs font-semibold text-slate-700 mb-2">AI Tone Analysis</p>
                    <span class="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded-full font-medium">
                        Tone: Empathetic (Loyal User)
                    </span>
                    <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                        AI detected high LTV. Adjusted language to be supportive rather than transactional
                    </p>
                </div>

                {{-- Discount Slider --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-700" for="campaign-discount">Discount offer</label>
                        <span class="text-sm font-bold text-indigo-600" x-text="discount + '%'"></span>
                    </div>
                    <input id="campaign-discount" type="range" min="5" max="50" step="5"
                           x-model.number="discount"
                           class="w-full accent-indigo-600 h-2 rounded-lg cursor-pointer">
                    <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                        <span>5%</span><span>50%</span>
                    </div>
                </div>

                {{-- Image Selector --}}
                <div>
                    <p class="text-xs font-semibold text-slate-700 mb-2">Hero image</p>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="img in images" :key="img.id">
                            <button type="button"
                                    class="rounded-lg border p-3 text-center transition-all"
                                    x-bind:class="selectedImage === img.id
                                        ? 'border-indigo-500 ring-2 ring-indigo-100 bg-indigo-50'
                                        : 'border-slate-200 hover:border-slate-300 bg-white'"
                                    x-on:click="selectedImage = img.id">
                                <span class="text-2xl block" x-text="img.emoji"></span>
                                <span class="text-[10px] text-slate-500 mt-1 block" x-text="img.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Audience chip --}}
                <div class="flex flex-wrap gap-2 text-[11px]">
                    <span class="rounded-full bg-slate-100 text-slate-600 px-2.5 py-1 font-medium">Segment: At-risk · High LTV</span>
                    <span class="rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-1 font-medium">Predicted save: $38</span>
                </div>

                {{-- Approve & Send --}}
                <div class="pt-1">
                    <button type="button"
                            class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            x-on:click="approveAndSend()"
                            x-bind:disabled="sending || sent">
                        <span x-show="!sending && !sent">Approve &amp; Send</span>
                        <span x-show="sending" x-cloak>Sending…</span>
                        <span x-show="sent && !sending" x-cloak>✓ Queued for delivery</span>
                    </button>
                    <p class="mt-2 text-[11px] text-slate-400 text-center" x-show="!sent">
                        Reviews subject, tone, and offer before enqueueing the retention email
                    </p>
                    <p class="mt-2 text-[11px] text-emerald-600 text-center font-medium" x-show="sent" x-cloak>
                        Campaign approved — Nebius will personalize delivery windows
                    </p>
                </div>
            </section>
        </div>
    </div>
</x-ui.modal>

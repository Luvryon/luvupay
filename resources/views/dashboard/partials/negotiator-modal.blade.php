{{-- AI Negotiator: two-way chat for failed payment / retention --}}
<x-ui.modal show="negotiatorOpen" title="AI Negotiator" size="lg">
    <div class="px-6 pt-4 pb-6 text-slate-900"
         x-data="negotiatorChat()"
         x-effect="if (negotiatorOpen) { ensureBoot() }">
        <p class="text-sm text-slate-500 mb-3">
            Two-way chat with retention AI. Options stay within merchant-safe policies (split pay, resale, safe refund).
        </p>

        <div class="rounded-xl border border-slate-200 bg-slate-50 h-72 overflow-y-auto p-3 space-y-3" id="negotiator-scroll">
            <template x-for="(m, i) in messages" :key="i">
                <div class="flex" x-bind:class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed"
                         x-bind:class="m.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-800'"
                         x-text="m.content"></div>
                </div>
            </template>
            <div x-show="thinking" class="flex items-center gap-2 text-xs text-slate-500">
                <span class="inline-block size-2 rounded-full bg-indigo-500 animate-pulse"></span>
                <span>AI is reviewing payment history and options…</span>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2" x-show="options.length">
            <template x-for="opt in options" :key="opt.id">
                <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:bg-indigo-50"
                        x-on:click="send('[option:' + opt.id + '] ' + opt.label)" x-text="opt.label"></button>
            </template>
        </div>

        <form class="mt-3 flex gap-2" x-on:submit.prevent="send(input)">
            <input type="text" x-model="input" maxlength="2000" placeholder="Type a reply…"
                   class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200" />
            <button type="submit" class="c-btn c-btn-primary c-btn-sm" x-bind:disabled="thinking || !input.trim()">Send</button>
        </form>
    </div>
</x-ui.modal>

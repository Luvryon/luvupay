{{-- Missed Cancellation Journey: timeline of the NEXT subscription charge + backend console + the subscriber's phone.
     Styling lives in journey-luxe.css (self-contained). All options are data-driven from journey.stage (server rules). --}}
<link rel="stylesheet" href="/css/journey-luxe.css?v={{ @filemtime(public_path('css/journey-luxe.css')) }}">

<x-ui.modal show="decisionOpen" title="Missed Cancellation Journey" size="xl">
    <div class="jx" style="padding:16px 24px 24px" x-data>

        <div class="jx-head">
            <p>Live on your real ledger for <strong x-text="journey.name"></strong> · <span x-text="journey.email"></span></p>
            <div class="jx-tags">
                <span class="jx-tag" x-bind:class="journey.sandbox ? 'is-blue' : 'is-amber'" x-text="journey.sandbox ? 'PayPal Sandbox connected' : 'Mock ledger (no PayPal keys)'"></span>
                <span class="jx-tag" x-bind:class="journey.nebius ? 'is-indigo' : ''" x-text="journey.nebius ? 'Nebius AI live' : 'AI: local template'"></span>
            </div>
        </div>

        {{-- Step 1: who and where on the timeline --}}
        <div x-show="journeyStep === 'setup'" x-cloak class="jx-panel">
            <div>
                <p class="jx-label">1. Choose the subscriber</p>
                <select class="jx-select" aria-label="Subscriber" x-on:change="pickJourneyUser($event.target.value)">
                    <template x-for="r in (journeySetup ? journeySetup.rows : [])" x-bind:key="r.id">
                        <option x-bind:value="r.id" x-bind:selected="journeySetup.rowId === r.id" x-text="r.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <p class="jx-label">2. Where are we on the timeline of the NEXT subscription charge?</p>
                <div class="jx-chips" role="radiogroup" aria-label="Position on the timeline">
                    <template x-for="o in timelineStations.slice(0, 5)" x-bind:key="o.h">
                        <button type="button" role="radio" class="jx-chip" x-bind:aria-checked="journeySetup && journeySetup.hours === o.h"
                                x-bind:class="journeySetup && journeySetup.hours === o.h ? 'is-on' : ''"
                                x-on:click="journeySetup.hours = o.h" x-text="o.long"></button>
                    </template>
                </div>
                <p class="jx-help">The current period stays paid and stays in your wallet. Before expiry the subscriber gets reminders by email and on the phone (written by Nebius AI) and can already pay, split or cancel. One day after expiry every recovery option opens. You can move along the timeline afterwards.</p>
            </div>
            <div class="jx-row-end">
                <button type="button" class="jx-btn" x-on:click="startJourney()" x-bind:disabled="decisionBusy" x-text="decisionBusy ? 'Starting…' : 'Start simulation'"></button>
            </div>
        </div>

        {{-- Thinking --}}
        <div x-show="journeyStep === 'loading'" x-cloak class="jx-think" role="status">
            <div class="jx-think-orb" aria-hidden="true"></div>
            <b x-text="aiThinkingLabel">Analyzing…</b>
            <span>Nebius AI is preparing the renewal journey for this subscriber</span>
            <div class="jx-bar"><i x-bind:style="'width:' + aiThinkingProgress + '%'"></i></div>
        </div>

        {{-- Timeline --}}
        <div x-show="journeyStep === 'ready' && journey.stage" x-cloak class="jx-tl" x-bind:class="'jx-tone-' + (journey.stage ? journey.stage.tone : 'calm')">
            <div class="jx-tl-top">
                <div>
                    <p class="jx-eyebrow">Timeline of the next charge</p>
                    <p class="jx-tl-title" x-text="journey.stage && journey.stage.label"></p>
                    <p class="jx-tl-sub" x-text="journey.stage && journey.stage.expires_at ? 'Current period ends ' + new Date(journey.stage.expires_at).toLocaleString() : ''"></p>
                </div>
                <div class="jx-tl-amt"><b x-text="journey.amount"></b><span x-text="'next charge · ' + (journey.stage ? journey.stage.renewal_status : '')"></span></div>
            </div>
            <ol class="jx-track" role="group" aria-label="Move along the timeline">
                <template x-for="o in timelineStations" x-bind:key="o.h">
                    <li>
                        <button type="button" class="jx-node" x-bind:class="stationState(o.h)" x-bind:title="o.long" x-bind:aria-label="o.long"
                                x-bind:aria-current="stationState(o.h) === 'is-on' ? 'step' : null"
                                x-on:click="setJourneyStage(o.h)" x-bind:disabled="decisionBusy || phoneView === 'done'">
                            <span class="jx-dot"></span><span x-text="o.l"></span>
                        </button>
                    </li>
                </template>
            </ol>
            <p class="jx-tl-msg" x-text="journey.stage && journey.stage.message"></p>
        </div>

        <div x-show="journeyStep === 'ready'" x-cloak class="jx-grid">
            {{-- Backend --}}
            <section aria-label="Backend logs">
                <p class="jx-col-t">Backend</p>
                <div class="jx-console" role="log" aria-live="polite" x-ref="journeyLog">
                    <template x-for="(l, i) in decisionLogs" x-bind:key="i">
                        <p x-bind:class="l.tone ? 't-' + l.tone : ''" x-text="l.text"></p>
                    </template>
                    <p x-show="decisionBusy" x-cloak>⏳ working…</p>
                </div>
            </section>

            {{-- The subscriber's phone --}}
            <section aria-label="Subscriber phone preview">
                <p class="jx-col-t">Subscriber's phone</p>
                <div class="jx-phone-wrap">
                    <div class="jx-device">
                        <div class="jx-screen" x-bind:class="'jx-tone-' + (journey.stage ? journey.stage.tone : 'calm')">
                            <div class="jx-island" aria-hidden="true"></div>
                            <div class="jx-status" aria-hidden="true">
                                <span>9:41</span>
                                <span style="display:flex;align-items:center">
                                    <svg viewBox="0 0 18 12"><rect x="0" y="8" width="3" height="4" rx="1"/><rect x="5" y="5" width="3" height="7" rx="1"/><rect x="10" y="2.5" width="3" height="9.5" rx="1"/><rect x="15" y="0" width="3" height="12" rx="1"/></svg>
                                    <svg viewBox="0 0 26 12"><rect x="0" y="0.5" width="22" height="11" rx="3.2" fill="none" stroke="#fff" stroke-width="1" opacity=".5"/><rect x="2" y="2.5" width="16" height="7" rx="1.8"/><rect x="23.2" y="4" width="1.8" height="4" rx=".9" opacity=".5"/></svg>
                                </span>
                            </div>
                            <div class="jx-app">
                                <header class="jx-app-h">
                                    <span class="jx-mono" x-text="String(journey.brand || 'L').charAt(0).toUpperCase()"></span>
                                    <div><p class="jx-brand" x-text="journey.brand"></p><p class="jx-sub" x-text="'Member · ' + journey.name"></p></div>
                                </header>
                                <div class="jx-seg" role="tablist">
                                    <button type="button" role="tab" x-bind:class="phoneTab === 'options' ? 'is-on' : ''" x-on:click="phoneTab = 'options'">Your plan</button>
                                    <button type="button" role="tab" x-bind:class="phoneTab === 'chat' ? 'is-on' : ''" x-on:click="phoneTab = 'chat'">AI Concierge</button>
                                </div>

                                <div class="jx-scroll">
                                    {{-- Plan --}}
                                    <div x-show="phoneTab === 'options' && phoneView === 'options'">
                                        <div class="jx-notif" x-data="{ open: false }" x-bind:class="open ? 'is-open' : ''" role="button" tabindex="0" x-bind:aria-expanded="open ? 'true' : 'false'"
                                             x-on:click="open = !open" x-on:keydown.enter.prevent="open = !open" title="Tap to read the whole message">
                                            <div class="jx-notif-top">
                                                <span class="jx-notif-ic" x-text="String(journey.brand || 'L').charAt(0).toUpperCase()"></span>
                                                <span class="jx-notif-app" x-text="journey.brand"></span>
                                                <span class="jx-notif-now">now</span>
                                                <span class="jx-notif-more" x-text="open ? 'Less' : 'More'"></span>
                                            </div>
                                            <h4 x-text="journey.stage && journey.stage.reminder ? journey.stage.reminder.subject : (journey.stage ? journey.stage.label : 'Loading your plan…')"></h4>
                                            <p x-text="journey.stage ? journey.stage.message : ''"></p>
                                            <div class="jx-notif-meta">
                                                <span class="jx-badge" x-bind:class="journey.stage && journey.stage.message_source === 'nebius' ? 'is-ai' : ''" x-text="journey.stage && journey.stage.message_source === 'nebius' ? '✦ Nebius AI' : 'Template'"></span>
                                                <span class="jx-badge" x-bind:class="reminderEmailText().ok ? 'is-ok' : ''" x-show="reminderEmailText().text" x-text="reminderEmailText().text"></span>
                                            </div>
                                        </div>

                                        <div class="jx-card">
                                            <div>
                                                <p class="jx-card-k" x-text="journey.stage && journey.stage.stage === 'grace_open' ? 'Needs attention' : 'Next charge'"></p>
                                                <p class="jx-card-amt" x-text="journey.amount"></p>
                                            </div>
                                            <div class="jx-card-r">
                                                <p class="jx-card-d" x-text="renewalLine()"></p>
                                                <span class="jx-pips" aria-hidden="true">
                                                    <template x-for="o in timelineStations" x-bind:key="o.h"><i x-bind:class="stationState(o.h)"></i></template>
                                                </span>
                                            </div>
                                        </div>

                                        <p class="jx-note" x-show="lockNote()" x-text="lockNote()"></p>
                                        <ul class="jx-opts">
                                            <template x-for="o in (journey.stage ? journey.stage.options : [])" x-bind:key="o.id">
                                                <li>
                                                    <button type="button" class="jx-opt" x-bind:class="o.id === 'renew' ? 'is-primary' : ''"
                                                            x-bind:title="o.available ? '' : o.reason"
                                                            x-on:click="choose(o.id === 'expire' ? 'cancel' : o.id)"
                                                            x-bind:disabled="!o.available || !decisionReady || decisionBusy">
                                                        <span class="jx-opt-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path x-bind:d="optIcon(o.id)"/></svg></span>
                                                        <span class="jx-opt-b">
                                                            <span class="jx-opt-t" x-text="o.label"></span>
                                                            <span class="jx-opt-h" x-text="o.available ? optHint(o) : (o.reason || '')"></span>
                                                        </span>
                                                        <span class="jx-opt-go" aria-hidden="true">›</span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>

                                    {{-- Concierge chat --}}
                                    <div x-show="phoneTab === 'chat' && phoneView !== 'done'" x-cloak class="jx-chat">
                                        <div class="jx-msgs">
                                            <template x-for="(m, i) in journeyChatMessages" x-bind:key="i">
                                                <div class="jx-m" x-bind:class="m.role === 'user' ? 'is-me' : 'is-ai'" x-text="m.content"></div>
                                            </template>
                                            <div class="jx-typing" x-show="journeyChatThinking">Concierge is typing…</div>
                                        </div>
                                        <div class="jx-quick">
                                            <button type="button" x-on:click="sendJourneyChat('[option:split_half] Pay half now, half next week')">Split</button>
                                            <button type="button" x-on:click="sendJourneyChat('[option:resale] Resale')">Resale</button>
                                            <button type="button" x-on:click="sendJourneyChat('[option:safe_refund] Safe refund')">Refund</button>
                                            <button type="button" x-on:click="sendJourneyChat('[option:pay_full] Pay full')">Pay full</button>
                                        </div>
                                        <form class="jx-form" x-on:submit.prevent="sendJourneyChat(journeyChatInput)">
                                            <input type="text" x-model="journeyChatInput" placeholder="Ask your concierge…" aria-label="Message the AI concierge" x-bind:disabled="journeyChatThinking">
                                            <button type="submit" x-bind:disabled="journeyChatThinking || !String(journeyChatInput || '').trim()">Send</button>
                                        </form>
                                    </div>

                                    {{-- Done --}}
                                    <div x-show="phoneView === 'done'" x-cloak class="jx-done">
                                        <div class="jx-ring" aria-hidden="true">✓</div>
                                        <h4>All set</h4>
                                        <p class="a" x-text="selectedAction"></p>
                                        <p class="m" x-text="journey.doneMessage"></p>
                                        <a x-show="journey.listingUrl" x-cloak x-bind:href="journey.listingUrl" target="_blank" rel="noopener">View on marketplace ↗</a>
                                    </div>
                                </div>
                                <p class="jx-foot">Secure · LuvuPay AI</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Host-side actions (same options, same rules) --}}
        <div x-show="journeyStep === 'ready'" x-cloak class="jx-foot-row">
            <p class="jx-foot-note" x-show="phoneView === 'options'">Pick what the subscriber chose, from the phone or from here. Both stay in sync.</p>
            <p class="jx-foot-note is-ok" x-show="phoneView === 'done'" x-cloak>✓ Completed. Ledger, KPIs and wallet are updated.</p>
            <div class="jx-pills">
                <template x-for="o in (journey.stage ? journey.stage.options : [])" x-bind:key="o.id">
                    <button type="button" class="jx-pill" x-bind:class="o.id === 'renew' ? 'is-gold' : ''" x-bind:title="o.available ? o.description : o.reason"
                            x-on:click="choose(o.id === 'expire' ? 'cancel' : o.id)"
                            x-bind:disabled="!o.available || !decisionReady || decisionBusy || phoneView === 'done'" x-text="o.label"></button>
                </template>
                <button type="button" class="jx-pill is-gold" x-show="journey.splitPlan && journey.splitPlan.status === 'active'" x-cloak
                        x-on:click="collectInstallment(decisionRow)" x-bind:disabled="decisionBusy">Subscriber pays next instalment now</button>
            </div>
        </div>
    </div>
</x-ui.modal>

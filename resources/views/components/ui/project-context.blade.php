@props(['project' => null])
@php
    $initialName = $project->name ?? '';
    $initialEnv  = (($project->environment ?? 'sandbox') === 'production') ? 'production' : 'sandbox';
    $hasInitial  = filled($initialName);
@endphp
<div
    id="project-context"
    data-name="{{ $initialName }}"
    data-env="{{ $initialEnv }}"
    data-has="{{ $hasInitial ? '1' : '0' }}"
    x-data="projectContext()"
    x-on:keydown.escape.window="if (openModal) close()"
>
    <section class="c-card pbar">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;min-width:0">
            <div style="min-width:0">
                <small>Project</small>
                <b x-text="hasProject ? currentName : 'No project yet'">{{ $hasInitial ? $initialName : 'No project yet' }}</b>
            </div>
            <template x-if="hasProject">
                <span class="tag dot" x-bind:class="currentEnv === 'sandbox' ? 'warn' : 'ok'" x-text="currentEnv === 'sandbox' ? 'Sandbox' : 'Production'"></span>
            </template>
        </div>
        <button type="button" class="c-btn c-btn-sm" x-on:click="openNew()"
                x-text="hasProject ? 'New project' : 'Create your first project'"></button>
    </section>

    <div x-show="toast" x-cloak style="display:none" class="toast-pop" role="status">Project created. API keys are ready in Settings.</div>

    <div x-show="openModal" x-cloak class="modal" style="display:none" role="dialog" aria-modal="true">
        <div class="modal-scrim" x-on:click="close()"></div>
        <div class="modal-box modal-md" x-on:click.stop
             x-show="openModal"
             x-transition:enter="m-enter" x-transition:enter-start="m-from" x-transition:enter-end="m-to">
            <div class="modal-head">
                <h3 x-text="hasProject ? 'New project' : 'Create your first project'"></h3>
                <button type="button" class="modal-x" x-on:click="close()" aria-label="Close">
                    <x-ui.icon name="x" :size="18" />
                </button>
            </div>
            <div class="modal-body" style="padding:20px 24px;display:grid;gap:16px">
                <div class="field">
                    <label class="label">Project name</label>
                    <div class="relative" x-on:click.outside="nameOpen = false">
                        <button type="button" class="input flex w-full items-center justify-between gap-2 text-left cursor-pointer"
                                style="appearance:none;-webkit-appearance:none;background-image:none"
                                x-on:click="nameOpen = !nameOpen; envOpen = false">
                            <span class="truncate" x-bind:style="!form.nameSelect ? 'color:var(--faint)' : ''" x-text="nameLabel"></span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.55"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="nameOpen" x-cloak style="display:none" class="dd-menu">
                            <template x-for="opt in nameOptions" :key="opt.value">
                                <button type="button" class="dd-item"
                                        x-bind:class="form.nameSelect === opt.value ? 'on' : ''"
                                        x-on:click="form.nameSelect = opt.value; nameOpen = false"
                                        x-text="opt.label"></button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="field" x-show="form.nameSelect === '__custom'" x-cloak style="display:none">
                    <label class="label" for="np-name-custom">Custom name</label>
                    <input id="np-name-custom" class="input" type="text" x-model="form.nameCustom" maxlength="80" placeholder="My product">
                </div>

                <div class="field">
                    <label class="label" for="np-desc">Description <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <textarea id="np-desc" class="input" x-model="form.description" rows="2" maxlength="240" placeholder="Premium streaming plan"></textarea>
                </div>

                <div class="field">
                    <label class="label">Environment</label>
                    <div class="relative" x-on:click.outside="envOpen = false">
                        <button type="button" class="input flex w-full items-center justify-between gap-2 text-left cursor-pointer"
                                style="appearance:none;-webkit-appearance:none;background-image:none"
                                x-on:click="envOpen = !envOpen; nameOpen = false">
                            <span class="truncate" x-text="envLabel"></span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;opacity:.55"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="envOpen" x-cloak style="display:none" class="dd-menu">
                            <template x-for="opt in envOptions" :key="opt.value">
                                <button type="button" class="dd-item"
                                        x-bind:class="form.env === opt.value ? 'on' : ''"
                                        x-on:click="form.env = opt.value; envOpen = false"
                                        x-text="opt.label"></button>
                            </template>
                        </div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 110px;gap:12px">
                    <div class="field" style="margin:0">
                        <label class="label" for="np-plan">Plan name</label>
                        <input id="np-plan" class="input" type="text" x-model="form.plan" maxlength="40" placeholder="Premium">
                    </div>
                    <div class="field" style="margin:0">
                        <label class="label" for="np-price">Price</label>
                        <input id="np-price" class="input" type="number" min="1" step="0.01" x-model.number="form.price" placeholder="20.00">
                    </div>
                    <div class="field" style="margin:0">
                        <label class="label" for="np-cur">Currency</label>
                        <select id="np-cur" class="input" x-model="form.currency"><option>USD</option><option>EUR</option><option>GBP</option><option>IDR</option><option>SGD</option></select>
                    </div>
                </div>

                <div class="field" style="display:grid;gap:10px;padding:12px;border:1px solid var(--line);border-radius:12px;background:var(--bg)">
                    <p class="label" style="margin:0">Retention settings <span style="font-weight:400;color:var(--muted)">(also editable in Settings)</span></p>
                    <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer">
                        <input type="checkbox" x-model="form.grace_period" style="margin-top:3px;accent-color:var(--brand)" />
                        <span><strong style="font-size:.9rem">Grace Period</strong><span style="display:block;font-size:.8rem;color:var(--muted)">Hold a missed-cancellation charge and let the user choose before it settles.</span></span>
                    </label>
                    <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer">
                        <input type="checkbox" x-model="form.marketplace_resale" style="margin-top:3px;accent-color:var(--brand)" />
                        <span><strong style="font-size:.9rem">Marketplace Resale</strong><span style="display:block;font-size:.8rem;color:var(--muted)">Let users sell remaining days after grace.</span></span>
                    </label>
                    <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer">
                        <input type="checkbox" x-model="form.charity_split" style="margin-top:3px;accent-color:var(--brand)" />
                        <span><strong style="font-size:.9rem">Charity Split</strong><span style="display:block;font-size:.8rem;color:var(--muted)">Let users donate residual value to charity.</span></span>
                    </label>
                    <div>
                        <label class="label" for="np-refund">Partial refund offer (%)</label>
                        <input id="np-refund" class="input" type="number" min="0" max="100" x-model.number="form.partial_refund_pct" />
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="c-btn" x-on:click="close()">Cancel</button>
                
                <button type="button" class="c-btn c-btn-brand" x-bind:disabled="!resolvedName || creating" x-on:click="createProject()">
                    <span x-text="creating ? 'Creating…' : 'Create project'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
<script src="/js/project-context.js?v={{ @filemtime(public_path('js/project-context.js')) ?: time() }}" defer></script>

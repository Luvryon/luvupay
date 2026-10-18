@props(['project'])
{{-- Merchant Project Context: every action on the dashboard is scoped to this project. Display-only data from MerchantProject. --}}
<section aria-label="Project context" class="mb-6 overflow-hidden rounded-2xl border border-brand/25 bg-gradient-to-r from-brand/15 via-surface/80 to-ok/10 shadow-xl shadow-black/20">
    <dl class="grid divide-y divide-edge sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        <div class="flex items-center gap-3 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-xl bg-brand/20 text-brand-soft"><x-ui.icon name="building" :size="19" /></span>
            <div class="min-w-0"><dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Managing project</dt>
                <dd class="truncate text-base font-bold text-white">{{ $project->name }}</dd></div>
        </div>
        <div class="flex items-center gap-3 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-xl bg-ok/15 text-ok"><x-ui.icon name="package" :size="19" /></span>
            <div class="min-w-0"><dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Active plan</dt>
                <dd class="truncate text-base font-bold text-white">{{ $project->plan }} Subscription <span class="font-medium text-muted">({{ $project->price() }}/{{ $project->interval === 'month' ? 'mo' : $project->interval }})</span></dd></div>
        </div>
        <div class="flex items-center gap-3 px-5 py-4">
            <span class="grid size-10 place-items-center rounded-xl bg-info/15 text-info"><x-ui.icon name="users" :size="19" /></span>
            <div class="min-w-0"><dt class="text-[11px] font-semibold uppercase tracking-wider text-muted">Active subscribers</dt>
                <dd class="text-base font-bold text-white tabular-nums">{{ number_format($project->activeSubscribers) }}</dd></div>
        </div>
    </dl>
</section>

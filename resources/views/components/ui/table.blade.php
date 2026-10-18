@props(['head' => null])
<div class="overflow-x-auto rounded-xl border border-edge">
    <table {{ $attributes->merge(['class' => 'w-full text-left text-sm']) }}>
        <thead class="bg-white/3 text-[11px] uppercase tracking-wider text-muted">{{ $head }}</thead>
        <tbody class="divide-y divide-edge">{{ $slot }}</tbody>
    </table>
</div>

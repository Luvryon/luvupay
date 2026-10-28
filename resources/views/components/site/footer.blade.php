{{-- Public site footer, shared by landing, docs, marketplace, status --}}
@php
    $columns = [
        'Product' => [['/#product', 'Second Chance'], ['/#product', 'Resale'], ['/directory', 'Directory'], ['/status', 'Status']],
        'Developers' => [['/docs', 'Docs'], ['/docs/api', 'API reference'], ['/docs/webhooks', 'Webhooks'], ['/docs/sandbox', 'PayPal sandbox']],
        'Company' => [['/support', 'Support'], ['/privacy', 'Privacy'], ['/terms', 'Terms'], ['/docs/security', 'Security']],
    ];
@endphp
<footer class="border-t border-line bg-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 text-sm sm:px-8 md:grid-cols-2 lg:grid-cols-[1.6fr_repeat(3,1fr)]">
        <div>
            <a href="/" class="flex items-center gap-2.5 font-semibold tracking-tight text-ink hover:text-ink">
                @include('partials.logo', ['size' => 28])
                LuvuPay
            </a>
            <p class="mt-4 max-w-xs leading-relaxed text-muted">Ethical subscription infrastructure. One API for Second Chance, resale and audit-ready money movement</p>
            <p class="mt-5 inline-flex items-center gap-2 text-xs text-muted">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                All systems normal
                <a href="/status" class="text-muted underline underline-offset-2 hover:text-ink">Status</a>
            </p>
        </div>

        @foreach($columns as $title => $links)
            <div>
                <div class="mb-3 font-semibold text-ink">{{ $title }}</div>
                <ul class="space-y-2.5">
                    @foreach($links as [$href, $label])
                        <li><a href="{{ $href }}" class="text-muted hover:text-ink">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="border-t border-line">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-xs text-muted sm:px-8">
            <span>© {{ date('Y') }} LuvuPay Sandbox environment, no real money moves</span>
            <span class="flex gap-5"><a href="/privacy" class="text-muted hover:text-ink">Privacy</a><a href="/terms" class="text-muted hover:text-ink">Terms</a><a href="/docs/security" class="text-muted hover:text-ink">Security</a></span>
        </div>
    </div>
</footer>

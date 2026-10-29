{{-- Public site footer, shared by landing, docs, marketplace, status --}}
@php
    $columns = [
        'Product' => [['/#product', 'Second Chance'], ['/#product', 'Resale'], ['/directory', 'Directory'], ['/status', 'Status']],
        'Developers' => [['/docs', 'Docs'], ['/docs/api', 'API reference'], ['/docs/webhooks', 'Webhooks'], ['/docs/sandbox', 'PayPal sandbox']],
        'Company' => [['/support', 'Support'], ['/privacy', 'Privacy'], ['/terms', 'Terms'], ['/docs/security', 'Security']],
    ];

    $socials = [
        ['label' => 'Instagram', 'href' => '#', 'path' => 'M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.9.3 2.3.4.6.2 1 .5 1.5 1 .4.4.7.9 1 1.5.2.4.4 1.1.4 2.3.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.3 1.9-.4 2.3-.2.6-.5 1-1 1.5-.4.4-.9.7-1.5 1-.4.2-1.1.4-2.3.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.9-.3-2.3-.4-.6-.2-1-.5-1.5-1-.4-.4-.7-.9-1-1.5-.2-.4-.4-1.1-.4-2.3C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.3-1.9.4-2.3.2-.6.5-1 1-1.5.4-.4.9-.7 1.5-1 .4-.2 1.1-.4 2.3-.4C8.4 2.2 8.8 2.2 12 2.2m0 1.8c-3.2 0-3.5 0-4.8.1-1.1 0-1.7.2-2.1.4-.5.2-.8.4-1.2.8-.4.4-.6.7-.8 1.2-.2.4-.3 1-.4 2.1-.1 1.2-.1 1.6-.1 4.8s0 3.5.1 4.8c0 1.1.2 1.7.4 2.1.2.5.4.8.8 1.2.4.4.7.6 1.2.8.4.2 1 .3 2.1.4 1.2.1 1.6.1 4.8.1s3.5 0 4.8-.1c1.1 0 1.7-.2 2.1-.4.5-.2.8-.4 1.2-.8.4-.4.6-.7.8-1.2.2-.4.3-1 .4-2.1.1-1.2.1-1.6.1-4.8s0-3.5-.1-4.8c0-1.1-.2-1.7-.4-2.1-.2-.5-.4-.8-.8-1.2-.4-.4-.7-.6-1.2-.8-.4-.2-1-.3-2.1-.4-1.3-.1-1.6-.1-4.8-.1zm0 3.1a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.8a3.2 3.2 0 1 0 0 6.4 3.2 3.2 0 0 0 0-6.4zm6.4-.9a1.2 1.2 0 1 1 0 2.3 1.2 1.2 0 0 1 0-2.3z'],
        ['label' => 'X', 'href' => '#', 'path' => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.727-8.835L1.254 2.25H8.08l4.263 5.686L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z'],
        ['label' => 'YouTube', 'href' => '#', 'path' => 'M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.75 15.5v-7l6.5 3.5-6.5 3.5z'],
        ['label' => 'LinkedIn', 'href' => '#', 'path' => 'M4.98 3.5C4.98 4.88 3.86 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8.5h4V23h-4V8.5zM8.5 8.5h3.8v2h.05c.53-1 1.82-2.05 3.75-2.05 4 0 4.75 2.65 4.75 6.1V23h-4v-6.6c0-1.55-.03-3.55-2.15-3.55-2.15 0-2.48 1.68-2.48 3.45V23h-4V8.5z'],
    ];
@endphp

<footer class="border-t border-line bg-white">
    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-10 px-5 py-14 text-sm sm:px-8 md:grid-cols-2 lg:grid-cols-[1.6fr_repeat(3,1fr)]">

        {{-- Brand: last on mobile, first on tablet and desktop --}}
        <div class="order-last md:order-none">
            <a href="/" class="flex items-center gap-2.5 font-semibold tracking-tight text-ink hover:text-ink">
                @include('partials.logo', ['size' => 28])
                LuvuPay
            </a>

            <p class="mt-4 max-w-xs leading-relaxed text-muted">
                Ethical subscription infrastructure. One API for Second Chance, resale and audit-ready money movement
            </p>

            <div class="mt-5 flex items-center gap-2.5">
                @foreach($socials as $s)
                    <a href="{{ $s['href'] }}"
                       aria-label="{{ $s['label'] }}"
                       class="grid size-9 place-items-center rounded-full border border-line text-muted transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                            <path d="{{ $s['path'] }}"/>
                        </svg>
                    </a>
                @endforeach
            </div>

            <p class="mt-5 inline-flex items-center gap-2 text-xs text-muted">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                All systems normal
                <a href="/status" class="text-muted underline underline-offset-2 hover:text-ink">Status</a>
            </p>
        </div>

        {{-- Product, Developers, Company: after brand on tablet/desktop --}}
        @foreach($columns as $title => $links)
            <div>
                <div class="mb-3 font-semibold text-ink">{{ $title }}</div>
                <ul class="space-y-2.5">
                    @foreach($links as [$href, $label])
                        <li>
                            <a href="{{ $href }}" class="text-muted hover:text-ink">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="border-t border-line">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-xs text-muted sm:px-8">
            <span>© {{ date('Y') }} LuvuPay Sandbox environment, no real money moves</span>
            <span class="flex gap-5">
                <a href="/privacy" class="text-muted hover:text-ink">Privacy</a>
                <a href="/terms" class="text-muted hover:text-ink">Terms</a>
                <a href="/docs/security" class="text-muted hover:text-ink">Security</a>
            </span>
        </div>
    </div>
</footer>

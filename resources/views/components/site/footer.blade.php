{{-- Public site footer — shared by welcome, docs, marketplace, status, etc. --}}
<footer class="border-t border-line bg-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 text-sm sm:grid-cols-2 sm:px-8 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <a href="/" class="flex items-center gap-2.5 font-semibold tracking-tight">
                @include('partials.logo', ['size' => 24])
                LuvuPay
            </a>
            <p class="mt-4 max-w-xs leading-relaxed text-muted">
                Infrastruktur langganan yang etis. Satu API untuk Second Chance, resale, dan pergerakan uang yang bisa diaudit.
            </p>
            <p class="mt-5 inline-flex items-center gap-2 text-xs text-muted">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                Semua sistem normal ·
                <a href="/status" class="underline underline-offset-2 hover:text-ink">status</a>
            </p>
        </div>

        @foreach([
            'Product' => [
                ['/#produk', 'Second Chance'],
                ['/#produk', 'Resale'],
                ['/directory', 'Directory'],
                ['/status', 'Status'],
            ],
            'Developers' => [
                ['/docs', 'Docs'],
                ['/docs/api', 'API reference'],
                ['/docs/webhooks', 'Webhooks'],
                ['/docs/sandbox', 'PayPal sandbox'],
            ],
            'Company' => [
                ['/support', 'Support'],
                ['/privacy', 'Privacy'],
                ['/terms', 'Terms'],
                ['/docs/security', 'Security'],
            ],
        ] as $title => $links)
            <div>
                <div class="mb-3 font-semibold text-ink">{{ $title }}</div>
                <ul class="space-y-2.5 text-muted">
                    @foreach($links as [$href, $label])
                        <li><a href="{{ $href }}" class="hover:text-ink">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="border-t border-line py-6 text-center text-xs text-muted">
        © {{ date('Y') }} LuvuPay. Sandbox environment — no real money moves.
    </div>
</footer>

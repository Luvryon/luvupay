{{-- Public site navbar, shared by landing, docs, marketplace, status --}}
@php
    $links = [
        ['/#product', 'Product'],
        ['/#developers', 'Developers'],
        ['/#security', 'Security'],
        ['/marketplace', 'Marketplace'],
        ['/docs', 'Docs'],
        ['/status', 'Status'],
    ];
@endphp
<header class="sticky top-0 z-50 border-b border-line bg-white/85 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8">
        <a href="/" class="flex items-center gap-2.5 text-[1.05rem] font-semibold tracking-tight text-ink hover:text-ink">
            @include('partials.logo', ['size' => 28])
            <span>LuvuPay</span>
        </a>

        <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary">
            @foreach($links as [$href, $label])
                <a href="{{ $href }}" class="lp-nav-link {{ ltrim($href, '/') !== '' && !str_contains($href, '#') && request()->is(ltrim($href, '/').'*') ? 'text-ink' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            @auth('merchant_web')
                <a href="{{ route('dashboard') }}" class="lp-btn lp-btn-dark">Open console</a>
            @else
                <a href="{{ route('login') }}" class="lp-btn hidden text-ink hover:bg-wash sm:inline-flex">Sign in</a>
                <a href="{{ route('register') }}" class="lp-btn lp-btn-dark">Start building <span aria-hidden="true">→</span></a>
            @endauth
            <button type="button" class="-mr-2 p-2 text-muted lg:hidden" @click="mobile = !mobile" aria-label="Menu" :aria-expanded="mobile">
                @include('partials.icon', ['name' => 'menu', 'size' => 22])
            </button>
        </div>
    </div>

    <div x-show="mobile" x-cloak class="grid gap-1 border-t border-line bg-white px-5 py-4 text-sm font-medium lg:hidden">
        @foreach($links as [$href, $label])
            <a href="{{ $href }}" class="rounded-lg px-3 py-2 text-muted hover:bg-wash hover:text-ink" @click="mobile = false">{{ $label }}</a>
        @endforeach
        <a href="/support" class="rounded-lg px-3 py-2 text-muted hover:bg-wash hover:text-ink">Support</a>
    </div>
</header>

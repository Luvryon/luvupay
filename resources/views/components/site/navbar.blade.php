{{-- Public site navbar — shared by welcome, docs, marketplace, status, etc. --}}
<header class="sticky top-0 z-50 border-b border-line bg-white/80 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8">
        <a href="/" class="flex items-center gap-2.5 text-[1.05rem] font-semibold tracking-tight">
            @include('partials.logo', ['size' => 28])
            <img src="{{ asset('images/logo-luvupay-v2.png') }}" alt="" width="28" height="28" style="width:28px;height:28px;object-fit:contain" /><span>LuvuPay</span>
        </a>

        <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary">
            <a href="/#produk" class="lp-nav-link">Product</a>
            <a href="/#developer" class="lp-nav-link">Developers</a>
            <a href="/#keamanan" class="lp-nav-link">Security</a>
            <a href="/marketplace" class="lp-nav-link {{ request()->is('marketplace') ? 'text-ink' : '' }}">Marketplace</a>
            <a href="/docs" class="lp-nav-link {{ request()->is('docs') || request()->is('docs/*') ? 'text-ink' : '' }}">Docs</a>
            <a href="/status" class="lp-nav-link {{ request()->is('status') ? 'text-ink' : '' }}">Status</a>
        </nav>

        <div class="flex items-center gap-2">
            @auth('merchant_web')
                <a href="{{ route('dashboard') }}" class="lp-btn lp-btn-dark">Open console</a>
            @else
                <a href="{{ route('login') }}" class="lp-btn hidden text-ink hover:bg-wash sm:inline-flex">Sign in</a>
                <a href="{{ route('register') }}" class="lp-btn lp-btn-dark">Start building</a>
            @endauth
            <button type="button"
                    class="-mr-2 p-2 text-muted lg:hidden"
                    @click="mobile = !mobile"
                    aria-label="Menu"
                    :aria-expanded="mobile">
                @include('partials.icon', ['name' => 'menu', 'size' => 22])
            </button>
        </div>
    </div>

    <div x-show="mobile" x-cloak class="grid gap-1 border-t border-line bg-white px-5 py-4 text-sm font-medium lg:hidden">
        @foreach([
            ['/#produk', 'Product'],
            ['/#developer', 'Developers'],
            ['/#keamanan', 'Security'],
            ['/marketplace', 'Marketplace'],
            ['/docs', 'Docs'],
            ['/status', 'Status'],
            ['/support', 'Support'],
        ] as [$href, $label])
            <a href="{{ $href }}"
               class="rounded-lg px-3 py-2 text-muted hover:bg-wash hover:text-ink"
               @click="mobile = false">{{ $label }}</a>
        @endforeach
    </div>
</header>

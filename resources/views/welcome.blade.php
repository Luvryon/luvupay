{{-- =====================================================================
     PATCH 1: HERO BACKGROUND
     Cari: <div class="hx-decor" ...> ... </div>  (di dalam .lp-stage)
     Ganti seluruh <svg> di dalamnya dengan yang ini.
     Ide: wajah jam "48 jam" dengan busur waktu + tiga titik pembagian dana
     (pelanggan, donasi, merchant). Sesuai produknya, tanpa grid/kotak miring.
     ===================================================================== --}}
<svg class="h-full w-full" viewBox="0 0 700 600" preserveAspectRatio="xMidYMid slice" fill="none">
    <defs>
        <linearGradient id="hx-fadeL" x1="0" x2="1" y1="0" y2="0">
            <stop offset="0" stop-color="#fff" stop-opacity="0"/>
            <stop offset=".45" stop-color="#fff" stop-opacity="1"/>
            <stop offset="1" stop-color="#fff" stop-opacity="1"/>
        </linearGradient>
        <mask id="hx-mask"><rect width="700" height="600" fill="url(#hx-fadeL)"/></mask>
    </defs>

    <g mask="url(#hx-mask)">
        {{-- alas lembut --}}
        <circle cx="470" cy="300" r="250" fill="#09027b" fill-opacity=".035"/>

        {{-- tick menit (60) dan tick jam (12), dibuat dari dasharray --}}
        <circle cx="470" cy="300" r="262" stroke="#09027b" stroke-opacity=".28" stroke-width="12" stroke-dasharray="1.5 25.3"/>
        <circle cx="470" cy="300" r="262" stroke="#09027b" stroke-opacity=".55" stroke-width="22" stroke-dasharray="2.5 134.8" transform="rotate(-90 470 300)"/>

        {{-- cincin dalam --}}
        <circle cx="470" cy="300" r="205" stroke="#09027b" stroke-opacity=".16"/>
        <circle cx="470" cy="300" r="140" stroke="#09027b" stroke-opacity=".22" stroke-dasharray="2 7"/>

        {{-- busur waktu yang sudah berjalan --}}
        <circle cx="470" cy="300" r="205" stroke="#09027b" stroke-width="2.5" stroke-linecap="round"
                stroke-dasharray="800 1288" transform="rotate(-90 470 300)"/>

        {{-- tiga penerima dana --}}
        <g>
            <circle cx="330" cy="449" r="9" fill="#fff" stroke="#09027b" stroke-width="2"/>
            <circle cx="330" cy="449" r="3.5" fill="#09027b"/>

            <circle cx="573" cy="122" r="9" fill="#fff" stroke="#f59e0b" stroke-width="2"/>
            <circle cx="573" cy="122" r="3.5" fill="#f59e0b"/>

            <circle cx="663" cy="370" r="9" fill="#fff" stroke="#10b981" stroke-width="2"/>
            <circle cx="663" cy="370" r="3.5" fill="#10b981"/>
        </g>

        {{-- garis yang membagi ke tiga titik --}}
        <path d="M470 300 L330 449" stroke="#09027b" stroke-opacity=".3" stroke-dasharray="3 5"/>
        <path d="M470 300 L573 122" stroke="#f59e0b" stroke-opacity=".5" stroke-dasharray="3 5"/>
        <path d="M470 300 L663 370" stroke="#10b981" stroke-opacity=".5" stroke-dasharray="3 5"/>
        <circle cx="470" cy="300" r="5" fill="#09027b"/>
    </g>
</svg>


{{-- =====================================================================
     PATCH 2: SECTION "READY TO START"
     Cari: <div class="lp-cta-visual ..."> ... </div>
     Ganti seluruh blok itu (termasuk div dekor miring dan <img>) dengan ini.
     Ide: kartu langganan dengan hitung mundur 48 jam + kartu pembagian dana,
     jadi yang terlihat adalah produknya sendiri.
     ===================================================================== --}}
<div class="lp-cta-visual relative mx-auto w-full max-w-md lg:max-w-none" aria-hidden="true">
    <svg viewBox="0 0 520 430" class="w-full" fill="none" font-family="inherit">
        <defs>
            <filter id="cta-sh" x="-20%" y="-20%" width="140%" height="150%">
                <feDropShadow dx="0" dy="12" stdDeviation="14" flood-color="#09027b" flood-opacity=".12"/>
            </filter>
        </defs>

        {{-- jam di belakang kartu --}}
        <circle cx="400" cy="150" r="150" fill="#09027b" fill-opacity=".04"/>
        <circle cx="400" cy="150" r="150" stroke="#09027b" stroke-opacity=".3" stroke-width="10" stroke-dasharray="1.5 18.1"/>
        <circle cx="400" cy="150" r="150" stroke="#09027b" stroke-opacity=".55" stroke-width="18" stroke-dasharray="2.5 77.5" transform="rotate(-90 400 150)"/>
        <circle cx="400" cy="150" r="108" stroke="#09027b" stroke-opacity=".2" stroke-dasharray="2 7"/>

        {{-- kartu langganan --}}
        <g filter="url(#cta-sh)">
            <rect x="40" y="60" width="300" height="180" rx="20" fill="#fff" stroke="#e8eaf3"/>
        </g>
        <circle cx="68" cy="90" r="9" fill="#09027b"/>
        <path d="M64.5 90.5l2.5 2.5 4.5-5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        <text x="86" y="94" font-size="13" font-weight="600" fill="#0f172a">Pro plan</text>
        <rect x="266" y="80" width="50" height="20" rx="10" fill="#fef3c7"/>
        <text x="291" y="94" font-size="10" font-weight="600" fill="#b45309" text-anchor="middle">Held</text>

        <text x="64" y="132" font-size="11" fill="#64748b">Second Chance closes in</text>
        <text x="64" y="162" font-size="30" font-weight="700" fill="#0f172a" font-family="ui-monospace,SFMono-Regular,Menlo,monospace" letter-spacing="-1">47:52:10</text>
        <rect x="64" y="176" width="252" height="5" rx="2.5" fill="#eef0f6"/>
        <rect x="64" y="176" width="236" height="5" rx="2.5" fill="#09027b"/>
        <rect x="64" y="196" width="150" height="30" rx="15" fill="#09027b"/>
        <text x="139" y="215" font-size="11.5" font-weight="600" fill="#fff" text-anchor="middle">Use Second Chance</text>

        {{-- segel berhasil di pojok kartu --}}
        <circle cx="340" cy="62" r="16" fill="#10b981" stroke="#fff" stroke-width="3"/>
        <path d="M333 62l5 5 9-10" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

        {{-- kartu pembagian dana --}}
        <g filter="url(#cta-sh)">
            <rect x="190" y="256" width="290" height="148" rx="20" fill="#fff" stroke="#e8eaf3"/>
        </g>
        <text x="212" y="286" font-size="12" font-weight="600" fill="#0f172a">Money split</text>
        <text x="458" y="286" font-size="12" font-weight="600" fill="#0f172a" text-anchor="end" font-family="ui-monospace,SFMono-Regular,Menlo,monospace">$12.00</text>

        <g font-size="11" fill="#64748b">
            <text x="212" y="314">Customer</text>
            <text x="212" y="344">Charity</text>
            <text x="212" y="374">Merchant</text>
        </g>
        <g fill="#eef0f6">
            <rect x="282" y="308" width="130" height="7" rx="3.5"/>
            <rect x="282" y="338" width="130" height="7" rx="3.5"/>
            <rect x="282" y="368" width="130" height="7" rx="3.5"/>
        </g>
        <rect x="282" y="308" width="78" height="7" rx="3.5" fill="#09027b"/>
        <rect x="282" y="338" width="13" height="7" rx="3.5" fill="#f59e0b"/>
        <rect x="282" y="368" width="39" height="7" rx="3.5" fill="#10b981"/>
        <g font-size="11" font-weight="600" fill="#0f172a" text-anchor="end">
            <text x="458" y="314">60%</text>
            <text x="458" y="344">10%</text>
            <text x="458" y="374">30%</text>
        </g>
    </svg>
</div>

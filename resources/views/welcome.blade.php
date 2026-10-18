@extends('layouts.site')
@section('title', 'LuvuPay — Infrastruktur langganan yang etis')
@section('content')

{{-- ============================== HERO ============================== --}}
<section class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-[640px] lp-grid"></div>
    <div class="pointer-events-none absolute left-1/2 top-[-160px] h-[460px] w-[820px] -translate-x-1/2 lp-glow"></div>

    <div class="relative mx-auto max-w-7xl px-5 sm:px-8 pt-20 sm:pt-28 text-center">
        <a href="/docs/sandbox" class="inline-flex items-center gap-2 rounded-full border border-line bg-white/80 px-3.5 py-1.5 text-sm text-muted shadow-sm hover:border-brand-200">
            <span class="size-1.5 rounded-full bg-emerald-500"></span> Siap dipakai di PayPal Sandbox hari ini
        </a>
        <h1 class="mx-auto mt-7 max-w-3xl text-[2.6rem] sm:text-6xl font-semibold tracking-[-0.035em] leading-[1.05]">Langganan yang memberi pelanggan kesempatan kedua.</h1>
        <p class="mx-auto mt-6 max-w-xl text-lg text-muted leading-relaxed">Satu API untuk Second Chance 48 jam, resale sisa akses, dan pergerakan uang yang bisa diaudit sampai satuan sen.</p>
        <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ route('register') }}" class="lp-btn lp-btn-primary lp-btn-lg">Mulai membangun</a>
            <a href="/docs" class="lp-btn lp-btn-outline lp-btn-lg">Baca dokumentasi</a>
        </div>
    </div>

    {{-- the product, not a stock illustration: a charge inside its Second Chance window --}}
    <div class="relative mx-auto mt-16 max-w-4xl px-5 sm:px-8" x-data="{ left: 47*3600+12*60, t:null }" x-init="t=setInterval(()=>{ if(left>0) left-- },1000)">
        <div class="lp-card overflow-hidden shadow-[0_30px_80px_-30px_rgb(46_26_140/0.35)]">
            <div class="flex items-center gap-2 border-b border-line bg-wash px-4 py-3">
                <span class="size-2.5 rounded-full bg-[#ff5f57]"></span><span class="size-2.5 rounded-full bg-[#febc2e]"></span><span class="size-2.5 rounded-full bg-[#28c840]"></span>
                <span class="ml-3 rounded-md bg-white border border-line px-3 py-0.5 text-xs text-muted font-mono">console.luvupay.test / charges / ch_8Q2K</span>
            </div>
            <div class="grid md:grid-cols-[1.1fr_1fr] text-left">
                <div class="p-6 sm:p-8 md:border-r border-line">
                    <div class="flex items-center gap-2"><span class="lp-badge lp-badge-warn">Held</span><span class="text-sm text-muted">Pro plan · bulan ini</span></div>
                    <div class="mt-3 text-4xl font-semibold tracking-tight">$20.00</div>
                    <p class="mt-2 text-sm text-muted">Lupa membatalkan? Pelanggan punya waktu untuk menarik kembali sebelum uang jadi milik merchant.</p>
                    <div class="mt-6 rounded-xl border border-line bg-wash p-4">
                        <div class="text-xs text-muted">Jendela Second Chance berakhir dalam</div>
                        <div class="mt-1 font-mono text-2xl tabular-nums" x-text="String(Math.floor(left/3600)).padStart(2,'0')+':'+String(Math.floor(left%3600/60)).padStart(2,'0')+':'+String(left%60).padStart(2,'0')">47:12:00</div>
                    </div>
                    <button type="button" class="mt-5 lp-btn lp-btn-dark w-full">Gunakan Second Chance</button>
                </div>
                <div class="p-6 sm:p-8 bg-white">
                    <div class="text-sm font-semibold">Jika dipakai, uang dibagi otomatis</div>
                    <div class="mt-4 flex h-3 overflow-hidden rounded-full"><span class="bg-brand-600" style="width:30%"></span><span class="bg-emerald-500" style="width:20%"></span><span class="bg-ink" style="width:50%"></span></div>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex items-center justify-between"><dt class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-brand-600"></span>Refund ke pelanggan</dt><dd class="font-mono">$6.00</dd></div>
                        <div class="flex items-center justify-between"><dt class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-emerald-500"></span>Donasi ke charity</dt><dd class="font-mono">$4.00</dd></div>
                        <div class="flex items-center justify-between"><dt class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-ink"></span>Merchant</dt><dd class="font-mono">$10.00</dd></div>
                    </dl>
                    <div class="mt-6 rounded-xl border border-line p-3 text-xs text-muted font-mono leading-relaxed">journal split:ch_8Q2K<br>Dr merchant_held&nbsp;&nbsp;&nbsp;20.00<br>&nbsp;&nbsp;Cr user_refund&nbsp;&nbsp;&nbsp;&nbsp;6.00<br>&nbsp;&nbsp;Cr charity&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4.00<br>&nbsp;&nbsp;Cr merchant&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;10.00</div>
                </div>
            </div>
            <div class="mt-4"><a href="/marketplace" class="text-sm font-semibold text-amber-300 hover:text-amber-200">Open public marketplace →</a></div>
        </div>
    </div>

    <div class="relative mx-auto max-w-4xl px-5 sm:px-8 mt-14 pb-20 text-center">
        <p class="text-sm text-muted">Berjalan di atas infrastruktur yang sudah Anda percaya</p>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-[1.05rem] font-semibold tracking-tight text-[#9a9eae]">
            <span>PayPal</span><span>Laravel</span><span>PostgreSQL</span><span>Redis</span><span>FrankenPHP</span>
        </div>
    </div>
</section>

{{-- ============================== PRODUCT ============================== --}}
<section id="produk" class="border-t border-line bg-wash py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="max-w-2xl">
            <h2 class="text-3xl sm:text-4xl font-semibold tracking-[-0.03em] leading-tight">Tiga masalah langganan, satu lapisan infrastruktur.</h2>
            <p class="mt-4 text-muted text-lg leading-relaxed">Pelanggan lupa membatalkan, akses yang sudah dibayar terbuang, dan merchant buta soal perilaku. LuvuPay menyelesaikannya tanpa Anda membangun mesin keuangan sendiri.</p>
        </div>
        <div class="mt-14 grid gap-5 md:grid-cols-3">
            @foreach([
                ['Second Chance 48 jam','Setiap tagihan berulang ditahan selama jendela waktu. Satu klik membagi uang ke pelanggan, charity, dan merchant — sesuai basis poin yang Anda atur.'],
                ['Resale sisa akses','Pelanggan bisa menjual sisa siklus ke pengguna lain. Akses berpindah lewat Entitlement Bridge, uang lewat ledger — tanpa refund palsu.'],
                ['Adaptive Nudge','Pengingat 72 jam sebelum perpanjangan, plus skor kepercayaan agar Second Chance tidak disalahgunakan berulang kali.'],
            ] as [$t,$d])
                <div class="lp-card lp-card-pad">
                    <h3 class="font-semibold text-lg tracking-tight">{{ $t }}</h3>
                    <p class="mt-2 text-sm text-muted leading-relaxed">{{ $d }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-14 rounded-2xl border border-amber-500/20 bg-gradient-to-br from-slate-950 to-slate-900 p-6 sm:p-8 text-white">
            <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-400">Nebius · NVIDIA Nemotron · PayPal Sandbox</div>
            <h3 class="mt-2 text-2xl font-black tracking-tight">AI Smart-Convert Engine</h3>
            <p class="mt-2 text-sm text-slate-300 max-w-2xl leading-relaxed">
                Instead of a painful cash refund, LuvuPay converts unused subscription time into
                <strong class="text-emerald-300">merchant store credit</strong>,
                <strong class="text-violet-300">matched charity</strong>, and a small platform fee —
                chargebacks prevented, revenue retained, users still whole.
            </p>
            <div class="mt-5 grid sm:grid-cols-3 gap-3 text-sm">
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <div class="text-emerald-300 font-bold">User</div>
                    <p class="mt-1 text-slate-400 text-xs">Keeps value as store credit — no angry dispute.</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <div class="text-amber-300 font-bold">Merchant</div>
                    <p class="mt-1 text-slate-400 text-xs">Money stays in-ecosystem; chargeback risk collapses.</p>
                </div>
                <div class="rounded-xl bg-white/5 border border-white/10 p-4">
                    <div class="text-violet-300 font-bold">LuvuPay</div>
                    <p class="mt-1 text-slate-400 text-xs">Success fee + ethical infrastructure that scales.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================== DEVELOPER ============================== --}}
<section id="developer" class="py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8 grid lg:grid-cols-2 gap-14 items-center">
        <div>
            <h2 class="text-3xl sm:text-4xl font-semibold tracking-[-0.03em] leading-tight">API yang aman diulang. Webhook yang tidak pernah hilang.</h2>
            <ul class="mt-8 space-y-5">
                @foreach([
                    ['Idempotency-Key di setiap request uang','Kirim ulang dengan aman. Body berbeda dengan key yang sama ditolak, bukan dijalankan diam-diam.'],
                    ['Webhook inbox yang tahan banting','Diverifikasi, disimpan, baru diproses. Gagal? Retry berjenjang, dead-letter, lalu replay satu perintah.'],
                    ['Scope per API key & rate limit per merchant','Beri key hanya akses yang ia butuhkan.'],
                ] as [$t,$d])
                    <li class="flex gap-3"><span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700">@include('partials.icon', ['name' => 'check', 'size' => 14])</span>
                        <div><div class="font-semibold">{{ $t }}</div><p class="text-sm text-muted mt-0.5 leading-relaxed">{{ $d }}</p></div></li>
                @endforeach
            </ul>
            <div class="mt-8"><a href="/docs/api" class="lp-btn lp-btn-outline">Lihat referensi API</a></div>
        </div>
<pre class="lp-code text-[0.78rem]"><span class="c"># Buat langganan — aman dikirim ulang</span>
<span class="k">curl</span> -X POST https://api.luvupay.test/api/v1/subscriptions \
  -H <span class="s">"Authorization: Bearer lp_live_…"</span> \
  -H <span class="s">"Idempotency-Key: 9f1c7a52-order-1042"</span> \
  -d <span class="s">'{"plan_id":"P-PRO","email":"ana@acme.com"}'</span>

<span class="c"># → 201 Created</span>
{
  <span class="n">"id"</span>: <span class="s">"sub_01J8…"</span>,
  <span class="n">"status"</span>: <span class="s">"approval_pending"</span>,
  <span class="n">"approval_url"</span>: <span class="s">"https://www.sandbox.paypal.com/…"</span>
}

<span class="c"># Kirim ulang dengan key yang sama</span>
<span class="c"># → 201, header Idempotent-Replayed: true</span></pre>
    </div>
</section>

{{-- ============================== SECURITY ============================== --}}
<section id="keamanan" class="border-t border-line bg-wash py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="max-w-2xl">
            <h2 class="text-3xl sm:text-4xl font-semibold tracking-[-0.03em] leading-tight">Dibangun untuk diaudit, bukan sekadar berjalan.</h2>
            <p class="mt-4 text-muted text-lg leading-relaxed">Kontrol yang biasanya baru ditambahkan setelah insiden — sudah ada sejak hari pertama, bahkan di sandbox.</p>
        </div>
        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                ['Ledger double-entry','Append-only. Setiap jurnal seimbang, tidak bisa diubah, dan direkonsiliasi tiap jam.'],
                ['Audit berantai HMAC','Setiap perubahan ditandatangani berantai. Manipulasi terdeteksi oleh audit:verify.'],
                ['MFA, peran, dan tim','TOTP dengan recovery code. Owner, Admin, Finance, Developer, Viewer.'],
                ['Rekonsiliasi PayPal','Payout yang menggantung dicek langsung ke PayPal dan diselesaikan otomatis.'],
            ] as [$t,$d])
                <div class="lp-card lp-card-pad"><h3 class="font-semibold tracking-tight">{{ $t }}</h3><p class="mt-2 text-sm text-muted leading-relaxed">{{ $d }}</p></div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============================== CTA ============================== --}}
<section class="py-24">
    <div class="mx-auto max-w-4xl px-5 sm:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-ink px-8 py-16 text-center text-white">
            <div class="pointer-events-none absolute left-1/2 top-[-140px] h-[320px] w-[620px] -translate-x-1/2 rounded-full bg-brand-600/50 blur-3xl"></div>
            <h2 class="relative text-3xl sm:text-4xl font-semibold tracking-[-0.03em]">Mulai di sandbox. Go live saat siap.</h2>
            <p class="relative mx-auto mt-4 max-w-md text-white/70">Tidak ada uang sungguhan yang bergerak sampai Anda memutuskannya.</p>
            <div class="relative mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="lp-btn lp-btn-lg bg-white text-ink hover:bg-brand-50">Buat akun merchant</a>
                <a href="/docs/sandbox" class="lp-btn lp-btn-lg border border-white/25 text-white hover:bg-white/10">Panduan sandbox</a>
            </div>
        </div>
    </div>
</section>
@endsection

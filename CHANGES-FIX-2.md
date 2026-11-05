# Fix round 2

## PayPal "still confirming — hang tight" then "failed"
Root causes: (1) every non-fatal PayPal error was disguised as "still confirming", polled ~25 times, then shown as a
failure; (2) when the popup merely *looked* closed (COOP makes `window.closed` unreliable) the client auto-discarded
the pending checkout, so a payment finished in another tab later "failed".
- Server: errors are classified (`classifyProviderError`): declined / expired / bad credentials / buyer-not-approved /
  already captured / transient. Only definitive answers fail; network, 429 and 5xx stay "processing". The real
  PayPal reason + debug id is shown instead of a generic message. Failed capture re-reads PayPal before answering.
- Client: nothing is discarded automatically any more. Pending rows poll in the background with back-off and show
  Check again / Reopen PayPal / Cancel checkout. Error toasts were green (`'err'` was not a known tone), fixed.

## UI
- "Approve and send": `.c-btn{color:var(--ink)!important}` beat `text-white`; new `.c-btn-cta` forces white text.

## Split pay (new, end to end)
`payment_plans` table, `SplitPayService` (quote, down payment, ordered idempotent instalments, default, sweep),
journey option + "Collect next instalment" button, grid labels, metrics, scheduler. Parts always sum to the charge.
Run `php artisan migrate`.

## Money / logic bugs fixed
- Resale platform fee was counted twice and shown as merchant revenue; now fee = sale - seller payout, never in wallet.
- Fee rows were created at *listing* time (before any sale). Removed.
- Manual resale was not counted as "Resale" (label mismatch); duplicate listings could be sold twice. One shared
  `ResaleSettlement` is now used by sandbox and public redeem, compare-and-set so only one buyer wins.
- Resale asking price is prorated for the remaining access (was a hard-coded 50%).
- Charity donated 100% of the charge; now `charity_bps` (default 20%) and merchant keeps the rest. Resale no longer adds a charity leg.
- Partial refund / donate left the subscription *active*; now cancelled.
- A charge could be decided repeatedly (double refund/donation). `GraceDecisionService` locks the row and only decides HELD charges.
- Nothing ever closed expired grace windows or listings: `luvupay:sweep` (every 5 min).
- Wallet no longer hides a negative figure (`wallet_raw_minor`, `wallet_inconsistent`); tests recompute the wallet independently from rows.
- Public redeem: if PayPal captured but delivery failed, the capture is refunded automatically.

## Security
- Withdrawals: per-merchant lock + reservation (no double-spend), merchant currency (was hard-coded USD), failed payout releases the reservation.
- Removed the free-form `charity_donation` payout API (could be replayed to drain the platform PayPal account).
- RBAC: payouts, project settings and all simulator/money POSTs now need `payouts.write` / `billing.write` (viewer/developer got 403).
- `/pay/return` and `/pay/cancel` throttled.

## AJAX navigation
`public/js/ajax-nav.js`: sidebar links swap `<main>` in place (no reload, hover prefetch, back/forward, safe fallback to a normal load).
Settings/docs pages that need one-time wiring still load normally.

## Not done / needs your decision
- No live PayPal calls were possible here: the PayPal flow is covered by unit tests of the classifier, not a real sandbox run.
- Split-pay instalments in PayPal mode are simulated like the other sandbox decisions (no subscriber-facing pay page / reminder emails yet).
- `charity_bps` has an API field but no input in Settings yet.
- AJAX navigation was not exercised in a real browser here; please click through Overview / Wallet / Analytics / Subscriptions once.

## Missed cancellation journey: untuk LANGGANAN BERIKUTNYA (koreksi besar)
Sebelumnya journey mengubah charge yang SUDAH dibayar (periode berjalan) menjadi "held", sehingga wallet merchant turun
dan hitungannya salah. Sekarang:
- Periode berjalan tetap lunas dan tetap di wallet. Journey membuat baris terpisah: charge renewal (periode berikutnya),
  status `pending` sebelum expire, `held` setelah expire.
- Posisi waktu = relatif terhadap expire T (`subscriptions.current_period_end`). Sandbox menggeser T, sehingga
  scheduler, aturan tahap dan harga resale ikut konsisten.
- Tahap: 7 hari / 3 hari / 48 jam / 6 jam sebelum expire = reminder saja (email + notifikasi phone, ditulis Nebius AI,
  fallback template). Setelah expire = pembayaran gagal, opsi masih terkunci. +1 hari setelah expire = SEMUA opsi aktif
  (Retry payment, Split pay, Partial refund, Resell remaining, Donate residual, Let it expire). Setelah +2 hari = window tutup, churn.
- Aturan tahap dipaksa di server. Email: satu kali per tahap, batas per jam per merchant, alamat demo palsu dilewati.
- Retry/split memperpanjang langganan satu periode; partial refund/donasi membatalkan; resale menjual akses periode berikutnya.
- Scheduler (`luvupay:sweep`) menjalankan waktu sungguhan: renewal yang jatuh tempo masuk grace dan reminder terkirim sendiri.
- Bug ditemukan lewat tes: resale memilih "charge terbaru" lewat timestamp, bisa salah jika dua charge dibuat di detik yang sama. Diperbaiki.
- PayPal "checkout expired": HTTP 404 dari PayPal sandbox untuk checkout yang baru dibuat sekarang dianggap jeda (dicoba lagi),
  baru dianggap kedaluwarsa setelah 20 menit.
- Baris cicilan sekarang berlabel "Instalment 1 of 2 (paid)", bukan "Split pay (instalments)".

## Ronde berikutnya (tombol journey, phone, toast, aksi di tahap reminder)
- Tombol "Missed cancellation journey": sebelumnya mati setelah ada baris yang dipilih karena membandingkan
  `selectedLifecycle !== 'ACTIVE'` (label sudah dinormalisasi, tidak pernah sama), jadi baru aktif setelah reload.
  Sekarang aktif secara reaktif dari baris yang ada (`journeyEligibleRows`), menampilkan "Loading…" saat data dimuat.
- Tahap reminder (7d/3d/48h/6h) sekarang bisa diklik di phone: Pay now, Split pay, Cancel renewal (data ledger,
  wallet, langganan ikut berubah). Refund/resale/donasi tetap terkunci sampai +1 hari setelah expire.
- Phone didesain ulang (frame titanium, dynamic island, notifikasi ala lock screen yang bisa dibuka, kartu "Next charge",
  opsi dari data server, AI Concierge). CSS sendiri di `public/css/journey-luxe.css`: class Tailwind yang sebelumnya dipakai
  (mis. border-amber-300, border-indigo-600) memang tidak ada di CSS hasil build sehingga tampilan "biasa".
- Simpan/buat project sekarang memakai toast (sebelumnya teks inline). Error validasi juga toast.
- Log webhook di panel Backend tidak lagi berwarna merah (merah hanya untuk error).
- Diuji di browser asli (headless Chromium) + jsdom: alur journey, chip timeline, opsi phone, navigasi AJAX antar halaman
  (dokumen tidak reload, tombol back jalan), toast project.

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

## Missed cancellation journey: pilih user + pilih sisa waktu (tambahan)
- Langkah 1 modal: pilih subscriber dan sisa waktu (7 hari / 3 hari / 48 jam / 6 jam), lalu "Start simulation".
- Tahap dihitung dari sisa waktu grace (`GraceStagePlanner`, aturan di `config/luvupay.php` -> `grace_stages`):
  - 7 hari: bayar penuh, split pay, expire
  - 3 hari: semua opsi
  - 48 jam: semua opsi, nada mendesak
  - 6 jam: bayar penuh, split pay, expire (resale/refund/donasi tutup)
  - expired: otomatis churn
- Bisa pindah tahap di tengah simulasi (`POST demo/grace/stage`): deadline baris sandbox itu benar-benar digeser,
  jadi sweeper, aturan tahap dan harga resale ikut konsisten. Listing resale ditarik otomatis jika tahap baru menutup resale.
- Aturan tahap dipaksa di server (`GraceDecisionService`), bukan hanya disembunyikan di UI.
- Angka palsu di log journey ("$50, fee $2.50, $25 now") diganti angka nyata dari tagihan.

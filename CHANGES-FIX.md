# LuvuPay fix pack (24 Oct 2026)

## Deploy
1. Upload everything, including the NEW folder `public/assets/ag-grid-32.3.3/` (moved from `public/vendor/`, which many hosts block or exclude → the 404 + MIME errors).
2. `composer install --no-dev -o`, then `php artisan migrate --force`, then `php artisan optimize:clear`.
3. Make sure `storage/` and `bootstrap/cache/` are writable (the folders now ship with .gitignore placeholders).
4. `.env`: `LUVUPAY_PAYMENT_DRIVER=mock` (everything simulated) or `paypal` (sandbox). `LUVUPAY_DEMO_SIMULATOR=true` for the demo buttons.

## Root causes fixed
- 404 on ag-grid → moved to `/assets/ag-grid-32.3.3/`.
- `/api/v1/dashboard/summary` 500: query on non-existent `merchants.paypal_email`, and a missing method `recentEventsForMerchant()`.
- Wallet/KPIs empty because summary 500 → rewritten on one service: `App\Services\Dashboard\MerchantMetrics`.
- "Simulate 30 days" only created 5 charges → new `POST /api/v1/dashboard/demo/simulate-month` (`App\Services\Demo\MonthSimulator`): active, renewed, grace, partial refund, charity, resale sold, churned; back-dated over 30 days; honours project settings.
- Project settings not wired: create modal dropped resale/charity/refund; Settings' Grace Period was discarded by validation. Both now use `POST /api/v1/dashboard/project` with all fields (name, plan, price, currency, env, grace, resale, charity, refund %).
- Withdraw: mock mode now works end to end; balance computed from the ledger; fixed missing `idempotency_key` insert.
- Console no longer demands a PayPal link when the driver is mock.
- Marketplace buy works in mock mode (no PayPal credentials).
- Analytics page rewritten (working 7/30/90 filter, KPIs, charts, outcomes, settings in effect, insights, recent activity).
- Fatal signature mismatch in `MockPaymentProvider` / `PayPalPaymentProvider::parseWebhook`.
- `phpunit.xml` APP_KEY was 33 bytes (invalid) so most tests could not run.

## Money semantics (MerchantMetrics)
Revenue = completed charges. Revenue saved = partial-refund cash kept + $15 chargeback fee avoided per charity donation + resale fees + smart-convert retained.
Wallet = charges − partial refunds − charity − withdrawals + resale fees.


# Round 2 (25 Oct 2026)
- **Preview User Experience** now floats bottom-right on every console page. Root cause: the console loads `ui.css`, a partial Tailwind build without `bottom-6/left-6/bg-indigo-600`, so the button was `position:fixed` with no position. Rewritten with its own scoped CSS.
- **Withdraw 502 (PayPal sandbox)**: real cause was `Money::toDecimalString()` missing (crashed every payout AND every PayPal order). Added. Sandbox payouts PayPal rejects are now recorded as labelled simulations (`LUVUPAY_PAYOUT_SANDBOX_FALLBACK`), with a readable error otherwise.
- **Logo upload**: the Settings script was inline and blocked by the app CSP, so the form did nothing. Moved to `public/js/settings-logo.js`; logos are served from `/brand/logo/{id}` (no `storage:link` needed). Toast queue in the layout had the same CSP problem, fixed.
- **Rate limits**: numeric `throttle:N,M` shared ONE bucket per IP across all routes, so normal dashboard calls caused random 429 on upload/withdraw. Every route now has its own bucket.
- **Invoices**: Bookipi-style HTML page + dependency-free PDF (logo JPEG/PNG), signed public links, "Invoice details" (address, tax ID) in Settings, preview button.
- **Email**: new subscription and resale purchase send a receipt with the invoice inline and the PDF attached (needs real MAIL_* settings).
- **Public merchant page** `/m/{merchant-id}` lists live resale listings with Buy. **Hosted checkout** `/checkout/{id}` and **token redemption** `/redeem/{token}` (your design), mock or PayPal sandbox. Console has a new **Resale** page.
- IDR removed from currency pickers (PayPal does not support it).
- New migration: `resale_listings.redeem_token` (run `php artisan migrate --force`).
- Tailwind for the hosted pages is compiled to `public/css/hosted.css` (no CDN, works with the CSP).

# Round 3 (25 Oct 2026)
- **Withdrawals**: the merchant wallet (earnings ledger) is debited the gross amount; PayPal's payout fee comes out of that wallet (`LUVUPAY_PAYOUT_FEE_*`); the NET is paid to the merchant's OWN PayPal email (Settings -> Payouts, password-confirmed, new column `merchants.payout_email`). A receipt email (gross, fee, net, PayPal batch id) goes to the merchant. Customer payments land in the platform's PayPal app account (merchant of record) and are credited to merchant wallets in the ledger.
- **Missed Cancellation Journey** is now a dual-view simulator (backend log | user's phone, 5 synced actions). It runs on a real completed charge, calls the real `forgot`/`decision` endpoints and prints real webhook events, AI source/nudge and sandbox status. The separate floating Preview button was removed.
- **Resale -> /marketplace**: merchant resale listings are merged into the public marketplace; Buy now -> /redeem/{token}.
- **Toasts**: solid colours (success green, info blue, warning yellow, error red).
- New migration: `merchants.payout_email` (run `php artisan migrate --force`).


# Round 4 (28 Oct 2026): New Subscription Checkout rebuilt
**Why checkout jumped straight to PayPal and money did not "just appear"**: PayPal never moves money without the buyer approving it, not even in the sandbox, so approval cannot be skipped. What was wrong around it:
- `LUVUPAY_PAYMENT_DRIVER=mock` still went to PayPal whenever `PAYPAL_CLIENT_ID/SECRET` were filled (`$hasPayPalCreds && ($driver === 'paypal' || $hasPayPalCreds)` was always true). Now **only the driver decides**; mock never calls PayPal. If the driver is `paypal` but credentials are empty you get a clear 422 instead of a fake "paypal" row.
- It used a one-off Orders payment but stored a fake subscription id (`I-DEMO-...`). Now it creates a **real PayPal Billing Subscription**: product + plan are created automatically for the project price (cached; override with `PAYPAL_PLAN_ID`), the buyer approves ONCE, PayPal bills every period. If Subscriptions is unavailable it falls back to a one-off order (`LUVUPAY_CHECKOUT_MODE=order` forces that).
- Full-page redirect replaced by a **PayPal popup** (no CSP change needed: the popup is a normal window). Popup blocked -> automatic full redirect, and the remaining subscribers resume on return.

**Bugs fixed in the flow**
- Receipt email, invoice and the merchant notification were sent *before* the buyer paid. They now run only after PayPal confirms, exactly once.
- Batch with PayPal created N orders but redirected only for the last one; the others stayed `pending_approval` forever. Now one approval per subscriber, processed one after another; never-paid rows are deleted (cancel, popup closed, or older than 30 min) so they no longer pollute the ledger or count as churn.
- `paypalReturn` trusted the query string: it did not check the transaction belongs to the logged-in merchant, did not check the order id or the paid amount, and was not idempotent (refresh = second capture attempt). Now merchant-scoped, order/subscription id must match what we stored, amount + currency verified, activation is a single conditional UPDATE.
- `paypalReturn`/`paypalCancel` wrote webhook events with `merchant_id = NULL`, so they never appeared in the Live event log. Fixed.
- Currency came from the merchant row while the amount came from the project; both now come from the project.
- New status endpoint `GET /api/v1/dashboard/demo/checkout/{tx}/status` (merchant-scoped) so the dashboard can confirm the payment even if the popup message is lost.

**New modal** (`dashboard/partials/checkout-modal.blade.php` + `public/css/checkout-modal.css`, scoped CSS so it does not depend on the partial Tailwind build): plan + price header, mode badge (PayPal Sandbox / Mock), 3-step explainer, subscriber cards with per-row status (Ready / Waiting for PayPal / Paid / Needs attention), live "Due now" total, Esc/backdrop to close.

**Housekeeping**: `.env.example` had every PayPal key twice; deduped and documented (`LUVUPAY_CHECKOUT_MODE`, `PAYPAL_PLAN_ID`). No migration needed; run `php artisan config:clear && php artisan route:clear`.

**Sandbox checklist**: .env -> `LUVUPAY_PAYMENT_DRIVER=paypal`, `PAYPAL_MODE=sandbox`, Client ID/Secret of a sandbox REST app; pay with a sandbox **personal** account (not the business one). The money appears in the **business** account that owns that REST app. Subscriptions must be enabled for the app (default on for sandbox apps).



# Round 5 (31 Oct 2026): New Subscription Checkout bug sweep ("Transaction not found." etc.)

**Root causes**
- **"Transaction not found."** The dashboard added the *pending* checkout row to the grid and selected it. When the checkout was cancelled / the popup closed / the 30-min purge ran, the server deleted that row but the browser kept `selectedId` (and `patchRow` even re-created a row with the stale id), so the next action (`forgot`, `decision`, `resale`, negotiator, receipt...) hit a deleted transaction. Also, `checkout/{tx}/status` could 404 from `formatRow()` when a concurrent request discarded the row between the ownership check and the read.
- **Invoice + emails never sent when the PayPal webhook won the race** against the return URL: `PendingCheckoutActivator` flipped the row to `completed` without invoice/email, and `settle()` then saw `completed` and skipped them forever. The activator also wrote lifecycle `Settled` instead of `ACTIVE`.
- **Random 429 during payment**: the status poll shared the 30/min bucket (`dashboard96`) with every other demo action, and was also counted by the 120/min limiter in `EnsureDemoSimulator`.
- **Next subscriber's popup got blocked**: after paying subscriber #1 the code auto-opened the next popup outside a click, so browsers blocked it and forced a full-page redirect.
- Re-using an old pending checkout for the same email returned its old approval link without asking PayPal (could be dead, or already paid).
- Declined payments left a ghost `pending` row; the popup page referenced a non-existent `/images/logo-mark.png`.

**Fixes**
- New `App\Services\Checkout\CheckoutFinalizer`: invoice + outbound delivery + subscriber receipt + merchant notice, **exactly once** (atomic cache lock + `checkout_finalized_at` marker). Called by mock checkout, the return/poll path, and the webhook activator.
- `DemoCheckoutController`: status never 404s (`safeRow`), returns `removed_id` when a row is gone; hard declines discard the pending row; an existing pending checkout is re-verified with PayPal before its link is reused (`409 already_paid` if it was paid).
- Status route has its own bucket (`throttle:240,1,dashboard97`) and is not counted by the demo limiter.
- `dashboard.js`: `removeRow()` / `pruneSelection()` (selection can never point at a deleted row, also after `load()`), `sim()` self-heals on a 404 "Transaction not found" (refresh + friendly toast), no more auto-popup for the next subscriber (click "Continue with next subscriber"), pending rows no longer counted as subscribers in the local KPI.
- Storage placeholders (`storage/framework/{views,cache/data,sessions}`) now ship, so a fresh deploy does not crash with "Please provide a valid cache path".
- `phpunit.xml` now carries a valid test env (sqlite in memory, APP_KEY, mock driver). New `tests/Feature/CheckoutFlowTest.php` (6 tests): mock checkout, webhook-vs-return race, vanished checkout, tenant isolation, cancel, paypal-without-credentials.

No migration needed. After deploy: `php artisan config:clear && php artisan route:clear`.

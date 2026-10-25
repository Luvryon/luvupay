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

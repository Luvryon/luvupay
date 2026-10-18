# Review fixes

**Marketplace** (`MarketplaceController`, new `MarketplaceService`, migration `2026_03_02_000001`, `MarketplaceTest`)
- Seller/buyer email no longer exposed; seller shown masked.
- CSRF exemption removed (JS already sent the token); sell throttle tightened; price must be <= original; currency whitelist.
- Optional seller email verification via signed link (default on in production).
- Reservation is an atomic compare-and-set; PayPal is no longer called while a row lock is held.
- Reservations expire (`reserved_until`) and are released lazily and by `resale:maintain`.
- PayPal return/cancel bound to a per-reservation token; capture verified (COMPLETED + exact amount); idempotent on refresh.
- Sales are posted to the double-entry ledger; seller cannot buy their own listing.
- Demo sample listings only when `LUVUPAY_DEMO_SIMULATOR=true`, and only when the table is empty.
- Buyer-facing toasts for bought / cancelled / failed / verified.

**Config & docs**: `.env.example` no longer defines `LUVUPAY_PAYMENT_DRIVER` twice (the second `paypal` value broke the default quick start); README/MATURITY now describe the databases that are actually tested (PostgreSQL, SQLite).

**Code structure**: `DemoSimulatorController` (1,344 lines) split into `Demo/*Controller` per feature + `Concerns/DemoSupport`; routes updated.

**Repo hygiene**: removed duplicate `resources/js|css` copies of served assets and generated `bootstrap/cache/*.php`; Dockerfile no longer hides failures (`|| true`), installs `composer`, and creates storage dirs correctly; seeders refuse to create demo credentials in production.

# UI redesign, project context and security pass

**Merchant Project Context** — `MerchantProject` + `luvupay.demo_project`; dashboard header bar (project, plan, subscribers); checkout modal bound to
"StreamNetFly - Premium Plan · $20.00 / month"; missed-cancellation panel, logs and the 3 choices reference the project; AG Grid has a "Product / Plan" column.
The server now decides product and amount (client `product` / `amount_minor` are ignored).

**Redesign + components** — new design system (`resources/css/ui.css`, Tailwind v4, compiled with the standalone CLI), 20 Blade components (`x-ui.*`, `x-console.nav`),
new `layouts/console` and `layouts/public`; all console, marketplace, status, support, directory, legal, docs and error pages rebuilt or re-skinned.
Untouched by request: welcome, login, register (and the MFA/verify/invite pages that belong to the auth flow).

**Security / privacy fixes found during the pass**
- Cross-tenant leak: sandbox "webhook logs" returned the newest `webhook_events` of *all* merchants (incl. real provider payloads). Events are now tagged with `merchant_id` and filtered (migration `2026_03_03`).
- Stored-XSS vector: team page built `onsubmit="return confirm('Remove {{ name }}?')"`; replaced by `<x-ui.confirm-form>` (escaped `data-confirm`, handled in `ui.js`).
- The marketplace "Connect & Sell" form collected the seller's app **password**; removed. `window.prompt` for buyer email replaced by a modal.
- No third-party runtime assets: Tailwind CDN (unpinned), Font Awesome and Alpine/AG Grid/Chart.js CDNs removed; Alpine + AG Grid vendored; Chart.js was unused.
- Strict CSP `<meta>` on every redesigned layout, no inline scripts/handlers, `rel="noopener noreferrer"` on external links, only `https://*.paypal.com` approval URLs are opened, all API text rendered with `x-text`.
- Relative asset URLs, so pages keep working behind ngrok/proxies without trusted-proxy configuration.
- Double-submit guard on POST forms; invoice page is merchant-scoped and has `connect-src 'none'`.

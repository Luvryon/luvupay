# LuvuPay maturity

Sandbox-first, built to the same controls a live deployment needs. Real money is gated behind
`PAYPAL_MODE=live` **and** `APP_ENV=production` (boot fails otherwise).

| Area | Control | Where |
|---|---|---|
| Webhooks | Verify → durable inbox (unique event id) → ack → async process; retry/backoff, dead-letter, replay | `WebhookHandler`, `ProcessWebhookEventJob`, `luvi:webhooks:replay` |
| Money | Append-only double-entry ledger, balanced journals, exactly-once by reference, PG trigger blocks UPDATE/DELETE | `LedgerPoster`, `ledger_*` tables |
| State | Transaction state machine; terminal states are final; locked row transitions | `TransactionStatus`, `Transaction::transitionTo` |
| Reconciliation | Hourly/daily: trial balance, missing journals, split sums, stuck payouts checked against PayPal; exceptions table | `luvi:reconcile`, `reconciliation_exceptions` |
| API | Idempotency-Key, per-key scopes, per-merchant rate limit, request id on every response | `EnsureIdempotency`, `RequireScope`, `AssignRequestId` |
| Console | RBAC (owner/admin/finance/developer/viewer), TOTP MFA + recovery codes, invitations | `RequireRole`, `MerchantUser`, `TeamController` |
| Registration | All-or-nothing: any failure rolls back, nothing is created, nobody is logged in | `MerchantAuthController::register` |
| Config safety | Refuses to boot on unsafe combinations (live keys outside production, debug in production, mock in production) | `AppServiceProvider` |
| Marketplace | Compare-and-set state machine (pending→active→reserved→capturing→sold); reservation token bound to the PayPal return/cancel URLs; capture verified (COMPLETED + exact amount) before sale; sale posted to the ledger; seller email never exposed; signed-link seller email verification (default on in production); CSRF on | `MarketplaceService`, `MarketplaceController`, `MarketplaceTest` |
| Ops | Docker/compose, CI (lint + tests on SQLite & PostgreSQL + gitleaks), Prometheus metrics, JSON logs | `Dockerfile`, `.github/`, `/internal/metrics` |

## Known gaps (marketplace)
- Seller proves control of an **email**, not of the underlying subscription; real ownership proof needs the merchant integration (Entitlement Bridge).
- Seller payout is recorded in `liability:seller_payable` but not yet paid out automatically.
- A listing stuck in `capturing` (process crash mid-capture) needs a reconciliation sweep against PayPal; today it is surfaced only by logs.

## Still out of scope (needs people/process, not code)
SOC 2 / PCI attestation · multi-region active-active · dispute desk UX · legal opinion on money transmission ·
pen-test · KMS/HSM-held keys · SSO/SAML · public SDKs.

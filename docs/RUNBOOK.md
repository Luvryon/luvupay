# Operations runbook

## Daily health
- `GET /internal/health` (Bearer METRICS_TOKEN) · `GET /up` (db, cache, redis, queue, provider) · `GET /up` · `GET /internal/metrics` (Bearer `METRICS_TOKEN`)
- Alert on: `luvupay_ledger_unbalanced_currencies > 0` (page), `luvupay_webhook_events{status="dead"} > 0`,
  `luvupay_reconciliation_exceptions_open > 0`, `luvupay_failed_jobs` rising.

## Webhook not processed
1. `select id,event_id,event_type,status,attempts,last_error from webhook_events where status in ('failed','dead') order by received_at desc;`
2. Fix the cause (see `last_error`), then `php artisan luvi:webhooks:replay --status=dead` (or `--id=<uuid> --force`).
3. Processing is idempotent; replaying a processed event is a no-op unless `--force`.

## Reconciliation exception
`select * from reconciliation_exceptions where status='open';`
- `ledger_unbalanced`: STOP payouts, treat as incident. Ledger rows are immutable; correct with a reversing journal, never an UPDATE.
- `missing_journal`: a payout completed without a journal. Post it with `LedgerPoster::payoutCompleted($tx)` in tinker (idempotent).
- `provider_lookup_failed`: PayPal API issue; re-run `php artisan luvi:reconcile`.
- `payout_settled_by_reconciliation` (auto_resolved): informational — webhook was late/missing.

## Stuck payout (Processing > 15 min)
`php artisan luvi:reconcile` checks PayPal and settles or fails it. Failed payouts can be retried by the original job.

## Key rotation
- `APP_KEY`: re-encrypts nothing automatically — MFA secrets are encrypted with it. Rotate with `APP_PREVIOUS_KEYS`, then have users re-enrol if needed.
- `AUDIT_HMAC_KEY`: start a new chain segment; keep the old key to verify history (`php artisan audit:verify`).
- Merchant API keys: console → Settings → Rotate (needs `keys.rotate`).

## Going live (checklist)
`APP_ENV=production`, `APP_DEBUG=false`, `PAYPAL_MODE=live`, live `PAYPAL_*`, `QUEUE_CONNECTION=redis` with workers + scheduler running,
`LUVUPAY_REQUIRE_MFA=true`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` set, PostgreSQL backups + PITR tested, `METRICS_TOKEN` set.

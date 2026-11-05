# Test the hero flow in Postman

Files (also downloadable from `/docs/postman`):

- `postman/LuvuPay.postman_collection.json`: 27 requests with assertions
- `postman/LuvuPay.postman_environment.json`: `base_url`, `email`, `password`, `api_key`, `paypal_plan_id`

## Setup (2 minutes)

1. Postman → **Import** → select both files. Choose the environment **LuvuPay · Local / Render** (top right).
2. Edit the environment:
   - `base_url`: `http://localhost:8000` or your Render URL (no trailing slash)
   - `email` / `password`: the seeded demo owner is `owner@demomerchant.test` / `SecurePass1`. The account must **not** have 2FA enabled
   - `api_key`: the `lp_live_…` key printed **once** by `php artisan db:seed` (or create one in the console → Settings)
   - `paypal_plan_id`: a sandbox billing plan id (`P-…`) if the driver is `paypal`; any text works with the mock driver
3. Server needs `LUVUPAY_DEMO_SIMULATOR=true`. For the real flow also `NEBIUS_API_KEY`, `LUVUPAY_PAYMENT_DRIVER=paypal`, sandbox credentials and a connected PayPal account (`docs/DEMO_RUNBOOK_90S.md`).
4. Click the collection → **Run** (keep the folder order). Every test should be green.

> No Nebius/PayPal yet? Set the **collection variable** `require_real` to `false`. The AI path and the PayPal batch checks are then skipped or relaxed, and the payout is labelled SIMULATED.

## What each folder proves

### 1 · Hero flow (console session)
Postman signs in with the normal login form (cookie + CSRF) and then:

| # | Request | Proves |
|---|---|---|
| 4 | Preflight + live AI ping | the model really answers; PayPal credentials present |
| 5 | AI proposes (`mode: propose`) | split adds up, charity ≤ 50 % guardrail, donation stage = `awaiting_approval`, labelled EXAMPLE |
| 6 | Evidence before approval | public JSON, no PayPal batch yet, `model_raw` stored |
| 7 | Human lowers donation by half, approves | donation lowered never raised, guardrail note recorded, real `payout_batch_id` |
| 8, 9 | Approve again / reject after execute | both return **409**: a proposal can never pay twice |
| 10, 11 | Evidence JSON + page after approval | audit trail, masked decider, no email leak, `5 · Donation` visible |
| 12 | Ledger | parent row is SMART-CONVERTED and the donation row exists |
| 13, 14 | Propose, then reject | status `rejected`, donation `not_sent`, "No money moved" |
| 15 | One-click auto mode (optional) | second payout in a single request |

### 2 · Merchant API (API key)
Bearer auth, `Idempotency-Key` replay (same body returns the first answer), reuse with a different body → 422, missing key → 400, wrong key → 401, resale list, AI health report, export.

### 3 · Security checks
Forged PayPal webhook is rejected, console API without a session is refused (run this folder before signing in), unknown evidence ids return 404.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Step 2 fails "signed in" | wrong email/password, or the account has 2FA. Use the seeded owner |
| 404 on `/api/v1/dashboard/demo/...` | `LUVUPAY_DEMO_SIMULATOR` is not `true` |
| 403 `paypal_not_connected` | click **Connect PayPal** once in the console |
| 419 on a POST | run steps 1-3 again to refresh the CSRF token |
| 502 `stage: ai` | the model call failed; nothing moved by design. Check `NEBIUS_API_KEY` |

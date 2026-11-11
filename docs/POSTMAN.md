# Test the hero flow in Postman

Files (also downloadable from `/docs/postman`):

- `postman/LuvuPay.postman_collection.json`: 27 requests with assertions
- `postman/LuvuPay.postman_environment.json`: **hosted**, `base_url` = `https://luvupay.luvryon.com`
- `postman/LuvuPay.local.postman_environment.json`: same variables, `base_url` = `http://localhost:8000`

Variables: `base_url`, `email`, `password`, `api_key`, `paypal_plan_id`.

## Setup (2 minutes)

1. Postman → **Import** → the collection and the environment you need. Choose it in the top-right dropdown: **LuvuPay · Hosted (luvupay.luvryon.com)** or **LuvuPay · Local**.
2. Edit the environment:
   - `base_url`: already set per environment (no trailing slash). Change it if your domain differs
   - `email` / `password`: the seeded demo owner is `owner@demomerchant.test` / `SecurePass1`. The account must **not** have 2FA enabled
   - `api_key`: a `lp_live_…` key for **that server**. In the console go to Settings and use *Rotate key*; the new key is shown once. Rotating **invalidates the previous key**, so do it before you record, not after
   - `paypal_plan_id`: a sandbox billing plan id (`P-…`) if the driver is `paypal`; any text works with the mock driver
3. The hosted server needs `LUVUPAY_DEMO_SIMULATOR=true`, the demo owner account seeded (`php artisan db:seed` on that database) and no 2FA on it. For the real flow also `NEBIUS_API_KEY`, `LUVUPAY_PAYMENT_DRIVER=paypal`, sandbox credentials and a connected PayPal account (`docs/DEMO_RUNBOOK_90S.md`).
4. Click the collection → **Run** (keep the folder order). Every test should be green.

> No Nebius/PayPal yet? Set the **collection variable** `require_real` to `false`. The AI path and the PayPal batch checks are then skipped or relaxed, and the payout is labelled SIMULATED.

## Running against the public site
- Cookies and CSRF work over HTTPS in Postman, no extra setup.
- Folder 1 creates **real rows** on the hosted database and, with the `paypal` driver, **real PayPal Sandbox payouts** (test money). Run it before recording, not while recording, and keep a note of the `run_id` the console prints.
- The demo endpoints exist only while `LUVUPAY_DEMO_SIMULATOR=true`. That is intended for the judging period; switch it off afterwards if the site stays public.

## What each folder proves

### 1 · Hero flow (console session)
Postman signs in with the normal login form (cookie + CSRF) and then:

| # | Request | Proves |
|---|---|---|
| 0b | Console API without a session | anonymous callers are refused (runs before sign-in on purpose) |
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
Forged PayPal webhook is rejected, unknown evidence ids return 404.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Step 2 fails "signed in" | wrong email/password, or the account has 2FA. Use the seeded owner |
| 404 on `/api/v1/dashboard/demo/...` | `LUVUPAY_DEMO_SIMULATOR` is not `true` |
| 403 `paypal_not_connected` | click **Connect PayPal** once in the console |
| 419 on a POST | run steps 1-3 again to refresh the CSRF token |
| 502 `stage: ai` | the model call failed; nothing moved by design. Check `NEBIUS_API_KEY` |

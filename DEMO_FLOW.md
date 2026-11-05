# LuvuPay demo flow (hero story)

Single story: **subscriber forgot -> AI proposes -> guardrails / you decide -> PayPal pays -> donation -> audit trail.**
Script, timings and rehearsal checklist live in [DEMO.md](DEMO.md) and [docs/DEMO_RUNBOOK_90S.md](docs/DEMO_RUNBOOK_90S.md); automated checks in [docs/POSTMAN.md](docs/POSTMAN.md).

## Env (required)
```
APP_ENV=local
LUVUPAY_DEMO_SIMULATOR=true
LUVUPAY_PAYMENT_DRIVER=paypal   # or mock for offline (payout is then labelled SIMULATED)
PAYPAL_MODE=sandbox
```
Then `php artisan migrate --seed && php artisan config:clear`.

## Steps
1. Login at `/dashboard` (`owner@demomerchant.test` / `SecurePass1`). With the `paypal` driver click **Connect PayPal** once.
2. In **AI Smart-Convert** click **Check readiness (live AI ping)**; it must say ready.
3. Tick **Require my approval**, click **Run AI Smart-Convert Protocol**. The stepper stops at *3 · You decide* and the **Donation** card says *WAITING FOR YOUR APPROVAL*.
4. Lower the donation and **Approve**. The stepper reaches *6 · Audit trail*, the Donation card shows amount, receipt id and status.
5. Open **AI Negotiation Evidence** (`/evidence/smart-convert/{run}`): model, tokens, latency, rationale, raw vs final split, PayPal batch id, donation.

The donation recipient is an **example** (*Example Charity (sandbox)*, EXAMPLE · SANDBOX tag). PayPal Sandbox moves test money only.

## Why the PayPal Developer "Event Log" may still be empty
That UI only logs real HTTP calls to `api-m.sandbox.paypal.com`. LuvuPay's **Live event log** / Webhook Debugger reads the `webhook_events` table, which is written on every demo action. With the `mock` driver no PayPal call is made at all.

# Demo runbook: 90-second hero run

Goal: one unbroken take that shows **AI proposes → merchant can intervene → PayPal executes → donation → ledger/audit trail**. The donation recipient is an example (EXAMPLE · SANDBOX tag); say so.
Everything below is sandbox money. Nothing here has been run against live Nebius/PayPal by the author of this file:
**do the rehearsal section first and replace every `[ISI]` with what you actually saw.**

## T-1 day: setup

0. Import `postman/LuvuPay.postman_collection.json` + the environment and run the collection (see `docs/POSTMAN.md`). Folder 1 must be all green before you record.

1. Deploy with `render.yaml` (web + worker + Postgres). Fill the `sync: false` env vars.
2. Shell on the web service:
   ```
   LUVUPAY_ALLOW_DEMO_SEED=true php artisan db:seed --force
   php artisan luvupay:demo-check --live
   ```
   It must end with `GO`. Fix every `FAIL`; read every `WARN`. The `--live` flag makes one real model call.
3. Demo account (from `DatabaseSeeder`): `owner@demomerchant.test` / `SecurePass1`. If `LUVUPAY_REQUIRE_MFA` is on, enrol TOTP once and keep the authenticator ready.
4. In the dashboard click **Connect PayPal** once (required when the driver is `paypal`).
5. Make sure `PAYPAL_CHARITY_SANDBOX_EMAIL` is a real sandbox Business/Personal account (not the seeded `donations@charity.example.org`, which is refused).

## Browser profiles (record without logging in on camera)

Create three Chrome profiles, each already signed in; see `docs/PAYPAL_PAYOUTS_DEMO.md` for the account setup.

| Profile | Signed in as | Used for |
|---|---|---|
| A | LuvuPay demo owner | the dashboard, the whole take |
| B | PayPal sandbox **charity** account (receives the Smart-Convert payout) | show the money arriving (cut-away) |
| C | PayPal sandbox **seller** account | marketplace payout arrival |

Also keep a tab open on PayPal Developer Dashboard → Apps & Credentials → your app → **API calls**.

## The 90 seconds

| Time | On screen | Say |
|---|---|---|
| 0:00–0:10 | Dashboard, a charge that was never used | "A subscriber forgot to cancel. Refund means a chargeback fee and a lost customer." |
| 0:10–0:20 | Tick **Require my approval**, click **🧪 Run AI Smart-Convert Protocol** | "Instead, an AI agent negotiates the settlement." |
| 0:20–0:40 | Result card: model name, tokens, latency, rationale, split. Stepper stops at *3 · You decide* | "Nemotron on Nebius proposes this split and tells us why. Policy guardrails cap the fee and charity share." |
| 0:40–0:50 | Lower the donation a little, click **Approve** | "The merchant stays in control: I can lower the donation or reject it. The model never touches money." |
| 0:50–1:00 | Stepper moves to *4 · PayPal pays*; show `payout_batch_id` | "PayPal Payouts executes. This is a real sandbox batch." |
| 1:00–1:10 | **Donation** card: amount, receipt id, status, EXAMPLE · SANDBOX tag | "The donation is its own stage with a receipt. The recipient is an example and it is sandbox money." |
| 1:10–1:20 | **AI Negotiation Evidence** page (scroll to *5 · Donation*) | "Every run leaves a public, read-only evidence page: raw model split vs final split." |
| 1:20–1:30 | Ledger row (`charity_payout`) in AG Grid, then PayPal Developer Dashboard API call with the same batch id | "Ledger and PayPal agree on the same batch id." |

Optional (+15 s): Webhook delivery center, click **Retry** on one delivery. Optional (+20 s): run the Postman collection and show all green.

Marketplace add-on (+30 s, optional): sell a listing, buy it in a second tab, show the seller's payout batch on the listing and in profile C.

## Rehearsal checklist (do it 5×, tick each)

- [ ] `luvupay:demo-check --live` prints GO
- [ ] Run with **approval** off → `AI PATH · REAL PayPal Payouts batch` banner
- [ ] Run with **approval** on → approve with a lowered donation → guardrail note says "Merchant lowered the donation"
- [ ] Reject once → evidence page says REJECTED, Donation card says NOT SENT, PayPal shows nothing new
- [ ] Donation card shows the EXAMPLE · SANDBOX tag and a `DON-…` receipt id after approval
- [ ] Postman folder 1 is all green
- [ ] Batch id on the evidence page equals the id in the PayPal API-call log
- [ ] Webhook retry visibly flips a delivery status
- [ ] Marketplace: sale → payout row shows `paid`/`sent` with a batch id
- [ ] Worker is running (payout does not depend on a page refresh)

Observed in rehearsal: latency `[ISI] ms`, tokens `[ISI]`, PayPal batch status `[ISI]`, failures seen `[ISI]`.

## If something breaks on camera

- AI error banner (red, stage `ai`): nothing moved by design. Run preflight (`Check readiness`), retry. Do not switch to fallback mode for the recording.
- PayPal error (stage `paypal`): check the sender has sandbox balance and Payouts is enabled for the app; the failed run still has an evidence page.
- Marketplace payout `held_unverified`: the seller has not clicked the link (set `MARKETPLACE_REQUIRE_SELLER_VERIFICATION=true` so it happens at listing time).

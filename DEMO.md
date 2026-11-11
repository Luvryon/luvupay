# LuvuPay: Judge Demo Pack (one story, 3 minutes)

## One-liner
A subscriber forgot to cancel. **AI proposes** how the unused value is split (store credit / charity / fee), **rules and a human decide**, **PayPal Payouts pays**, and every step is **auditable**.

```
1 Subscriber forgot -> 2 AI proposes -> 3 Guardrails / you decide -> 4 PayPal pays -> 5 Donation -> 6 Audit trail
```

Everything else in the repo (Second Chance, Resale, Marketplace, Entitlement Bridge, Adaptive Nudge) is supporting infrastructure. Do not demo it in the 3-minute video.

## Stack proof (say this out loud)
- **AI:** Nebius Token Factory, NVIDIA Nemotron. Model name, tokens and latency are on the evidence page
- **Money movement:** PayPal REST **Sandbox** Payouts API (`PAYPAL_MODE=sandbox`), batch read back from PayPal
- **Accountability:** `model_raw` vs final split, guardrail adjustments, masked decider, public evidence page
- **Tables:** AG Grid Enterprise on every console table (ledger, wallet, resale, analytics, team, webhooks)

## The donation is an example
The recipient is *Example Charity (sandbox)* with an **EXAMPLE · SANDBOX** tag on the dashboard card, the evidence page and the JSON. PayPal Sandbox moves test money only; no real organisation is named or paid. Say so on camera.

## 3-minute script
| Time | Screen | Say |
|---|---|---|
| 0:00 | Ledger, a charge never used | "A subscriber forgot to cancel. A refund means a chargeback fee and a lost customer." |
| 0:20 | **Check readiness (live AI ping)**, tick **Require my approval**, **Run AI Smart-Convert Protocol** | "An AI agent negotiates the settlement instead." |
| 0:40 | Result card: model, tokens, latency, rationale, split | "Nemotron proposes the split and says why. Policy guardrails cap fee and charity." |
| 1:10 | Lower the donation, **Approve** | "I stay in control. I can lower the donation or reject. The model never touches money." |
| 1:30 | Stepper: PayPal pays, then the **Donation** card | "PayPal Payouts executes. The donation is a visible stage: amount, receipt, status. The recipient is an example." |
| 1:55 | **AI Negotiation Evidence** page | "Every run leaves a public, read-only evidence page: raw model split vs final split." |
| 2:20 | Ledger row (AG Grid) + PayPal Developer Dashboard API call | "Same batch id on PayPal's side." |
| 2:45 | Postman run, all green | "Everything I showed is also an automated test." |

## Before recording
1. Import the `postman/` collection + the hosted environment (`https://luvupay.luvryon.com`), run it, everything green (`docs/POSTMAN.md`).
2. `php artisan luvupay:demo-check --live` prints GO.
3. Full rehearsal list: `docs/DEMO_RUNBOOK_90S.md`.

## Env for real integrations
```bash
LUVUPAY_DEMO_SIMULATOR=true
LUVUPAY_PAYMENT_DRIVER=paypal
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=...
PAYPAL_SECRET=...
PAYPAL_CHARITY_SANDBOX_EMAIL=...      # a sandbox account that receives the example donation
NEBIUS_ENABLED=true
NEBIUS_API_KEY=...
NEBIUS_MODEL=nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B
AG_GRID_LICENSE_KEY="..."
SMART_CONVERT_CHARITY_LABEL="Example Charity (sandbox)"
```

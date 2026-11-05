# Devpost submission draft

Fill every `[ISI]` from a real run. Do not claim anything the evidence page does not show.

## Tagline
When a subscriber forgets to cancel, an AI agent proposes a fair settlement and PayPal Payouts executes it, with a human able to step in first.

## Inspiration
Forgotten subscriptions end in refunds, chargebacks and lost customers. Money-moving AI agents are easy to build and hard to trust. We wanted an agent that is useful **and** accountable.

## What it does
**Smart-Convert.** For a charge the customer never used, an AI model (NVIDIA Nemotron on Nebius Token Factory) proposes how the unused value is split between store credit, a charity and a fee, and states its reasons. Policy guardrails cap the fee and charity share. In approval mode the merchant can approve, reject or lower the donation. Only then does **PayPal Payouts** send the charity share.

**AI proposes → rules and a human decide → PayPal executes → donation → audit trail.** The model never has PayPal access. The donation is a visible stage (amount, receipt id, status); the recipient is an example and PayPal Sandbox moves test money only.

**Evidence, not claims.** Every run produces a public AI Negotiation Evidence page: model, tokens, latency, rationale, raw vs final split, guardrail adjustments, and the PayPal `payout_batch_id` returned by PayPal. Failures are shown as failures: if the AI or PayPal step fails, nothing moves and nothing is faked.

**Operations.** Double-entry ledger, tamper-evident audit log, webhook delivery center with retries, AG Grid Enterprise tables, and an importable Postman collection that tests the whole story (including the refusal paths).

The repo also contains Second Chance, a resale marketplace, an entitlement bridge and adaptive nudges; they are supporting infrastructure and not part of this submission's story.

## How we built it
Laravel 12 / PHP 8.3 on FrankenPHP; PostgreSQL; Nebius Token Factory (`[ISI model id]`); PayPal Payouts + Orders/Subscriptions (sandbox); queue worker for payouts and webhooks; deployed on Render.

## Ethical agentic money movement
- Human control point before money moves (approve / reject / lower).
- Deterministic policy limits applied to the model's output; every adjustment recorded.
- No silent fallbacks: a fallback is labelled, and strict mode refuses it.
- Exactly-once payouts (idempotent batch ids, compare-and-set).
- Data minimisation: the model receives amounts and a hashed email fingerprint, never an address.
- Public evidence pages mask emails and hold no secrets.

## Challenges
`[ISI: real ones, e.g. model returning amounts that do not sum, reasoning-trace availability, PayPal sandbox payout latency]`

## Honest limitations
- Sandbox money only. The donation recipient is illustrative (*Example Charity (sandbox)*); no real charity is named or paid. `[ISI]` live runs were performed before submission.
- The seller check proves control of an inbox, not ownership of the underlying subscription (`docs/OWNERSHIP_AND_LIMITS.md`).
- The "reasoning" shown is the model's stated rationale; a native trace appears only if the endpoint returns one.
- Demo inputs for Smart-Convert are fixed (10 unused days, $30).

## What's next
Provider OAuth / receipt verification for sellers, merchant-defined policy limits, per-merchant approval thresholds.

## Built with
`laravel` `php` `postgresql` `nebius` `nvidia-nemotron` `paypal-payouts` `frankenphp` `render` `alpine.js`

## Evidence strip (screenshots to capture from the real run)
1. Dashboard result card (model, tokens, latency, split, batch id)
2. AI Negotiation Evidence page, full page
3. Approval step with the lowered donation + guardrail note
4. PayPal Developer Dashboard → API calls: `POST /v1/payments/payouts` with the same batch id
5. PayPal sandbox charity account showing the received payment
6. Ledger rows + audit trail
7. Donation card (EXAMPLE · SANDBOX) and the evidence page's *5 · Donation* section
8. Postman collection run, all green

## Video (2–3 min)
1. 0:00 Problem: the subscription trap (chargeback, lost customer)
2. 0:25 AI negotiates and decides the split (show rationale)
3. 1:00 Guardrails + merchant lowers the donation, PayPal Payout executes (batch id)
4. 1:30 Donation stage (example recipient, receipt) and the evidence page: raw vs final split
5. 2:10 Ledger in AG Grid + PayPal dashboard match + Postman run all green
6. 2:40 Ethics slide: guardrails, human control, no silent fallback, honest limits

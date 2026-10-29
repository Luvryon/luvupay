# LuvuPay vision alignment

> **Mission:** Stopping involuntary churn and friendly fraud with an AI agent that calculates safe refunds and builds dispute evidence.

## How this codebase maps to the mission

| Mission pillar | Implementation in this tree |
|----------------|----------------------------|
| **Involuntary churn** (failed payment / missed cancel / retention) | AI Negotiator chat (`NegotiatorService`, `DemoNegotiatorController`, negotiator modal). Options: split payment, marketplace resale of remaining access, safe partial refund, pay full. Retention offers via `RetentionOfferService`. Second Chance flows. |
| **Friendly fraud / chargeback prevention** | Smart convert / AI-retained lifecycle, dispute **evidence builder** (`DemoDisputeEvidenceController`, evidence modal), exportable evidence pack for PayPal. |
| **AI agent** | Nebius-backed services: negotiator, churn prediction, dynamic pricing, subscription health, retention offers. Local fallbacks when API key absent (demo still works). |
| **Safe refunds** | Negotiator system prompt + policy caps (`safe_refund_max_minor`); never promises full refund above safe max. |
| **Dispute evidence** | Evidence pack builder with summary, points, raw metadata; downloadable for PayPal disputes. |

## Product surfaces (merchant console)

- Overview dashboard: wallet, ledger, retention tools (AJAX, soft background loading)
- AI Negotiate on transaction rows
- Evidence builder on disputed / high-risk flows
- Resale marketplace for unused access
- Second Chance / charity split paths
- Demo sandbox simulator (gated off in production by default)

## Security posture (this release)

- No `document.write` / unsafe `innerHTML` in app JS
- DomSafe helpers + escaped receipt HTML
- CSP + hardened headers
- Soft AJAX progress bar (no blocking full-page loaders)
- Upload allow-list (no SVG), UrlGuard SSRF protection

## Honest gaps / next hardening

- Alpine still requires CSP `unsafe-eval`
- Full production readiness depends on PayPal + Nebius keys, rate limits at the edge, and ops checklist in `SECURITY_HARDENING.md`

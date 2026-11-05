# AI Negotiation Evidence

Each Smart-Convert run writes one row to `smart_convert_runs` and gets a public, read-only page:

- `/evidence/smart-convert/{run-uuid}`: screenshot-ready page
- `/evidence/smart-convert/{run-uuid}.json`: same document, machine-readable
- `/evidence/latest`: newest executed run (only when `LUVUPAY_DEMO_SIMULATOR=true`)

The UUID is the access token (unguessable). The page contains no emails (masked), tokens or secrets, and is `noindex`.

## What is shown, and where it comes from

| Item | Source | Honesty rule |
|---|---|---|
| Model name, tokens, latency | the inference response (`model`, `usage.total_tokens`) and a server timer | `n/a` if the endpoint did not send it |
| Rationale steps | the model's own structured JSON answer | labelled "stated by the model", **not** a chain-of-thought dump |
| Provider trace | `reasoning_content` / `<think>` returned by the endpoint | shown only if present (enable with `SMART_CONVERT_REQUEST_REASONING=true`); never invented |
| Raw vs final split | model output untouched vs after policy guardrails | every adjustment is listed |
| `payout_batch_id` | `batch_header.payout_batch_id` from PayPal | null unless PayPal returned it; mock driver = "SIMULATED" |
| Batch status | `GET /v1/payments/payouts/{id}` after creation | falls back to the create response if the check fails |

## Paths and failure behaviour

- `path = ai`: a live model produced the split. `path = fallback`: only with `SMART_CONVERT_STRICT=false`; banner says "NOT produced by the AI model".
- Strict mode (default): AI failure → HTTP 502, run stored as `failed`, **no money moves, no ledger rows**. PayPal failure → same.
- Retries are idempotent: `sender_batch_id` = `sc-` + run id.

## Human oversight

`mode=propose` stops after the AI step. The merchant can **approve**, **reject**, or **lower** the donation (never raise it). Approvals use a compare-and-set, so a proposal can pay at most once. The model has no access to PayPal.

## Known limits

- Demo inputs (10 unused days, $30) are fixed in `DemoSmartConvertController`.
- The evidence page proves what LuvuPay recorded; the independent check is the PayPal Developer Dashboard API-call log.

## Donation stage

The evidence document (page and JSON) has a `donation` block: `recipient`, `amount_minor`, `share_percent`, `status` (`awaiting_approval`, `sent`, `simulated`, `none`, `not_sent`), `receipt_id` (`DON-…`), `payout_batch_id`, and `example_only`.

The recipient is illustrative by default (`SMART_CONVERT_CHARITY_EXAMPLE=true`, label from `SMART_CONVERT_CHARITY_LABEL`) and is tagged **EXAMPLE · SANDBOX** everywhere. A rejected or failed run reports `not_sent`; a proposal that is still waiting reports `awaiting_approval`. The block is derived from the stored run, so it can never claim more than the run recorded.

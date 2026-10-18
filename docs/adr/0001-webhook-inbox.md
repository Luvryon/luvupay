# ADR 1 — Durable webhook inbox
**Decision:** persist every verified webhook in `webhook_events` (unique `provider,event_id`) before acknowledging; process in a queued job.
**Why:** cache-based de-dupe loses state on flush/restart and processing in-request couples PayPal's timeout to our business logic.
**Consequence:** at-least-once delivery + idempotent consumers = effectively-once; dead-letter + replay give operators control.

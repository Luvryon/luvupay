# ADR 3 — Registration is all-or-nothing
**Decision:** merchant, owner, webhook endpoint and the verification email are one transaction; the email is sent synchronously (`sendNow`).
**Why:** a queued mail "succeeds" at signup and fails later, leaving an account nobody can verify and an email that is then "already registered".
**Consequence:** on any failure nothing exists and the user resubmits. Stale *unverified* signups for the same address are replaced.

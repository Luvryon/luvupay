# ADR 2 — Append-only double-entry ledger
**Decision:** every money movement posts a balanced journal (`ledger_journals` + `ledger_entries`), keyed by a unique reference.
**Why:** single-amount rows cannot prove conservation of money; a trial balance can. Immutability (model + PG trigger) makes tampering visible.
**Consequence:** corrections are reversing journals. Posting happens inside the same DB transaction as the business change.

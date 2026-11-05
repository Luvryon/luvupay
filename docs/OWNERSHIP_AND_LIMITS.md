# Marketplace: ownership signal and honest limits

## What is verified

When a seller lists, LuvuPay emails a **signed, expiring link** to the seller address. The link carries a keyed fingerprint of that address, so it is useless if the address on the listing changes. Clicking it:

- publishes a pending listing,
- records `ownership_level = email_signed_link` with hashed IP/user-agent (HMAC with the app key; raw values are never stored),
- releases a payout that was held for verification.

`verified_at` is set **only** by that click. (Earlier code set it to "now" whenever verification was switched off; that was fixed.)

## Automatic seller payout

After a sale, `SettleMarketplaceSaleJob` pays 90% (`MARKETPLACE_SELLER_BPS`) through PayPal Payouts:

- unverified address → status `held_unverified`, link emailed, **no money moves**;
- verified → `sending` → `sent`/`paid` with PayPal's batch id; ledger debits seller payable and credits clearing;
- exactly-once: compare-and-set + stable `sender_batch_id` (`mkt-<listing>`); stuck sales are re-queued every minute by `resale:maintain`.

## What this does NOT prove (say this in the submission)

- It proves **control of an email inbox** at one moment. It does **not** prove the seller owns the third-party subscription (Netflix, etc.) or that remaining days exist. Those are seller claims.
- A PayPal payout to an address the seller controls cannot be pulled back by LuvuPay once PayPal completes it.
- Stronger signals (provider OAuth, receipt/invoice check, KYC, PayPal-verified identity) are future work.

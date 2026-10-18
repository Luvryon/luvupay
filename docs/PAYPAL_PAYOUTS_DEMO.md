# PayPal Payouts — Merchant Withdrawal + Charity Split

## Environment Swap
| Env var | Sandbox | Production |
|---------|---------|------------|
| `PAYPAL_MERCHANT_SANDBOX_EMAIL` | `sb-****@business.example.com` | `finance@yourcompany.com` |
| `PAYPAL_CHARITY_SANDBOX_EMAIL` | second sandbox Business email | `donations@redcross.org` |

UI never shows these emails. Labels: **My Business PayPal Account** / **American Red Cross · Sandbox Verified**.

## Demo security (camera-safe)
1. Before recording, open two browser profiles (or tabs) already logged into PayPal Sandbox for merchant and charity Business accounts.
2. Keep those tabs in the background — do **not** type sandbox passwords on camera.
3. In LuvuPay: run **Withdraw to PayPal** then **Donate via Payouts API**.
4. Cut to the pre-logged-in Sandbox Activity tabs to show batch credits arriving.
5. Optional: PayPal Developer Dashboard → API calls → `POST /v1/payments/payouts`.

## Judge pitch (4 sentences)
LuviPay routes every PayPal Payout through a single `executePayout` path that selects the receiver exclusively from environment variables—merchant withdrawals use `PAYPAL_MERCHANT_SANDBOX_EMAIL`, charity donations use `PAYPAL_CHARITY_SANDBOX_EMAIL`. Ugly Sandbox Business addresses never appear in source or UI; the product only shows "My Business PayPal Account" and a "Sandbox Verified" charity badge. That Environment Swap architecture means production is a configuration change, not a code change: swap the `.env` values to real verified emails and the same Payouts API calls go live. Whether the money is a merchant's earned balance or a user's residual subscription value donated to charity, judges see one ethical, auditable money-movement surface built on PayPal Payouts.

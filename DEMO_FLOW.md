# LuvuPay Two-Sided Demo (2–3 min)

## Env (required)
```
APP_ENV=local
LUVUPAY_DEMO_SIMULATOR=true
LUVUPAY_PAYMENT_DRIVER=paypal   # or mock for offline
PAYPAL_MODE=sandbox
```
Then: `php artisan migrate && php artisan config:clear`

## Minute 0–1 · Merchant (B2B)
1. Login console → `/dashboard`
2. **New subscription checkout**
   - `mock` driver: pick subscribers → **Charge N subscribers** → instant, row ACTIVE, receipt emailed.
   - `paypal` driver: **Pay with PayPal** opens a popup → log in with a sandbox **personal (buyer)** account → approve ONCE.
     PayPal confirms → the dashboard turns the row ACTIVE, issues the invoice, emails the receipt, and the money shows in the
     sandbox **business** balance. Several subscribers = one approval each (click *Continue with next subscriber*).
   - Popup blocked? You are redirected to PayPal instead and the remaining subscribers resume after you return.
3. Select ACTIVE row → **Missed Cancellation** → GRACE_PERIOD
4. **Smart-Convert** (optional) · watch **Live event log** fill with PayPal-style events

## Minute 1–2 · Marketplace (B2C)
1. Open `/marketplace` (or sidebar **Public Marketplace**)
2. Show discount cards
3. **Sell Your Subscription** → Connect StreamFlix → Verify → Nebius AI listing → **Publish**
4. New card prepends to grid

## Why PayPal Developer "Event Log" may still be empty
That UI only logs **real HTTP calls to api-m.sandbox.paypal.com**.
LuvuPay **Live event log** / Webhook Debugger uses table `webhook_events` (always written on demo actions).

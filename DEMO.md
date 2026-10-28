# LuvuPay — Judge Demo Pack (90s + 3min)

## One-liner
**LuvuPay Smart-Convert** turns unused subscription time into store credit + matched charity via **Nebius (NVIDIA Nemotron)** negotiation and **PayPal Sandbox** payouts — so users keep value, merchants keep revenue, and chargebacks die.

## Stack proof (say this out loud)
- **AI:** Nebius Token Factory · NVIDIA Nemotron — live model name, tokens, latency on the evidence strip
- **Money movement:** PayPal REST **Sandbox** Payouts API (`PAYPAL_MODE=sandbox`)
- **Reliability:** webhook delivery center + retry job, idempotent ledger

## 90-second live script
1. Login `owner@demomerchant.test` / `SecurePass1` → Merchant Console  
2. Point at KPI **Chargebacks Prevented** + API usage meter  
3. Click **🧪 Run AI Smart-Convert Protocol**  
4. Narrate the log lines (Nebius → split → PayPal Payouts)  
5. Show **AI & PayPal evidence** strip: LIVE vs fallback, batch id  
6. Ledger row → lifecycle **SMART-CONVERTED** + Convert split column  
7. Optional: Webhook Deliveries → red row → **Retry**

## Win-win-win (one sentence each)
| Party | Win |
|-------|-----|
| **User** | Keeps $15 store credit instead of fighting for cash refund |
| **Merchant** | Retains ecosystem revenue; avoids chargeback + full cash-out |
| **LuvuPay** | $5 success fee + proven ethical retention infrastructure |

## Env for real integrations
```bash
LUVUPAY_DEMO_SIMULATOR=true
LUVUPAY_PAYMENT_DRIVER=paypal
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=...
PAYPAL_SECRET=...
NEBIUS_ENABLED=true
NEBIUS_API_KEY=...
NEBIUS_MODEL=nvidia/NVIDIA-Nemotron-3-Nano-30B-A3B
```

## `make demo`
```bash
make demo
php artisan serve
```

## Architecture (one slide)
```
User churn signal → LuvuPay Smart-Convert
        ↓
 Nebius Token Factory (Nemotron negotiate split)
        ↓
 Merchant Store Credit API  +  PayPal Sandbox Payout (charity)
        ↓
 Ledger + webhook_deliveries + invoice
```

## Video outline (2–3 min)
0:00 Problem (forgot-to-cancel → chargeback)  
0:30 Dashboard overview  
1:00 Smart-Convert protocol full run  
1:45 Evidence strip + SMART-CONVERTED row  
2:15 Webhook retry + invoice  
2:45 Close win-win-win

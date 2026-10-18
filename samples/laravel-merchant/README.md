# Integrate a Laravel product app with local LuvuPay

1. Run LuvuPay on port 8000; product app on 8001.
2. Register a merchant on LuvuPay; copy secret API key after email verify.
3. In the product `.env`:

```
LUVUPAY_BASE_URL=http://127.0.0.1:8000
LUVUPAY_SECRET_KEY=lp_live_...
LUVUPAY_WEBHOOK_SECRET=whsec_...
```

4. `config/services.php`:

```php
'luvupay' => [
    'base' => env('LUVUPAY_BASE_URL'),
    'secret' => env('LUVUPAY_SECRET_KEY'),
    'webhook_secret' => env('LUVUPAY_WEBHOOK_SECRET'),
],
```

5. Copy `LuvuPayClient.php` and `LuvuPayWebhookController.php` into the product app.
6. Register `POST /webhooks/luvupay` and exclude CSRF.
7. Set that URL in LuvuPay Console → Settings (confirm email code).
8. Call `createSubscription` when a user upgrades a plan.

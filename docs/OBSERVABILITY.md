# Monitoring, Logging & Observability (no Horizon)

## JSON logs
```env
LOG_CHANNEL=stack
LOG_STACK=single,json
```
Output: `storage/logs/laravel.json.log` (Monolog JsonFormatter).

## Health
`GET /internal/health` (Bearer METRICS_TOKEN) · `GET /up` — database, cache, Redis (only if configured), queue driver, payment provider.

## Queue without Horizon
```env
QUEUE_CONNECTION=database   # or redis later
```
```bash
php artisan queue:work --tries=3 --timeout=90
php artisan schedule:work
```

## Telescope (dev only, optional)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```
Gate `viewTelescope` to local/admin.

## Octane
See in-app `/docs/octane`. Use FrankenPHP/RoadRunner/Swoole. Restart Octane workers after config changes.

## E2E demo commands (mock)

```bash
php artisan demo:second-chance
php artisan demo:resale
```

Requires seeded merchant + `LUVUPAY_PAYMENT_DRIVER=mock`.

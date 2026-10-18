# LuvuPay — Quick Start (Local)

## Requirements
- PHP 8.2+
- Composer
- Node.js 18+ (optional, for Vite)
- SQLite (default) **or** PostgreSQL + Redis (recommended for full features)

## 1. Install PHP deps
```bash
composer install
```

## 2. Environment
Create it: `cp .env.example .env && php artisan key:generate` (defaults: SQLite + mock payments).
If you prefer Postgres:
```bash
cp .env.example .env
php artisan key:generate
# edit DB_* and REDIS_* then:
```

## 3. Migrate & seed
```bash
php artisan migrate --seed
```
The seeder prints a **demo merchant API key** — save it.

## 4. Run
```bash
# Terminal 1 — API + web
php artisan serve

# Terminal 2 (optional) — Vite for CSS/JS
npm install
npm run dev

# Terminal 3 (optional) — queues (when using redis/database queue)
php artisan queue:work
```

Or use the composer script:
```bash
composer run dev
```
(requires `concurrently` via npm)

## Useful endpoints
- Dashboard: http://localhost:8000/dashboard
- Health: http://localhost:8000/up
- Badge JS: http://localhost:8000/api/v1/badge.js
- API (Bearer `lp_live_...`): http://localhost:8000/api/v1/...

## Payment driver
- `LUVUPAY_PAYMENT_DRIVER=mock` (default) — no real money
- `paypal` — needs credentials in `.env`

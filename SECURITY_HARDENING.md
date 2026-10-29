# Security hardening — LuvuPay

## Must never ship
- `.env` and any `.env.*` (except `.env.example`) — blocked in `.gitignore`
- API secret keys, PayPal client secrets, session files, SQLite dumps

## Headers (`SecurityHeaders` middleware)
- `Content-Security-Policy`: default-src self; **no** `unsafe-inline` scripts; frame-ancestors none
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`: camera/mic/geo/payment disabled
- `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`
- HSTS (2y + preload) when HTTPS / production
- Strip `X-Powered-By` / `Server` when possible

## Auth
- Register: password min 10, mixed case, numbers, **symbols**, `uncompromised()` (Have I Been Pwned)
- Login / register / MFA throttled
- Session: HttpOnly always, encrypted by default, 60m lifetime default

## Uploads (company logo)
- PNG/JPEG/WebP only (**no SVG** — XSS vector)
- MIME sniff via `finfo`, extension allow-list, max 1 MB
- Random stored filename under `merchant-logos/{merchant_id}/`
- Throttle 10/min

## Demo simulator
- Gated by `demo.simulator` middleware (off in production by default)
- Throttled endpoints

## Deploy checklist
1. `APP_DEBUG=false`, `APP_ENV=production`
2. Strong `APP_KEY`, never commit `.env`
3. `SESSION_SECURE_COOKIE=true` behind HTTPS
4. `php artisan storage:link` only for public logos
5. Rate-limit at reverse proxy (nginx/cloudflare) as well

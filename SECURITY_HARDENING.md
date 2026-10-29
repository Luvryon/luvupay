# Security hardening — LuvuPay

## Priority: hard security (XSS / DOM injection)

### Forbidden in application code
- `insertAdjacentHTML()`
- `document.write()` / `document.writeln()`
- `eval()` / `new Function()`
- String-form `setTimeout("...")` / `setInterval("...")`
- `element.setAttribute('on*', ...)` from user/API data
- `location` / `window.location` assignment with unvalidated user URLs (esp. `javascript:`)
- `iframe.srcdoc`
- `document.createRange().createContextualFragment()`
- Raw `innerHTML` assignment with non-static content

### Required pattern
- Build UI with `createElement` + `textContent` + `addEventListener` only.
- Prefer `window.DomSafe.el` / `DomSafe.clear` / `DomSafe.esc` / `DomSafe.openPrintableHtml`.
- Receipt print/download: Blob URL only — never `document.write`.
- Server HTML (e.g. receipt body) must use Laravel `e()` / `htmlspecialchars`.

### Status (this release)
| Sink | Status |
|------|--------|
| `document.write` | **Removed** (dashboard print receipt → Blob URL) |
| `innerHTML` (docs-toc, toast, badge) | **Removed** — createElement / createElementNS |
| Fallback receipt HTML | Escaped via `DomSafe.esc` |
| AG Grid cell helper `h()` | Blocks `on*` attrs and `javascript:` URLs |
| Alpine.min.js | Third-party; still needs CSP `unsafe-eval` |

## Must never ship
- `.env` and any `.env.*` (except `.env.example`) — blocked in `.gitignore`
- API secret keys, PayPal client secrets, session files, SQLite dumps

## Headers (`SecurityHeaders` middleware)
- `Content-Security-Policy`: default-src self; **no** `unsafe-inline` scripts; frame-ancestors none
- `script-src 'self' 'unsafe-eval'` — Alpine 3 requirement (track removal)
- `style-src 'self' 'unsafe-inline'` — AG Grid / console theme
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

## SSRF / outbound URLs
- `UrlGuard` resolves host, blocks private/reserved IPs, rejects non-public targets

## Demo simulator
- Gated by `demo.simulator` middleware (off in production by default)
- Throttled endpoints

## Deploy checklist
1. `APP_DEBUG=false`, `APP_ENV=production`
2. Strong `APP_KEY`, never commit `.env`
3. `SESSION_SECURE_COOKIE=true` behind HTTPS
4. `php artisan storage:link` only for public logos
5. Rate-limit at reverse proxy (nginx/cloudflare) as well
6. Confirm CSP is enforced (not report-only) in production

## Remaining hardening goals
1. Remove Alpine or replace evaluator so CSP can drop `unsafe-eval`
2. Move AG Grid styles off inline if possible → drop `style-src 'unsafe-inline'`
3. Expand automated tests that fail CI on forbidden DOM sinks (grep for the list above)

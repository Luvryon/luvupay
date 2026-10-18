# Before git push

## 1. Confirm secrets are ignored
```bash
git check-ignore -v .env .env.production storage/*.key
git status --ignored
```

## 2. Never stage these
- `.env`, `.env.*` (except `.env.example`)
- `vendor/`, `node_modules/`
- `*.pem`, `*.key`, `auth.json`, `credentials.json`
- Database dumps `*.sql`, `*.sqlite`
- Real values for `PAYPAL_*`, `NEBIUS_API_KEY`, `APP_KEY`, `DB_PASSWORD`

## 3. Scan the index before push
```bash
git ls-files | grep -Ei '\.env$|\.pem$|\.key$|secret|credential|id_rsa'
# must print nothing

git grep -nE 'PAYPAL_SECRET=.+|NEBIUS_API_KEY=.+|APP_KEY=base64:' $(git ls-files) || true
```

## 4. Safe first commit flow
```bash
cp .env.example .env
php artisan key:generate
git add .
git status
git commit -m "Initial LuvuPay public demo"
git remote add origin git@github.com:YOU/luvupay.git
git push -u origin main
```

## 5. If a secret was committed by mistake
1. Rotate the secret immediately (PayPal, Nebius, DB, APP_KEY).
2. Remove from git history (git filter-repo / BFG). Deleting in a new commit is not enough.
3. Force-push only if you understand the risk.

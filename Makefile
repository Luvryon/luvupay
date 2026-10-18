.PHONY: up down test lint reconcile replay-dead logs
up:            ; docker compose up -d --build
down:          ; docker compose down
test:          ; php artisan test --compact
lint:          ; find app routes bootstrap config database tests -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null && vendor/bin/pint --test
reconcile:     ; php artisan luvi:reconcile
replay-dead:   ; php artisan luvi:webhooks:replay --status=dead
logs:          ; docker compose logs -f app worker scheduler

.PHONY: demo
demo:
	@echo "==> LuvuPay judge-ready demo"
	cp -n .env.example .env 2>/dev/null || true
	php artisan key:generate --force 2>/dev/null || true
	php artisan migrate --force
	php artisan db:seed --force
	@echo ""
	@echo "Login: owner@demomerchant.test / SecurePass1"
	@echo "Set LUVUPAY_DEMO_SIMULATOR=true  LUVUPAY_PAYMENT_DRIVER=paypal  PAYPAL_MODE=sandbox"
	@echo "Optional: NEBIUS_ENABLED=true NEBIUS_API_KEY=..."
	@echo "Then: php artisan serve"

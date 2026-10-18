# syntax=docker/dockerfile:1
# Front-end note: the Blade views serve the prebuilt public/css/app.css and public/js/*.js (no @vite).
# Regenerate CSS with `npm run build:css` and commit it; there is deliberately no Node stage that could fail silently.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader

FROM dunglas/frankenphp:1-php8.3 AS app
RUN install-php-extensions pdo_pgsql pdo_mysql redis pcntl opcache intl bcmath sodium zip
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && composer dump-autoload --no-dev --optimize
ENV APP_ENV=production APP_DEBUG=false OCTANE_SERVER=frankenphp
USER www-data
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://localhost:8000/up || exit 1
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]

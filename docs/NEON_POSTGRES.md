# Migrate LuvuPay from XAMPP (MySQL) → Neon (PostgreSQL)

## 1. Create a Neon project
1. Go to https://console.neon.tech and create a project.
2. Open **Connection details** → copy:
   - Host (`ep-….aws.neon.tech`)
   - Database (often `neondb`)
   - User (often `neondb_owner`)
   - Password
3. Prefer the **pooled** connection string for Laravel web traffic.

## 2. PHP extension (Windows / XAMPP PHP)
Neon uses PostgreSQL. Enable PDO pgsql in `php.ini`:

```ini
extension=pdo_pgsql
extension=pgsql
```

Restart Apache / `php artisan serve` after enabling.

Verify:

```bash
php -m | findstr pgsql
```

## 3. `.env` (replace MySQL/XAMPP block)

```env
DB_CONNECTION=pgsql
DB_HOST=ep-YOUR-ENDPOINT.region.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=your_neon_password
DB_SSLMODE=require
```

Or use URL form (Laravel reads `DATABASE_URL` if set):

```env
DATABASE_URL="postgresql://neondb_owner:PASSWORD@ep-XXXX.region.aws.neon.tech/neondb?sslmode=require"
DB_CONNECTION=pgsql
```

Remove / comment old XAMPP MySQL lines:

```env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=luvupay
# DB_USERNAME=root
# DB_PASSWORD=
```

## 4. Clear config & migrate

```bash
php artisan config:clear
php artisan migrate --force
```

Fresh empty Neon DB:

```bash
php artisan migrate:fresh --force
```

Optional demo seed:

```bash
php artisan db:seed
```

## 5. Session / cache (optional but good on Neon)
If you used `SESSION_DRIVER=database` or `CACHE_STORE=database`, migrations already create those tables.  
For local speed you can keep:

```env
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

## 6. Common errors

| Error | Fix |
|-------|-----|
| `could not find driver` | Enable `pdo_pgsql` in php.ini |
| `SSL connection required` | Set `DB_SSLMODE=require` |
| `password authentication failed` | Reset password in Neon console; no special URL-encoding issues if using separate DB_* vars |
| `Connection timed out` | Check host, allowlist (Neon is public by default), firewall |
| Old MySQL data missing | Neon starts empty — run migrations; export/import separately if you need old rows |

## 7. Data migration from MySQL (optional)
If you need existing XAMPP data:

1. Export MySQL: `mysqldump -u root luvupay > luvupay.sql`
2. Convert / import with a tool (e.g. `pgloader`):

```bash
pgloader mysql://root@127.0.0.1/luvupay postgresql://USER:PASS@HOST/neondb
```

For a hackathon demo, **fresh migrate on Neon** is usually enough.

## 8. Stop using XAMPP MySQL
You can keep XAMPP only for PHP, or switch to:

```bash
php artisan serve
```

No MySQL service required once Neon is configured.

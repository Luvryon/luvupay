<?php
/**
 * Neon PDO diagnostic — php scripts/test-neon-pdo.php
 * Shows whether password was loaded (length only, never prints the secret).
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$envFile = $root . DIRECTORY_SEPARATOR . '.env';

function loadEnvFile(string $path): array
{
    $out = [];
    if (! is_file($path)) {
        return $out;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return $out;
    }
    foreach ($lines as $num => $line) {
        $raw = $line;
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        // Strip matching quotes
        if (strlen($v) >= 2) {
            $q = $v[0];
            if (($q === '"' || $q === "'") && str_ends_with($v, $q)) {
                $v = substr($v, 1, -1);
            }
        }
        $out[$k] = $v;
        $out['_line_'.$k] = $num + 1;
    }

    return $out;
}

$env = loadEnvFile($envFile);

$host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? '');
$port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '5432');
$db   = getenv('DB_DATABASE') ?: ($env['DB_DATABASE'] ?? 'neondb');
$user = getenv('DB_USERNAME') ?: ($env['DB_USERNAME'] ?? '');
$pass = getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== ''
    ? (string) getenv('DB_PASSWORD')
    : (string) ($env['DB_PASSWORD'] ?? '');
$ssl  = getenv('DB_SSLMODE') ?: ($env['DB_SSLMODE'] ?? 'require');
$endpoint = getenv('DB_ENDPOINT') ?: ($env['DB_ENDPOINT'] ?? '');

if ($endpoint === '' && preg_match('/^(ep-[a-z0-9-]+?)(?:-pooler)?\./i', $host, $m)) {
    $endpoint = $m[1];
}
$endpoint = preg_replace('/-pooler$/i', '', (string) $endpoint);

echo "=== Neon PDO diagnostic ===\n";
echo ".env path: $envFile\n";
echo ".env exists: ".(is_file($envFile) ? 'yes' : 'NO')."\n";
echo "php: ".PHP_VERSION."\n";
echo "pdo_pgsql: ".(extension_loaded('pdo_pgsql') ? 'yes' : 'NO')."\n";
echo "host: $host\n";
echo "port: $port\n";
echo "database: $db\n";
echo "username: $user\n";
echo "sslmode: $ssl\n";
echo "endpoint: $endpoint\n";
echo "password length: ".strlen($pass)."\n";
echo "password empty: ".($pass === '' ? 'YES ← THIS IS THE PROBLEM' : 'no')."\n";
if (isset($env['_line_DB_PASSWORD'])) {
    echo "DB_PASSWORD found at .env line: ".$env['_line_DB_PASSWORD']."\n";
} else {
    echo "DB_PASSWORD key: NOT FOUND in .env file\n";
}

if ($pass === '') {
    echo "\nFIX:\n";
    echo "  1. Open .env in the project root (same folder as artisan)\n";
    echo "  2. Set exactly: DB_PASSWORD=your_neon_password\n";
    echo "  3. If password contains # @ \" space → use double quotes: DB_PASSWORD=\"your#pass\"\n";
    echo "  4. No spaces around =\n";
    echo "  5. Save file, run this script again\n";
    echo "\nOr pass password only for this test (PowerShell):\n";
    echo "  \$env:DB_PASSWORD=\"your_password\"; php scripts/test-neon-pdo.php\n";
    exit(1);
}

if (! extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "FAIL: enable extension=pdo_pgsql in php.ini\n");
    exit(1);
}

$dsnList = [];
if ($endpoint !== '') {
    $dsnList['options endpoint%3D'] = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl};options=endpoint%3D{$endpoint}";
    $dsnList['options endpoint='] = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl};options=endpoint={$endpoint}";
}
$dsnList['ssl only'] = "pgsql:host={$host};port={$port};dbname={$db};sslmode={$ssl}";

foreach ($dsnList as $label => $dsn) {
    echo "\n--- $label ---\n";
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "OK: ".$pdo->query('select version()')->fetchColumn()."\n";
        echo "SUCCESS format: $label\n";
        exit(0);
    } catch (Throwable $e) {
        echo "FAIL: ".$e->getMessage()."\n";
    }
}

echo "\nAll attempts failed. Check password in Neon console (reset if unsure).\n";
exit(1);

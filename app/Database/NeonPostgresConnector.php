<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;
use PDO;
use PDOException;

/**
 * Neon connector for Laravel.
 * - Parses DATABASE_URL / DB_URL
 * - No options=endpoint (breaks PDO)
 * - Prefer direct host (strip -pooler) so migrations work
 * - ATTR_EMULATE_PREPARES for PgBouncer when pooler is used
 */
class NeonPostgresConnector extends PostgresConnector
{
    public function connect(array $config): PDO
    {
        $config = $this->applyUrl($config);

        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($password === '') {
            throw new PDOException(
                'Empty DB password. Put password inside DATABASE_URL or set DB_PASSWORD in .env'
            );
        }

        // Migrations + DDL: prefer direct endpoint (pooler breaks multi-statement transactions)
        $host = (string) ($config['host'] ?? '');
        if (str_contains($host, '-pooler.')) {
            $config['host'] = str_replace('-pooler.', '.', $host);
        }

        $dsn = $this->buildDsn($config);

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
    }

    protected function getDsn(array $config): string
    {
        $config = $this->applyUrl($config);
        $host = (string) ($config['host'] ?? '');
        if (str_contains($host, '-pooler.')) {
            $config['host'] = str_replace('-pooler.', '.', $host);
        }

        return $this->buildDsn($config);
    }

    private function applyUrl(array $config): array
    {
        $url = env('DATABASE_URL') ?: env('DB_URL') ?: ($config['url'] ?? null);

        if (! is_string($url) || trim($url) === '') {
            if (env('DB_PASSWORD')) {
                $config['password'] = (string) env('DB_PASSWORD');
            }
            if (env('DB_USERNAME')) {
                $config['username'] = (string) env('DB_USERNAME');
            }
            if (env('DB_HOST')) {
                $config['host'] = (string) env('DB_HOST');
            }
            if (env('DB_DATABASE')) {
                $config['database'] = (string) env('DB_DATABASE');
            }
            if (env('DB_PORT')) {
                $config['port'] = env('DB_PORT');
            }
            $config['sslmode'] = env('DB_SSLMODE', 'require');

            return $config;
        }

        $url = trim($url);
        if (str_starts_with($url, 'postgres://')) {
            $url = 'postgresql://'.substr($url, 11);
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $config;
        }

        if (! empty($parts['host'])) {
            $config['host'] = $parts['host'];
        }
        if (! empty($parts['port'])) {
            $config['port'] = $parts['port'];
        } else {
            $config['port'] = $config['port'] ?? '5432';
        }
        if (! empty($parts['path'])) {
            $config['database'] = ltrim($parts['path'], '/');
        }
        if (isset($parts['user'])) {
            $config['username'] = rawurldecode($parts['user']);
        }
        if (array_key_exists('pass', $parts)) {
            $config['password'] = rawurldecode((string) $parts['pass']);
        }

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (! empty($q['sslmode'])) {
                $config['sslmode'] = $q['sslmode'];
            }
            // ignore options=endpoint
        }

        $config['sslmode'] = $config['sslmode'] ?? 'require';
        unset($config['endpoint']);
        if (isset($config['options']) && ! is_array($config['options'])) {
            unset($config['options']);
        }

        return $config;
    }

    private function buildDsn(array $config): string
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $database = $config['database'] ?? '';
        $sslmode = $config['sslmode'] ?? 'require';

        return "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
    }
}
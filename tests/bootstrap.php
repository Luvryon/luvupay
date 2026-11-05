<?php

/**
 * Safety net: the suite uses RefreshDatabase, which drops and recreates EVERY table.
 * Refuse to start unless the target is a dedicated database whose name ends in "_test"
 * (or the in-memory SQLite database). This makes it impossible to wipe the app database
 * (for example a Neon DB_URL from .env) by running `php artisan test`.
 */
require __DIR__.'/../vendor/autoload.php';

$connection = (string) (getenv('DB_CONNECTION') ?: '');
$database = (string) (getenv('DB_DATABASE') ?: '');
$url = (string) (getenv('DB_URL') ?: getenv('DATABASE_URL') ?: '');

$safe = $connection === 'sqlite'
    ? $database === ':memory:'
    : ($url === '' && preg_match('/_test$/', $database) === 1);

if (! $safe) {
    fwrite(STDERR, "\nRefusing to run the test suite: it wipes every table.\n"
        ."Use a dedicated database whose name ends in _test and keep DB_URL empty.\n"
        ."Got connection='{$connection}' database='{$database}'"
        .($url !== '' ? ' (DB_URL is set)' : '')."\n\n");
    exit(1);
}

<?php

/**
 * Laravel's env-file selection (Illuminate\Foundation\Bootstrap\
 * LoadEnvironmentVariables) picks .env.testing over .env once APP_ENV is
 * "testing" — but compose.yaml's "api" service sets DB_HOST/DB_DATABASE/etc
 * directly as real container environment variables, and Dotenv's repository
 * is immutable (Illuminate\Support\Env::getRepository()): it will never let
 * a value loaded from a file override one that's already set in the process
 * environment. So .env.testing's DB_* values are silently ignored unless we
 * also clear/override them here, directly via putenv(), before Laravel ever
 * boots. (Found by tracing actual_db through every test class — every one
 * correctly picked .env.testing, yet every one connected to the dev
 * database until this file started overriding these explicitly.)
 */
$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'postgres',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'retiro_casais_testing',
    'DB_USERNAME' => 'retiro_casais',
    'DB_PASSWORD' => 'change-me-please',
];

foreach ($testingEnv as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require __DIR__.'/../vendor/autoload.php';

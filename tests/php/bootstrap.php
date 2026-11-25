<?php

declare(strict_types=1);

use App\Database;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

const TEST_DATABASE_PATTERN = '/^[A-Za-z0-9_]+_test$/';
const DEFAULT_TEST_DATABASE = 'redemc_test';

Database::loadEnvFile();
$testDatabase = Database::env('DB_TEST_NAME', DEFAULT_TEST_DATABASE);

if (preg_match(TEST_DATABASE_PATTERN, $testDatabase) !== 1) {
    fwrite(STDERR, "Refusing to run tests: DB_TEST_NAME must end in \"_test\" (got \"{$testDatabase}\")." . PHP_EOL);
    exit(1);
}

putenv("DB_NAME={$testDatabase}");

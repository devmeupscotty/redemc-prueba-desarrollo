<?php

declare(strict_types=1);

use App\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

const SCHEMA_FILE = __DIR__ . '/schema.sql';
const DATABASE_NAME_PATTERN = '/^[A-Za-z0-9_]+$/';

Database::loadEnvFile();
$databaseName = Database::env('DB_NAME');

if (preg_match(DATABASE_NAME_PATTERN, $databaseName) !== 1) {
    fwrite(STDERR, "Nombre de base inválido: {$databaseName}" . PHP_EOL);
    exit(1);
}

Database::connect('')->exec(
    "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);
Database::connect()->exec(file_get_contents(SCHEMA_FILE));

echo "Base \"{$databaseName}\" lista con la tabla person." . PHP_EOL;

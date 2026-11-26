<?php

declare(strict_types=1);

use App\Constants;
use App\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

Database::loadEnvFile();
$databaseName = Database::env(Constants::ENV_DB['NAME']);

try {
    Database::createDatabase($databaseName);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, "Nombre de base inválido: {$databaseName}" . PHP_EOL);
    exit(1);
}

Database::applySchema(Database::connect());

echo "Base \"{$databaseName}\" lista con la tabla person." . PHP_EOL;

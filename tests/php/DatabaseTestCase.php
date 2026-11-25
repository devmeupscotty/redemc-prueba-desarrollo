<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    private const SCHEMA_FILE = __DIR__ . '/../../database/schema.sql';

    private static ?PDO $pdo = null;

    protected static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $databaseName = Database::env('DB_NAME');
            Database::connect('')->exec(
                "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            self::$pdo = Database::connect();
            self::$pdo->exec(file_get_contents(self::SCHEMA_FILE));
        }

        return self::$pdo;
    }

    protected function setUp(): void
    {
        self::pdo()->exec('TRUNCATE TABLE person');
    }
}

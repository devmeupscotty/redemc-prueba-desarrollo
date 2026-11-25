<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    private static ?PDO $pdo = null;

    protected static function pdo(): PDO
    {
        if (self::$pdo === null) {
            Database::createDatabase(Database::env('DB_NAME'));
            self::$pdo = Database::connect();
            Database::applySchema(self::$pdo);
        }

        return self::$pdo;
    }

    protected function setUp(): void
    {
        self::pdo()->exec('TRUNCATE TABLE person');
    }
}

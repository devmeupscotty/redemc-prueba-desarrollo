<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

final class Database
{
    public static function connect(?string $databaseName = null): PDO
    {
        self::loadEnvFile();

        $databaseName ??= self::env('DB_NAME');
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            self::env('DB_HOST'),
            self::env('DB_PORT', Constants::DB_DEFAULT_PORT),
            Constants::DB_CHARSET,
        );
        if ($databaseName !== '') {
            $dsn .= ';dbname=' . $databaseName;
        }

        $pdo = new PDO($dsn, self::env('DB_USER'), self::env('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec(sprintf("SET time_zone = '%s'", Constants::DB_TIME_ZONE));

        return $pdo;
    }

    public static function env(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException("Missing environment variable: {$key}");
    }

    /**
     * Real environment variables (e.g. Railway's) always take precedence over the file.
     */
    public static function loadEnvFile(?string $path = null): void
    {
        $path ??= dirname(__DIR__) . DIRECTORY_SEPARATOR . Constants::ENV_FILE;
        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }
}

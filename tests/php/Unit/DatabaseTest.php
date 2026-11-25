<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    private const KEY = 'REDEMC_UNIT_VALUE';
    private const OTHER_KEY = 'REDEMC_UNIT_OTHER';
    private const URL_KEY = 'REDEMC_UNIT_URL';

    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = tempnam(sys_get_temp_dir(), 'redemc-env');
    }

    protected function tearDown(): void
    {
        foreach ([self::KEY, self::OTHER_KEY, self::URL_KEY] as $key) {
            putenv($key);
        }
        unlink($this->envFile);
    }

    public function testEnvReturnsTheEnvironmentValue(): void
    {
        putenv(self::KEY . '=from-environment');

        self::assertSame('from-environment', Database::env(self::KEY));
    }

    public function testEnvFallsBackToTheDefaultWhenMissingOrEmpty(): void
    {
        self::assertSame('fallback', Database::env(self::KEY, 'fallback'));

        putenv(self::KEY . '=');

        self::assertSame('', Database::env(self::KEY, ''));
    }

    public function testEnvRequiresValuesWithoutDefault(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(self::KEY);

        Database::env(self::KEY);
    }

    public function testLoadEnvFileParsesKeyValueLines(): void
    {
        file_put_contents($this->envFile, implode(PHP_EOL, [
            '# comment',
            '',
            'not a variable',
            ' ' . self::KEY . ' = spaced value ',
            self::URL_KEY . '=mysql://user:pa=ss@host:3306/db',
        ]));

        Database::loadEnvFile($this->envFile);

        self::assertSame('spaced value', getenv(self::KEY));
        self::assertSame('mysql://user:pa=ss@host:3306/db', getenv(self::URL_KEY));
    }

    public function testRealEnvironmentVariablesWinOverTheFile(): void
    {
        putenv(self::KEY . '=from-railway');
        file_put_contents($this->envFile, self::KEY . '=from-file' . PHP_EOL . self::OTHER_KEY . '=from-file');

        Database::loadEnvFile($this->envFile);

        self::assertSame('from-railway', getenv(self::KEY));
        self::assertSame('from-file', getenv(self::OTHER_KEY));
    }

    public function testMissingEnvFileIsIgnored(): void
    {
        Database::loadEnvFile($this->envFile . '.missing');

        self::assertFalse(getenv(self::KEY));
    }
}

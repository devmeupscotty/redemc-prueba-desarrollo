<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Constants;
use App\PersonValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\DatabaseTestCase;

final class ApiTest extends DatabaseTestCase
{
    private const HOST = '127.0.0.1';
    private const PORT = 8089;
    private const STARTUP_TIMEOUT_SECONDS = 10;
    private const REQUEST_TIMEOUT_SECONDS = 30;
    private const PROJECT_ROOT = __DIR__ . '/../../..';

    private const VALID_PERSON = [
        Constants::FIELD['NAME'] => 'Ana Pérez',
        Constants::FIELD['EMAIL'] => 'ana@mail.com',
        Constants::FIELD['PHONE'] => '097680102',
    ];

    /** @var resource|null */
    private static $server = null;

    public static function setUpBeforeClass(): void
    {
        self::pdo();

        if (self::isPortOpen()) {
            throw new RuntimeException('Port ' . self::PORT . ' is already in use; stop that process before running the API tests.');
        }

        $environment = getenv();
        foreach (Constants::ENV_DB as $variable) {
            $value = getenv($variable);
            if ($value !== false) {
                $environment[$variable] = $value;
            }
        }

        $root = realpath(self::PROJECT_ROOT);
        $log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'redemc-api-test.log';
        self::$server = proc_open(
            [PHP_BINARY, '-S', self::HOST . ':' . self::PORT, '-t', 'public', 'public/index.php'],
            [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']],
            $pipes,
            $root,
            $environment,
        );

        $deadline = time() + self::STARTUP_TIMEOUT_SECONDS;
        while (time() < $deadline) {
            if (self::isPortOpen()) {
                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException('The PHP built-in server did not start; see ' . $log);
    }

    private static function isPortOpen(): bool
    {
        $socket = @fsockopen(self::HOST, self::PORT);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function testHomePageEmbedsTheServerValidationRules(): void
    {
        $response = self::request(Constants::METHOD['GET'], Constants::ROUTE['HOME'], raw: true);

        self::assertSame(Constants::HTTP['OK'], $response['status']);
        self::assertStringStartsWith('text/html', $response['headers']['content-type']);
        self::assertSame(1, preg_match('#<script type="application/json" id="app-config">(.+?)</script>#s', $response['body'], $match));

        $config = json_decode($match[1], true);
        self::assertSame(Constants::PATTERNS, $config[Constants::CONFIG['PATTERNS']]);
        self::assertSame(Constants::ROUTE['PERSONS'], $config[Constants::CONFIG['ENDPOINT']]);
        self::assertSame(Constants::PAGINATION['PAGE_SIZE'], $config[Constants::CONFIG['PAGE_SIZE']]);
        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $config[Constants::CONFIG['UNPROCESSABLE_STATUS']]);
        self::assertSame(Constants::ERROR_MESSAGE['SERVER_ERROR'], $config[Constants::CONFIG['SERVER_ERROR']]);
    }

    public function testHomePageLayoutUsesNoTables(): void
    {
        $response = self::request(Constants::METHOD['GET'], Constants::ROUTE['HOME'], raw: true);

        self::assertDoesNotMatchRegularExpression('/<(table|tr|td|th)\b/i', $response['body']);
    }

    public function testHomePageOnlyAcceptsGet(): void
    {
        $response = self::request(Constants::METHOD['POST'], Constants::ROUTE['HOME']);

        self::assertSame(Constants::HTTP['METHOD_NOT_ALLOWED'], $response['status']);
        self::assertSame(implode(', ', Constants::ROUTE_METHODS[Constants::ROUTE['HOME']]), $response['headers']['allow']);
    }

    public static function readRoutes(): array
    {
        return ['home' => [Constants::ROUTE['HOME']], 'persons' => [Constants::ROUTE['PERSONS']]];
    }

    #[DataProvider('readRoutes')]
    public function testHeadIsAnsweredLikeGetWithoutBody(string $route): void
    {
        $response = self::request(Constants::METHOD['HEAD'], $route, raw: true);

        self::assertSame(Constants::HTTP['OK'], $response['status']);
        self::assertSame('', $response['body']);
    }

    public function testListReturnsJsonWithPaginationMeta(): void
    {
        $response = self::request(Constants::METHOD['GET'], Constants::ROUTE['PERSONS']);

        self::assertSame(Constants::HTTP['OK'], $response['status']);
        self::assertStringStartsWith('application/json', $response['headers']['content-type']);
        self::assertSame('no-store', $response['headers']['cache-control']);
        self::assertSame([], $response['json'][Constants::RESPONSE['DATA']]);
        self::assertSame(
            [Constants::META['PAGE'] => 1, Constants::META['PER_PAGE'] => Constants::PAGINATION['PAGE_SIZE'], Constants::META['TOTAL'] => 0, Constants::META['TOTAL_PAGES'] => 1],
            $response['json'][Constants::RESPONSE['META']],
        );
    }

    public function testCreatingAPersonAddsItFirstInTheList(): void
    {
        self::post([Constants::FIELD['EMAIL'] => 'primero@mail.com', Constants::FIELD['PHONE'] => ''] + self::VALID_PERSON);
        $before = self::request(Constants::METHOD['GET'], Constants::ROUTE['PERSONS'])['json'];

        $created = self::post(self::VALID_PERSON);
        $after = self::request(Constants::METHOD['GET'], Constants::ROUTE['PERSONS'])['json'];

        self::assertSame(Constants::HTTP['CREATED'], $created['status']);
        $person = $created['json'][Constants::RESPONSE['DATA']];
        self::assertIsInt($person[Constants::FIELD['ID']]);
        self::assertSame('Ana Pérez', $person[Constants::FIELD['NAME']]);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $person[Constants::FIELD['CREATED_AT']]);

        self::assertSame($before[Constants::RESPONSE['META']][Constants::META['TOTAL']] + 1, $after[Constants::RESPONSE['META']][Constants::META['TOTAL']]);
        self::assertCount(count($before[Constants::RESPONSE['DATA']]) + 1, $after[Constants::RESPONSE['DATA']]);
        self::assertSame($person, $after[Constants::RESPONSE['DATA']][0]);
    }

    public function testCreatedPersonIsNormalized(): void
    {
        $response = self::post([
            Constants::FIELD['NAME'] => '  María   José ',
            Constants::FIELD['EMAIL'] => ' Maria.Jose@MAIL.com ',
            Constants::FIELD['PHONE'] => '',
        ]);

        $person = $response['json'][Constants::RESPONSE['DATA']];
        self::assertSame('María José', $person[Constants::FIELD['NAME']]);
        self::assertSame('maria.jose@mail.com', $person[Constants::FIELD['EMAIL']]);
        self::assertNull($person[Constants::FIELD['PHONE']]);
    }

    public static function invalidSubmissions(): array
    {
        return [
            'missing name' => [[Constants::FIELD['NAME'] => ''], Constants::FIELD['NAME'], Constants::RULE['REQUIRED']],
            'whitespace-only name' => [[Constants::FIELD['NAME'] => '    '], Constants::FIELD['NAME'], Constants::RULE['REQUIRED']],
            'name with digits' => [[Constants::FIELD['NAME'] => 'Ana 2'], Constants::FIELD['NAME'], Constants::RULE['FORMAT']],
            'name with markup' => [[Constants::FIELD['NAME'] => '<script>alert(1)</script>'], Constants::FIELD['NAME'], Constants::RULE['FORMAT']],
            'name too long' => [[Constants::FIELD['NAME'] => str_repeat('a', 101)], Constants::FIELD['NAME'], Constants::RULE['LENGTH']],
            'missing email' => [[Constants::FIELD['EMAIL'] => ''], Constants::FIELD['EMAIL'], Constants::RULE['REQUIRED']],
            'malformed email' => [[Constants::FIELD['EMAIL'] => 'ana@mail'], Constants::FIELD['EMAIL'], Constants::RULE['FORMAT']],
            'phone with spaces' => [[Constants::FIELD['PHONE'] => '097 680 102'], Constants::FIELD['PHONE'], Constants::RULE['FORMAT']],
            'phone with country code' => [[Constants::FIELD['PHONE'] => '+59897680102'], Constants::FIELD['PHONE'], Constants::RULE['FORMAT']],
            'phone not starting with 09' => [[Constants::FIELD['PHONE'] => '087680102'], Constants::FIELD['PHONE'], Constants::RULE['FORMAT']],
        ];
    }

    /** @param array<string, string> $override */
    #[DataProvider('invalidSubmissions')]
    public function testInvalidSubmissionsAreRejectedWithASpecificMessage(array $override, string $field, string $rule): void
    {
        $response = self::post($override + self::VALID_PERSON);

        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $response['status']);
        self::assertSame([$field => PersonValidator::message($field, $rule)], $response['json'][Constants::RESPONSE['ERRORS']]);
        self::assertSame(0, $this->total());
    }

    public function testAllErrorsAreReturnedAtOnce(): void
    {
        $response = self::post([Constants::FIELD['NAME'] => '', Constants::FIELD['EMAIL'] => 'x', Constants::FIELD['PHONE'] => '1']);

        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $response['status']);
        self::assertSame(Constants::VALIDATED_FIELDS, array_keys($response['json'][Constants::RESPONSE['ERRORS']]));
    }

    public function testDuplicateEmailIsRejectedIgnoringCase(): void
    {
        self::post(self::VALID_PERSON);

        $response = self::post([Constants::FIELD['EMAIL'] => 'ANA@Mail.com', Constants::FIELD['PHONE'] => '091111111'] + self::VALID_PERSON);

        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $response['status']);
        self::assertSame(
            [Constants::FIELD['EMAIL'] => PersonValidator::message(Constants::FIELD['EMAIL'], Constants::RULE['DUPLICATE'])],
            $response['json'][Constants::RESPONSE['ERRORS']],
        );
        self::assertSame(1, $this->total());
    }

    public function testDuplicatePhoneIsRejected(): void
    {
        self::post(self::VALID_PERSON);

        $response = self::post([Constants::FIELD['EMAIL'] => 'otra@mail.com'] + self::VALID_PERSON);

        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $response['status']);
        self::assertSame(
            [Constants::FIELD['PHONE'] => PersonValidator::message(Constants::FIELD['PHONE'], Constants::RULE['DUPLICATE'])],
            $response['json'][Constants::RESPONSE['ERRORS']],
        );
    }

    public function testSeveralPeopleCanOmitThePhone(): void
    {
        $first = self::post([Constants::FIELD['PHONE'] => ''] + self::VALID_PERSON);
        $second = self::post([Constants::FIELD['EMAIL'] => 'luis@mail.com', Constants::FIELD['PHONE'] => ''] + self::VALID_PERSON);

        self::assertSame(Constants::HTTP['CREATED'], $first['status']);
        self::assertSame(Constants::HTTP['CREATED'], $second['status']);
    }

    public function testPaginatesAndClampsOutOfRangePages(): void
    {
        $values = [];
        foreach (range('a', 'i') as $letter) {
            $values[] = sprintf("('Persona %s', 'persona.%s@mail.com')", $letter, $letter);
        }
        self::pdo()->exec('INSERT INTO person (name, email) VALUES ' . implode(', ', $values));

        $second = self::request(Constants::METHOD['GET'], Constants::ROUTE['PERSONS'] . '?page=2')['json'];
        $beyond = self::request(Constants::METHOD['GET'], Constants::ROUTE['PERSONS'] . '?page=99')['json'];

        self::assertCount(1, $second[Constants::RESPONSE['DATA']]);
        self::assertSame('Persona a', $second[Constants::RESPONSE['DATA']][0][Constants::FIELD['NAME']]);
        self::assertSame([Constants::META['PAGE'] => 2, Constants::META['PER_PAGE'] => Constants::PAGINATION['PAGE_SIZE'], Constants::META['TOTAL'] => 9, Constants::META['TOTAL_PAGES'] => 2], $second[Constants::RESPONSE['META']]);
        self::assertSame(2, $beyond[Constants::RESPONSE['META']][Constants::META['PAGE']]);
    }

    public function testCrossSitePostsAreRejected(): void
    {
        $response = self::request(
            Constants::METHOD['POST'],
            Constants::ROUTE['PERSONS'],
            self::VALID_PERSON,
            headers: ['Sec-Fetch-Site: cross-site'],
        );

        self::assertSame(Constants::HTTP['FORBIDDEN'], $response['status']);
        self::assertSame([Constants::RESPONSE['MESSAGE'] => Constants::ERROR_MESSAGE['CROSS_SITE']], $response['json']);
        self::assertSame(0, $this->total());
    }

    public function testSameOriginPostsAreAccepted(): void
    {
        $response = self::request(
            Constants::METHOD['POST'],
            Constants::ROUTE['PERSONS'],
            self::VALID_PERSON,
            headers: ['Sec-Fetch-Site: same-origin'],
        );

        self::assertSame(Constants::HTTP['CREATED'], $response['status']);
    }

    public static function unsupportedMethods(): array
    {
        return ['PUT' => ['PUT'], 'DELETE' => ['DELETE'], 'PATCH' => ['PATCH']];
    }

    #[DataProvider('unsupportedMethods')]
    public function testUnsupportedMethodsAreRejected(string $method): void
    {
        $response = self::request($method, Constants::ROUTE['PERSONS']);

        self::assertSame(Constants::HTTP['METHOD_NOT_ALLOWED'], $response['status']);
        self::assertSame(implode(', ', Constants::ROUTE_METHODS[Constants::ROUTE['PERSONS']]), $response['headers']['allow']);
    }

    public static function privatePaths(): array
    {
        return [
            'source' => ['/src/Constants.php'],
            'parent traversal' => ['/../src/Constants.php'],
            'environment' => ['/.env'],
            'composer' => ['/composer.json'],
            'schema' => ['/database/schema.sql'],
            'unknown api' => ['/api/unknown'],
        ];
    }

    #[DataProvider('privatePaths')]
    public function testPrivatePathsAreNotExposed(string $path): void
    {
        $response = self::request(Constants::METHOD['GET'], $path);

        self::assertSame(Constants::HTTP['NOT_FOUND'], $response['status']);
        self::assertSame([Constants::RESPONSE['MESSAGE'] => Constants::ERROR_MESSAGE['NOT_FOUND']], $response['json']);
    }

    private function total(): int
    {
        return self::request(Constants::METHOD['GET'], Constants::ROUTE['PERSONS'])['json'][Constants::RESPONSE['META']][Constants::META['TOTAL']];
    }

    /** @param array<string, string> $fields */
    private static function post(array $fields): array
    {
        return self::request(Constants::METHOD['POST'], Constants::ROUTE['PERSONS'], $fields);
    }

    /**
     * @param array<string, string> $fields
     * @param list<string> $headers
     * @return array{status: int, headers: array<string, string>, json: mixed, body: string}
     */
    private static function request(
        string $method,
        string $path,
        array $fields = [],
        bool $raw = false,
        array $headers = [],
    ): array {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", ['Content-Type: application/x-www-form-urlencoded', ...$headers]) . "\r\n",
            'content' => http_build_query($fields),
            'ignore_errors' => true,
            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
        ]]);
        $body = file_get_contents(sprintf('http://%s:%d%s', self::HOST, self::PORT, $path), false, $context);

        $statusLine = array_shift($http_response_header);
        $responseHeaders = [];
        foreach ($http_response_header as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $responseHeaders[strtolower($name)] = $value;
        }

        return [
            'status' => (int) explode(' ', $statusLine)[1],
            'headers' => $responseHeaders,
            'json' => $raw ? null : json_decode((string) $body, true),
            'body' => (string) $body,
        ];
    }
}

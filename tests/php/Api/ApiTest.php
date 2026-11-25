<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Constants;
use App\Database;
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
    private const DB_VARIABLES = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];

    private const VALID_PERSON = [
        Constants::FIELD_NAME => 'Ana Pérez',
        Constants::FIELD_EMAIL => 'ana@mail.com',
        Constants::FIELD_PHONE => '097680102',
    ];

    /** @var resource|null */
    private static $server = null;

    public static function setUpBeforeClass(): void
    {
        self::pdo();

        $environment = getenv();
        foreach (self::DB_VARIABLES as $variable) {
            $environment[$variable] = Database::env($variable, Constants::DB_DEFAULT_PORT);
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
            $socket = @fsockopen(self::HOST, self::PORT);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(100_000);
        }

        throw new RuntimeException('The PHP built-in server did not start; see ' . $log);
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
        $response = self::request(Constants::METHOD_GET, Constants::ROUTE_HOME, raw: true);

        self::assertSame(Constants::HTTP_OK, $response['status']);
        self::assertStringStartsWith('text/html', $response['headers']['content-type']);
        self::assertSame(1, preg_match('#<script type="application/json" id="app-config">(.+?)</script>#s', $response['body'], $match));

        $config = json_decode($match[1], true);
        self::assertSame(Constants::PATTERNS, $config['patterns']);
        self::assertSame(Constants::ROUTE_PERSONS, $config['endpoint']);
        self::assertSame(Constants::PAGE_SIZE, $config['pageSize']);
    }

    public function testHomePageOnlyAcceptsGet(): void
    {
        $response = self::request(Constants::METHOD_POST, Constants::ROUTE_HOME);

        self::assertSame(Constants::HTTP_METHOD_NOT_ALLOWED, $response['status']);
        self::assertSame(Constants::METHOD_GET, $response['headers']['allow']);
    }

    public function testListReturnsJsonWithPaginationMeta(): void
    {
        $response = self::request(Constants::METHOD_GET, Constants::ROUTE_PERSONS);

        self::assertSame(Constants::HTTP_OK, $response['status']);
        self::assertStringStartsWith('application/json', $response['headers']['content-type']);
        self::assertSame('no-store', $response['headers']['cache-control']);
        self::assertSame([], $response['json'][Constants::RESPONSE_DATA]);
        self::assertSame(
            ['page' => 1, 'perPage' => Constants::PAGE_SIZE, 'total' => 0, 'totalPages' => 1],
            $response['json'][Constants::RESPONSE_META],
        );
    }

    public function testCreatingAPersonAddsItFirstInTheList(): void
    {
        self::post([Constants::FIELD_EMAIL => 'primero@mail.com', Constants::FIELD_PHONE => ''] + self::VALID_PERSON);
        $before = self::request(Constants::METHOD_GET, Constants::ROUTE_PERSONS)['json'];

        $created = self::post(self::VALID_PERSON);
        $after = self::request(Constants::METHOD_GET, Constants::ROUTE_PERSONS)['json'];

        self::assertSame(Constants::HTTP_CREATED, $created['status']);
        $person = $created['json'][Constants::RESPONSE_DATA];
        self::assertIsInt($person[Constants::FIELD_ID]);
        self::assertSame('Ana Pérez', $person[Constants::FIELD_NAME]);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $person[Constants::FIELD_CREATED_AT]);

        self::assertSame($before[Constants::RESPONSE_META]['total'] + 1, $after[Constants::RESPONSE_META]['total']);
        self::assertCount(count($before[Constants::RESPONSE_DATA]) + 1, $after[Constants::RESPONSE_DATA]);
        self::assertSame($person, $after[Constants::RESPONSE_DATA][0]);
    }

    public function testCreatedPersonIsNormalized(): void
    {
        $response = self::post([
            Constants::FIELD_NAME => '  María   José ',
            Constants::FIELD_EMAIL => ' Maria.Jose@MAIL.com ',
            Constants::FIELD_PHONE => '',
        ]);

        $person = $response['json'][Constants::RESPONSE_DATA];
        self::assertSame('María José', $person[Constants::FIELD_NAME]);
        self::assertSame('maria.jose@mail.com', $person[Constants::FIELD_EMAIL]);
        self::assertNull($person[Constants::FIELD_PHONE]);
    }

    public static function invalidSubmissions(): array
    {
        return [
            'missing name' => [[Constants::FIELD_NAME => ''], Constants::FIELD_NAME, Constants::RULE_REQUIRED],
            'whitespace-only name' => [[Constants::FIELD_NAME => '    '], Constants::FIELD_NAME, Constants::RULE_REQUIRED],
            'name with digits' => [[Constants::FIELD_NAME => 'Ana 2'], Constants::FIELD_NAME, Constants::RULE_FORMAT],
            'name with markup' => [[Constants::FIELD_NAME => '<script>alert(1)</script>'], Constants::FIELD_NAME, Constants::RULE_FORMAT],
            'name too long' => [[Constants::FIELD_NAME => str_repeat('a', 101)], Constants::FIELD_NAME, Constants::RULE_LENGTH],
            'missing email' => [[Constants::FIELD_EMAIL => ''], Constants::FIELD_EMAIL, Constants::RULE_REQUIRED],
            'malformed email' => [[Constants::FIELD_EMAIL => 'ana@mail'], Constants::FIELD_EMAIL, Constants::RULE_FORMAT],
            'phone with spaces' => [[Constants::FIELD_PHONE => '097 680 102'], Constants::FIELD_PHONE, Constants::RULE_FORMAT],
            'phone with country code' => [[Constants::FIELD_PHONE => '+59897680102'], Constants::FIELD_PHONE, Constants::RULE_FORMAT],
            'phone not starting with 09' => [[Constants::FIELD_PHONE => '087680102'], Constants::FIELD_PHONE, Constants::RULE_FORMAT],
        ];
    }

    /** @param array<string, string> $override */
    #[DataProvider('invalidSubmissions')]
    public function testInvalidSubmissionsAreRejectedWithASpecificMessage(array $override, string $field, string $rule): void
    {
        $response = self::post($override + self::VALID_PERSON);

        self::assertSame(Constants::HTTP_UNPROCESSABLE, $response['status']);
        self::assertSame([$field => PersonValidator::message($field, $rule)], $response['json'][Constants::RESPONSE_ERRORS]);
        self::assertSame(0, $this->total());
    }

    public function testAllErrorsAreReturnedAtOnce(): void
    {
        $response = self::post([Constants::FIELD_NAME => '', Constants::FIELD_EMAIL => 'x', Constants::FIELD_PHONE => '1']);

        self::assertSame(Constants::HTTP_UNPROCESSABLE, $response['status']);
        self::assertSame(Constants::VALIDATED_FIELDS, array_keys($response['json'][Constants::RESPONSE_ERRORS]));
    }

    public function testDuplicateEmailIsRejectedIgnoringCase(): void
    {
        self::post(self::VALID_PERSON);

        $response = self::post([Constants::FIELD_EMAIL => 'ANA@Mail.com', Constants::FIELD_PHONE => '091111111'] + self::VALID_PERSON);

        self::assertSame(Constants::HTTP_UNPROCESSABLE, $response['status']);
        self::assertSame(
            [Constants::FIELD_EMAIL => PersonValidator::message(Constants::FIELD_EMAIL, Constants::RULE_DUPLICATE)],
            $response['json'][Constants::RESPONSE_ERRORS],
        );
        self::assertSame(1, $this->total());
    }

    public function testDuplicatePhoneIsRejected(): void
    {
        self::post(self::VALID_PERSON);

        $response = self::post([Constants::FIELD_EMAIL => 'otra@mail.com'] + self::VALID_PERSON);

        self::assertSame(Constants::HTTP_UNPROCESSABLE, $response['status']);
        self::assertSame(
            [Constants::FIELD_PHONE => PersonValidator::message(Constants::FIELD_PHONE, Constants::RULE_DUPLICATE)],
            $response['json'][Constants::RESPONSE_ERRORS],
        );
    }

    public function testSeveralPeopleCanOmitThePhone(): void
    {
        $first = self::post([Constants::FIELD_PHONE => ''] + self::VALID_PERSON);
        $second = self::post([Constants::FIELD_EMAIL => 'luis@mail.com', Constants::FIELD_PHONE => ''] + self::VALID_PERSON);

        self::assertSame(Constants::HTTP_CREATED, $first['status']);
        self::assertSame(Constants::HTTP_CREATED, $second['status']);
    }

    public function testPaginatesAndClampsOutOfRangePages(): void
    {
        $values = [];
        foreach (range('a', 'i') as $letter) {
            $values[] = sprintf("('Persona %s', 'persona.%s@mail.com')", $letter, $letter);
        }
        self::pdo()->exec('INSERT INTO person (name, email) VALUES ' . implode(', ', $values));

        $second = self::request(Constants::METHOD_GET, Constants::ROUTE_PERSONS . '?page=2')['json'];
        $beyond = self::request(Constants::METHOD_GET, Constants::ROUTE_PERSONS . '?page=99')['json'];

        self::assertCount(1, $second[Constants::RESPONSE_DATA]);
        self::assertSame('Persona a', $second[Constants::RESPONSE_DATA][0][Constants::FIELD_NAME]);
        self::assertSame(['page' => 2, 'perPage' => Constants::PAGE_SIZE, 'total' => 9, 'totalPages' => 2], $second[Constants::RESPONSE_META]);
        self::assertSame(2, $beyond[Constants::RESPONSE_META]['page']);
    }

    public static function unsupportedMethods(): array
    {
        return ['PUT' => ['PUT'], 'DELETE' => ['DELETE'], 'PATCH' => ['PATCH']];
    }

    #[DataProvider('unsupportedMethods')]
    public function testUnsupportedMethodsAreRejected(string $method): void
    {
        $response = self::request($method, Constants::ROUTE_PERSONS);

        self::assertSame(Constants::HTTP_METHOD_NOT_ALLOWED, $response['status']);
        self::assertSame(implode(', ', Constants::PERSONS_METHODS), $response['headers']['allow']);
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
        $response = self::request(Constants::METHOD_GET, $path);

        self::assertSame(Constants::HTTP_NOT_FOUND, $response['status']);
        self::assertSame([Constants::RESPONSE_MESSAGE => Constants::MESSAGE_NOT_FOUND], $response['json']);
    }

    private function total(): int
    {
        return self::request(Constants::METHOD_GET, Constants::ROUTE_PERSONS)['json'][Constants::RESPONSE_META]['total'];
    }

    /** @param array<string, string> $fields */
    private static function post(array $fields): array
    {
        return self::request(Constants::METHOD_POST, Constants::ROUTE_PERSONS, $fields);
    }

    /**
     * @param array<string, string> $fields
     * @return array{status: int, headers: array<string, string>, json: mixed, body: string}
     */
    private static function request(string $method, string $path, array $fields = [], bool $raw = false): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'ignore_errors' => true,
            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
        ]]);
        $body = file_get_contents(sprintf('http://%s:%d%s', self::HOST, self::PORT, $path), false, $context);

        $statusLine = array_shift($http_response_header);
        $headers = [];
        foreach ($http_response_header as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $headers[strtolower($name)] = $value;
        }

        return [
            'status' => (int) explode(' ', $statusLine)[1],
            'headers' => $headers,
            'json' => $raw ? null : json_decode((string) $body, true),
            'body' => (string) $body,
        ];
    }
}

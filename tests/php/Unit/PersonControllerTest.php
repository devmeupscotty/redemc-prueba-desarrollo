<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants;
use App\Person;
use App\PersonController;
use App\PersonRepository;
use App\PersonValidator;
use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersonControllerTest extends TestCase
{
    private const VALID_BODY = [
        Constants::FIELD_NAME => ' Ana  Pérez ',
        Constants::FIELD_EMAIL => 'ANA@mail.com',
        Constants::FIELD_PHONE => '097680102',
    ];

    public function testIndexOnEmptyTable(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('count')->willReturn(0);
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGE_SIZE, 0)->willReturn([]);

        [$status, $body] = $this->controller($repository)->index([]);

        self::assertSame(Constants::HTTP_OK, $status);
        self::assertSame([], $body[Constants::RESPONSE_DATA]);
        self::assertSame(['page' => 1, 'perPage' => Constants::PAGE_SIZE, 'total' => 0, 'totalPages' => 1], $body[Constants::RESPONSE_META]);
    }

    public function testIndexReturnsRequestedPageWithMeta(): void
    {
        $person = new Person('Ana', 'ana@mail.com', null, 9, new DateTimeImmutable('2026-11-25 12:00:00+00:00'));
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('count')->willReturn(20);
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGE_SIZE, Constants::PAGE_SIZE)->willReturn([$person]);

        [, $body] = $this->controller($repository)->index([Constants::QUERY_PAGE => '2']);

        self::assertSame([$person->toArray()], $body[Constants::RESPONSE_DATA]);
        self::assertSame(['page' => 2, 'perPage' => Constants::PAGE_SIZE, 'total' => 20, 'totalPages' => 3], $body[Constants::RESPONSE_META]);
    }

    public function testIndexClampsPagesBeyondTheLast(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('count')->willReturn(10);
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGE_SIZE, Constants::PAGE_SIZE)->willReturn([]);

        [, $body] = $this->controller($repository)->index([Constants::QUERY_PAGE => '99']);

        self::assertSame(2, $body[Constants::RESPONSE_META]['page']);
    }

    public static function invalidPages(): array
    {
        return ['text' => ['abc'], 'zero' => ['0'], 'negative' => ['-3'], 'decimal' => ['1.5'], 'array' => [['2']], 'injection' => ['1 OR 1=1']];
    }

    #[DataProvider('invalidPages')]
    public function testIndexFallsBackToFirstPageOnInvalidInput(mixed $page): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('count')->willReturn(30);
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGE_SIZE, 0)->willReturn([]);

        [, $body] = $this->controller($repository)->index([Constants::QUERY_PAGE => $page]);

        self::assertSame(1, $body[Constants::RESPONSE_META]['page']);
    }

    public function testStoreCreatesNormalizedPerson(): void
    {
        $created = new Person('Ana Pérez', 'ana@mail.com', '097680102', 1, new DateTimeImmutable('2026-11-25 12:00:00+00:00'));
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('existsBy')->willReturn(false);
        $repository->expects(self::once())->method('create')
            ->with(new Person('Ana Pérez', 'ana@mail.com', '097680102'))
            ->willReturn($created);

        [$status, $body] = $this->controller($repository)->store(self::VALID_BODY);

        self::assertSame(Constants::HTTP_CREATED, $status);
        self::assertSame($created->toArray(), $body[Constants::RESPONSE_DATA]);
    }

    public function testStoreRejectsInvalidInputWithoutTouchingTheDatabase(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->expects(self::never())->method('existsBy');
        $repository->expects(self::never())->method('create');

        [$status, $body] = $this->controller($repository)->store([Constants::FIELD_NAME => '   ', Constants::FIELD_EMAIL => '']);

        self::assertSame(Constants::HTTP_UNPROCESSABLE, $status);
        self::assertSame([
            Constants::FIELD_NAME => PersonValidator::message(Constants::FIELD_NAME, Constants::RULE_REQUIRED),
            Constants::FIELD_EMAIL => PersonValidator::message(Constants::FIELD_EMAIL, Constants::RULE_REQUIRED),
        ], $body[Constants::RESPONSE_ERRORS]);
    }

    public static function duplicates(): array
    {
        return [
            'email' => [[Constants::FIELD_EMAIL]],
            'phone' => [[Constants::FIELD_PHONE]],
            'both' => [[Constants::FIELD_EMAIL, Constants::FIELD_PHONE]],
        ];
    }

    /** @param list<string> $duplicatedFields */
    #[DataProvider('duplicates')]
    public function testStoreRejectsDuplicatesFoundBeforeInserting(array $duplicatedFields): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('existsBy')->willReturnCallback(
            static fn (string $field) => in_array($field, $duplicatedFields, true)
        );
        $repository->expects(self::never())->method('create');

        [$status, $body] = $this->controller($repository)->store(self::VALID_BODY);

        $expected = [];
        foreach ($duplicatedFields as $field) {
            $expected[$field] = PersonValidator::message($field, Constants::RULE_DUPLICATE);
        }
        self::assertSame(Constants::HTTP_UNPROCESSABLE, $status);
        self::assertSame($expected, $body[Constants::RESPONSE_ERRORS]);
    }

    public function testStoreLooksUpEmailCaseInsensitively(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->expects(self::once())->method('existsBy')->with(Constants::FIELD_EMAIL, 'ana@mail.com')->willReturn(false);
        $repository->method('create')->willReturn(new Person('Ana', 'ana@mail.com', null, 1));

        $this->controller($repository)->store([Constants::FIELD_PHONE => ''] + self::VALID_BODY);
    }

    public function testStoreMapsDuplicateRaceToValidationError(): void
    {
        $race = new PDOException('Duplicate entry');
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('existsBy')->willReturn(false);
        $repository->method('create')->willThrowException($race);
        $repository->expects(self::once())->method('duplicateFieldFrom')->with($race)->willReturn(Constants::FIELD_EMAIL);

        [$status, $body] = $this->controller($repository)->store(self::VALID_BODY);

        self::assertSame(Constants::HTTP_UNPROCESSABLE, $status);
        self::assertSame(
            [Constants::FIELD_EMAIL => PersonValidator::message(Constants::FIELD_EMAIL, Constants::RULE_DUPLICATE)],
            $body[Constants::RESPONSE_ERRORS],
        );
    }

    public function testStoreRethrowsUnexpectedDatabaseErrors(): void
    {
        $failure = new PDOException('Connection lost');
        $repository = $this->createStub(PersonRepository::class);
        $repository->method('existsBy')->willReturn(false);
        $repository->method('create')->willThrowException($failure);
        $repository->method('duplicateFieldFrom')->willReturn(null);

        $this->expectExceptionObject($failure);

        $this->controller($repository)->store(self::VALID_BODY);
    }

    private function controller(PersonRepository $repository): PersonController
    {
        return new PersonController(new PersonValidator(), $repository);
    }
}

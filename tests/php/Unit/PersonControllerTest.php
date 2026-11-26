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
        Constants::FIELD['NAME'] => ' Ana  Pérez ',
        Constants::FIELD['EMAIL'] => 'ANA@mail.com',
        Constants::FIELD['PHONE'] => '097680102',
    ];

    public function testIndexOnEmptyTable(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('count')->willReturn(0);
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGINATION['PAGE_SIZE'], 0)->willReturn([]);

        [$status, $body] = $this->controller($repository)->index([]);

        self::assertSame(Constants::HTTP['OK'], $status);
        self::assertSame([], $body[Constants::RESPONSE['DATA']]);
        self::assertSame([Constants::META['PAGE'] => 1, Constants::META['PER_PAGE'] => Constants::PAGINATION['PAGE_SIZE'], Constants::META['TOTAL'] => 0, Constants::META['TOTAL_PAGES'] => 1], $body[Constants::RESPONSE['META']]);
    }

    public function testIndexReturnsRequestedPageWithMeta(): void
    {
        $person = new Person('Ana', 'ana@mail.com', null, 9, new DateTimeImmutable('2026-11-25 12:00:00+00:00'));
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('count')->willReturn(20);
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGINATION['PAGE_SIZE'], Constants::PAGINATION['PAGE_SIZE'])->willReturn([$person]);

        [, $body] = $this->controller($repository)->index([Constants::PAGINATION['QUERY_PARAM'] => '2']);

        self::assertSame([$person->toArray()], $body[Constants::RESPONSE['DATA']]);
        self::assertSame([Constants::META['PAGE'] => 2, Constants::META['PER_PAGE'] => Constants::PAGINATION['PAGE_SIZE'], Constants::META['TOTAL'] => 20, Constants::META['TOTAL_PAGES'] => 3], $body[Constants::RESPONSE['META']]);
    }

    public function testIndexClampsPagesBeyondTheLast(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('count')->willReturn(10);
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGINATION['PAGE_SIZE'], Constants::PAGINATION['PAGE_SIZE'])->willReturn([]);

        [, $body] = $this->controller($repository)->index([Constants::PAGINATION['QUERY_PARAM'] => '99']);

        self::assertSame(2, $body[Constants::RESPONSE['META']][Constants::META['PAGE']]);
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
        $repository->expects(self::once())->method('findPage')->with(Constants::PAGINATION['PAGE_SIZE'], 0)->willReturn([]);

        [, $body] = $this->controller($repository)->index([Constants::PAGINATION['QUERY_PARAM'] => $page]);

        self::assertSame(1, $body[Constants::RESPONSE['META']][Constants::META['PAGE']]);
    }

    public function testStoreCreatesNormalizedPerson(): void
    {
        $created = new Person('Ana Pérez', 'ana@mail.com', '097680102', 1, new DateTimeImmutable('2026-11-25 12:00:00+00:00'));
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('duplicatedFields')->willReturn([]);
        $repository->expects(self::once())->method('create')
            ->with(new Person('Ana Pérez', 'ana@mail.com', '097680102'))
            ->willReturn($created);

        [$status, $body] = $this->controller($repository)->store(self::VALID_BODY);

        self::assertSame(Constants::HTTP['CREATED'], $status);
        self::assertSame($created->toArray(), $body[Constants::RESPONSE['DATA']]);
    }

    public function testStoreRejectsInvalidInputWithoutTouchingTheDatabase(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->expects(self::never())->method('duplicatedFields');
        $repository->expects(self::never())->method('create');

        [$status, $body] = $this->controller($repository)->store([Constants::FIELD['NAME'] => '   ', Constants::FIELD['EMAIL'] => '']);

        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $status);
        self::assertSame([
            Constants::FIELD['NAME'] => PersonValidator::message(Constants::FIELD['NAME'], Constants::RULE['REQUIRED']),
            Constants::FIELD['EMAIL'] => PersonValidator::message(Constants::FIELD['EMAIL'], Constants::RULE['REQUIRED']),
        ], $body[Constants::RESPONSE['ERRORS']]);
    }

    public static function duplicates(): array
    {
        return [
            'email' => [[Constants::FIELD['EMAIL']]],
            'phone' => [[Constants::FIELD['PHONE']]],
            'both' => [[Constants::FIELD['EMAIL'], Constants::FIELD['PHONE']]],
        ];
    }

    /** @param list<string> $duplicatedFields */
    #[DataProvider('duplicates')]
    public function testStoreRejectsDuplicatesFoundBeforeInserting(array $duplicatedFields): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('duplicatedFields')->willReturn($duplicatedFields);
        $repository->expects(self::never())->method('create');

        [$status, $body] = $this->controller($repository)->store(self::VALID_BODY);

        $expected = [];
        foreach ($duplicatedFields as $field) {
            $expected[$field] = PersonValidator::message($field, Constants::RULE['DUPLICATE']);
        }
        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $status);
        self::assertSame($expected, $body[Constants::RESPONSE['ERRORS']]);
    }

    public function testStoreChecksDuplicatesOnceWithTheNormalizedPerson(): void
    {
        $repository = $this->createMock(PersonRepository::class);
        $repository->expects(self::once())->method('duplicatedFields')
            ->with(new Person('Ana Pérez', 'ana@mail.com', null))
            ->willReturn([]);
        $repository->method('create')->willReturn(new Person('Ana', 'ana@mail.com', null, 1));

        $this->controller($repository)->store([Constants::FIELD['PHONE'] => ''] + self::VALID_BODY);
    }

    public function testStoreMapsDuplicateRaceToValidationError(): void
    {
        $race = new PDOException('Duplicate entry');
        $repository = $this->createMock(PersonRepository::class);
        $repository->method('duplicatedFields')->willReturn([]);
        $repository->method('create')->willThrowException($race);
        $repository->expects(self::once())->method('duplicateFieldFrom')->with($race)->willReturn(Constants::FIELD['EMAIL']);

        [$status, $body] = $this->controller($repository)->store(self::VALID_BODY);

        self::assertSame(Constants::HTTP['UNPROCESSABLE'], $status);
        self::assertSame(
            [Constants::FIELD['EMAIL'] => PersonValidator::message(Constants::FIELD['EMAIL'], Constants::RULE['DUPLICATE'])],
            $body[Constants::RESPONSE['ERRORS']],
        );
    }

    public function testStoreRethrowsUnexpectedDatabaseErrors(): void
    {
        $failure = new PDOException('Connection lost');
        $repository = $this->createStub(PersonRepository::class);
        $repository->method('duplicatedFields')->willReturn([]);
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

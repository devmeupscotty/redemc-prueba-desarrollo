<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Constants;
use App\Person;
use App\PersonRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDOException;
use Tests\DatabaseTestCase;

final class PersonRepositoryTest extends DatabaseTestCase
{
    private const CLOCK_TOLERANCE_SECONDS = 120;

    private PersonRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PersonRepository(self::pdo());
    }

    public function testCreateReturnsThePersistedPerson(): void
    {
        $created = $this->repository->create(new Person('Ana Pérez', 'ana@mail.com', '097680102'));

        self::assertGreaterThan(0, $created->id);
        self::assertSame('Ana Pérez', $created->name);
        self::assertSame('ana@mail.com', $created->email);
        self::assertSame('097680102', $created->phone);
    }

    public function testCreateStoresTheInsertionDateInUtc(): void
    {
        $created = $this->repository->create(new Person('Ana', 'ana@mail.com'));

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        self::assertNotNull($created->createdAt);
        self::assertSame('+00:00', $created->createdAt->format('P'));
        self::assertLessThan(self::CLOCK_TOLERANCE_SECONDS, abs($now->getTimestamp() - $created->createdAt->getTimestamp()));
    }

    public function testCreateStoresNullPhone(): void
    {
        $created = $this->repository->create(new Person('Ana', 'ana@mail.com'));

        self::assertNull($created->phone);
    }

    public function testCountReflectsInsertions(): void
    {
        self::assertSame(0, $this->repository->count());

        $this->seed(3);

        self::assertSame(3, $this->repository->count());
    }

    public function testFindPageReturnsNewestFirst(): void
    {
        $this->seed(3);

        $names = array_map(static fn (Person $person) => $person->name, $this->repository->findPage(Constants::PAGINATION['PAGE_SIZE'], 0));

        self::assertSame(['Persona c', 'Persona b', 'Persona a'], $names);
    }

    public function testFindPageAppliesLimitAndOffset(): void
    {
        $this->seed(10);

        $firstPage = $this->repository->findPage(Constants::PAGINATION['PAGE_SIZE'], 0);
        $secondPage = $this->repository->findPage(Constants::PAGINATION['PAGE_SIZE'], Constants::PAGINATION['PAGE_SIZE']);

        self::assertCount(Constants::PAGINATION['PAGE_SIZE'], $firstPage);
        self::assertCount(2, $secondPage);
        self::assertSame('Persona j', $firstPage[0]->name);
        self::assertSame('Persona a', $secondPage[1]->name);
    }

    public function testFindPageBeyondTheEndIsEmpty(): void
    {
        $this->seed(2);

        self::assertSame([], $this->repository->findPage(Constants::PAGINATION['PAGE_SIZE'], Constants::PAGINATION['PAGE_SIZE']));
    }

    public function testExistsByFindsEmailsIgnoringCase(): void
    {
        $this->repository->create(new Person('Ana', 'ana@mail.com'));

        self::assertTrue($this->repository->existsBy(Constants::FIELD['EMAIL'], 'ana@mail.com'));
        self::assertTrue($this->repository->existsBy(Constants::FIELD['EMAIL'], 'ANA@Mail.COM'));
        self::assertFalse($this->repository->existsBy(Constants::FIELD['EMAIL'], 'otra@mail.com'));
    }

    public function testExistsByFindsPhones(): void
    {
        $this->repository->create(new Person('Ana', 'ana@mail.com', '097680102'));

        self::assertTrue($this->repository->existsBy(Constants::FIELD['PHONE'], '097680102'));
        self::assertFalse($this->repository->existsBy(Constants::FIELD['PHONE'], '091111111'));
    }

    public function testExistsByRejectsNonUniqueFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repository->existsBy(Constants::FIELD['NAME'], 'Ana');
    }

    public function testAllowsManyPeopleWithoutPhone(): void
    {
        $this->repository->create(new Person('Ana', 'ana@mail.com'));
        $this->repository->create(new Person('Luis', 'luis@mail.com'));

        self::assertSame(2, $this->repository->count());
    }

    public function testDatabaseRejectsDuplicateEmailIgnoringCase(): void
    {
        $this->repository->create(new Person('Ana', 'ana@mail.com'));

        $exception = $this->captureDuplicate(new Person('Ana Dos', 'ANA@MAIL.COM'));

        self::assertSame(Constants::FIELD['EMAIL'], $this->repository->duplicateFieldFrom($exception));
    }

    public function testDatabaseRejectsDuplicatePhone(): void
    {
        $this->repository->create(new Person('Ana', 'ana@mail.com', '097680102'));

        $exception = $this->captureDuplicate(new Person('Luis', 'luis@mail.com', '097680102'));

        self::assertSame(Constants::FIELD['PHONE'], $this->repository->duplicateFieldFrom($exception));
    }

    public function testDuplicateFieldFromIgnoresOtherErrors(): void
    {
        try {
            self::pdo()->exec('SELECT * FROM missing_table');
            self::fail('Expected a PDOException');
        } catch (PDOException $exception) {
            self::assertNull($this->repository->duplicateFieldFrom($exception));
        }
    }

    private function captureDuplicate(Person $person): PDOException
    {
        try {
            $this->repository->create($person);
        } catch (PDOException $exception) {
            return $exception;
        }

        self::fail('Expected a duplicate key PDOException');
    }

    private function seed(int $amount): void
    {
        foreach (range('a', chr(ord('a') + $amount - 1)) as $letter) {
            $this->repository->create(new Person("Persona {$letter}", "persona.{$letter}@mail.com"));
        }
    }
}

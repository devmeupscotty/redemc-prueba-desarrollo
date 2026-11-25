<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;

class PersonRepository
{
    private const SELECT_COLUMNS = 'SELECT id, name, email, phone, created_at FROM person';
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(Person $person): Person
    {
        $statement = $this->pdo->prepare('INSERT INTO person (name, email, phone) VALUES (:name, :email, :phone)');
        $statement->execute([
            'name' => $person->name,
            'email' => $person->email,
            'phone' => $person->phone,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId());
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM person')->fetchColumn();
    }

    /** @return list<Person> */
    public function findPage(int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(self::SELECT_COLUMNS . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(self::hydrate(...), $statement->fetchAll());
    }

    public function existsBy(string $field, string $value): bool
    {
        if (!in_array($field, Constants::UNIQUE_FIELDS, true)) {
            throw new InvalidArgumentException("Field is not unique: {$field}");
        }

        $statement = $this->pdo->prepare("SELECT 1 FROM person WHERE {$field} = :value LIMIT 1");
        $statement->execute(['value' => $value]);

        return $statement->fetchColumn() !== false;
    }

    public function duplicateFieldFrom(PDOException $exception): ?string
    {
        if ((int) ($exception->errorInfo[1] ?? 0) !== Constants::DUPLICATE_ENTRY_ERROR) {
            return null;
        }

        foreach (Constants::DUPLICATE_KEYS as $key => $field) {
            if (str_contains($exception->getMessage(), $key)) {
                return $field;
            }
        }

        return null;
    }

    private function findById(int $id): Person
    {
        $statement = $this->pdo->prepare(self::SELECT_COLUMNS . ' WHERE id = :id');
        $statement->execute(['id' => $id]);

        return self::hydrate($statement->fetch());
    }

    /** @param array<string, mixed> $row */
    private static function hydrate(array $row): Person
    {
        return new Person(
            $row['name'],
            $row['email'],
            $row['phone'],
            (int) $row['id'],
            DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $row['created_at'], new DateTimeZone(Constants::DB_TIME_ZONE)),
        );
    }
}

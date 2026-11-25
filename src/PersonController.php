<?php

declare(strict_types=1);

namespace App;

use PDOException;

final class PersonController
{
    public function __construct(
        private readonly PersonValidator $validator,
        private readonly PersonRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function index(array $query): array
    {
        $total = $this->repository->count();
        $totalPages = max(Constants::FIRST_PAGE, (int) ceil($total / Constants::PAGE_SIZE));
        $page = min(self::requestedPage($query), $totalPages);
        $people = $this->repository->findPage(Constants::PAGE_SIZE, ($page - 1) * Constants::PAGE_SIZE);

        return [Constants::HTTP_OK, [
            Constants::RESPONSE_DATA => array_map(static fn (Person $person) => $person->toArray(), $people),
            Constants::RESPONSE_META => [
                'page' => $page,
                'perPage' => Constants::PAGE_SIZE,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function store(array $body): array
    {
        [$person, $errors] = $this->validator->validate($body);
        if ($person === null) {
            return self::unprocessable($errors);
        }

        $errors = $this->duplicateErrors($person);
        if ($errors !== []) {
            return self::unprocessable($errors);
        }

        try {
            $created = $this->repository->create($person);
        } catch (PDOException $exception) {
            $field = $this->repository->duplicateFieldFrom($exception);
            if ($field === null) {
                throw $exception;
            }

            return self::unprocessable([$field => PersonValidator::message($field, Constants::RULE_DUPLICATE)]);
        }

        return [Constants::HTTP_CREATED, [Constants::RESPONSE_DATA => $created->toArray()]];
    }

    /** @return array<string, string> */
    private function duplicateErrors(Person $person): array
    {
        $values = $person->toArray();
        $errors = [];

        foreach (Constants::UNIQUE_FIELDS as $field) {
            if ($values[$field] !== null && $this->repository->existsBy($field, $values[$field])) {
                $errors[$field] = PersonValidator::message($field, Constants::RULE_DUPLICATE);
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $query */
    private static function requestedPage(array $query): int
    {
        $page = filter_var(
            $query[Constants::QUERY_PAGE] ?? Constants::FIRST_PAGE,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => Constants::FIRST_PAGE]],
        );

        return $page === false ? Constants::FIRST_PAGE : $page;
    }

    /**
     * @param array<string, string> $errors
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function unprocessable(array $errors): array
    {
        return [Constants::HTTP_UNPROCESSABLE, [Constants::RESPONSE_ERRORS => $errors]];
    }
}

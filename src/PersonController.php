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
        $totalPages = max(Constants::PAGINATION['FIRST_PAGE'], (int) ceil($total / Constants::PAGINATION['PAGE_SIZE']));
        $page = min(self::requestedPage($query), $totalPages);
        $people = $this->repository->findPage(Constants::PAGINATION['PAGE_SIZE'], ($page - 1) * Constants::PAGINATION['PAGE_SIZE']);

        return [Constants::HTTP['OK'], [
            Constants::RESPONSE['DATA'] => array_map(static fn (Person $person) => $person->toArray(), $people),
            Constants::RESPONSE['META'] => [
                Constants::META['PAGE'] => $page,
                Constants::META['PER_PAGE'] => Constants::PAGINATION['PAGE_SIZE'],
                Constants::META['TOTAL'] => $total,
                Constants::META['TOTAL_PAGES'] => $totalPages,
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

            return self::unprocessable([$field => PersonValidator::message($field, Constants::RULE['DUPLICATE'])]);
        }

        return [Constants::HTTP['CREATED'], [Constants::RESPONSE['DATA'] => $created->toArray()]];
    }

    /** @return array<string, string> */
    private function duplicateErrors(Person $person): array
    {
        $values = $person->toArray();
        $errors = [];

        foreach (Constants::UNIQUE_FIELDS as $field) {
            if ($values[$field] !== null && $this->repository->existsBy($field, $values[$field])) {
                $errors[$field] = PersonValidator::message($field, Constants::RULE['DUPLICATE']);
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $query */
    private static function requestedPage(array $query): int
    {
        $page = filter_var(
            $query[Constants::PAGINATION['QUERY_PARAM']] ?? Constants::PAGINATION['FIRST_PAGE'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => Constants::PAGINATION['FIRST_PAGE']]],
        );

        return $page === false ? Constants::PAGINATION['FIRST_PAGE'] : $page;
    }

    /**
     * @param array<string, string> $errors
     * @return array{0: int, 1: array<string, mixed>}
     */
    private static function unprocessable(array $errors): array
    {
        return [Constants::HTTP['UNPROCESSABLE'], [Constants::RESPONSE['ERRORS'] => $errors]];
    }
}

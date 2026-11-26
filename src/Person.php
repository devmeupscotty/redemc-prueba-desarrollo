<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class Person
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?int $id = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            Constants::FIELD['ID'] => $this->id,
            Constants::FIELD['NAME'] => $this->name,
            Constants::FIELD['EMAIL'] => $this->email,
            Constants::FIELD['PHONE'] => $this->phone,
            Constants::FIELD['CREATED_AT'] => $this->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}

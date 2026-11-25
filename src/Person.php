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
            Constants::FIELD_ID => $this->id,
            Constants::FIELD_NAME => $this->name,
            Constants::FIELD_EMAIL => $this->email,
            Constants::FIELD_PHONE => $this->phone,
            Constants::FIELD_CREATED_AT => $this->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}

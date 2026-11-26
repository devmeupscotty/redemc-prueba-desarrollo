<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants;
use App\Person;
use App\PersonValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersonValidatorTest extends TestCase
{
    private const CASES_FILE = __DIR__ . '/../../fixtures/validation-cases.json';

    private const VALID_INPUT = [
        Constants::FIELD['NAME'] => 'Ana Pérez',
        Constants::FIELD['EMAIL'] => 'ana@mail.com',
        Constants::FIELD['PHONE'] => '097680102',
    ];

    private PersonValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PersonValidator();
    }

    public static function validNames(): array
    {
        return self::cases(Constants::FIELD['NAME'], 'valid');
    }

    public static function invalidNameFormats(): array
    {
        return self::cases(Constants::FIELD['NAME'], 'invalid');
    }

    public static function validEmails(): array
    {
        return self::cases(Constants::FIELD['EMAIL'], 'valid');
    }

    public static function invalidEmailFormats(): array
    {
        return self::cases(Constants::FIELD['EMAIL'], 'invalid');
    }

    public static function validPhones(): array
    {
        return self::cases(Constants::FIELD['PHONE'], 'valid');
    }

    public static function invalidPhoneFormats(): array
    {
        return self::cases(Constants::FIELD['PHONE'], 'invalid');
    }

    #[DataProvider('validNames')]
    public function testAcceptsValidNames(string $name): void
    {
        $this->assertNoErrorFor(Constants::FIELD['NAME'], $name);
    }

    #[DataProvider('invalidNameFormats')]
    public function testRejectsInvalidNameFormats(string $name): void
    {
        $this->assertErrorFor(Constants::FIELD['NAME'], $name, Constants::RULE['FORMAT']);
    }

    #[DataProvider('validEmails')]
    public function testAcceptsValidEmails(string $email): void
    {
        $this->assertNoErrorFor(Constants::FIELD['EMAIL'], $email);
    }

    #[DataProvider('invalidEmailFormats')]
    public function testRejectsInvalidEmailFormats(string $email): void
    {
        $this->assertErrorFor(Constants::FIELD['EMAIL'], $email, Constants::RULE['FORMAT']);
    }

    #[DataProvider('validPhones')]
    public function testAcceptsValidPhones(string $phone): void
    {
        $this->assertNoErrorFor(Constants::FIELD['PHONE'], $phone);
    }

    #[DataProvider('invalidPhoneFormats')]
    public function testRejectsInvalidPhoneFormats(string $phone): void
    {
        $this->assertErrorFor(Constants::FIELD['PHONE'], $phone, Constants::RULE['FORMAT']);
    }

    public static function emptyValues(): array
    {
        return self::cases('empty');
    }

    #[DataProvider('emptyValues')]
    public function testRequiresNameAndEmail(string $empty): void
    {
        $this->assertErrorFor(Constants::FIELD['NAME'], $empty, Constants::RULE['REQUIRED']);
        $this->assertErrorFor(Constants::FIELD['EMAIL'], $empty, Constants::RULE['REQUIRED']);
    }

    #[DataProvider('emptyValues')]
    public function testPhoneIsOptional(string $empty): void
    {
        [$person, $errors] = $this->validator->validate([Constants::FIELD['PHONE'] => $empty] + self::VALID_INPUT);

        self::assertSame([], $errors);
        self::assertNull($person->phone);
    }

    public function testEnforcesMaxLengthCountingCharacters(): void
    {
        $tooLongEmail = str_repeat('a', 64) . '@' . str_repeat('b', 60) . '.' . str_repeat('c', 60) . '.' . str_repeat('d', 70) . '.com';

        $this->assertNoErrorFor(Constants::FIELD['NAME'], str_repeat('ñ', Constants::MAX_LENGTH[Constants::FIELD['NAME']]));
        $this->assertErrorFor(Constants::FIELD['NAME'], str_repeat('a', 101), Constants::RULE['LENGTH']);
        $this->assertErrorFor(Constants::FIELD['EMAIL'], $tooLongEmail, Constants::RULE['LENGTH']);
    }

    public function testLengthMessageIncludesTheLimit(): void
    {
        self::assertSame(
            'El nombre no puede superar los 100 caracteres.',
            PersonValidator::message(Constants::FIELD['NAME'], Constants::RULE['LENGTH']),
        );
    }

    public function testReturnsNormalizedPersonWhenValid(): void
    {
        [$person, $errors] = $this->validator->validate([
            Constants::FIELD['NAME'] => "  María   José\tPérez ",
            Constants::FIELD['EMAIL'] => ' Ana.Perez@MAIL.com ',
            Constants::FIELD['PHONE'] => ' 097680102 ',
        ]);

        self::assertSame([], $errors);
        self::assertInstanceOf(Person::class, $person);
        self::assertSame('María José Pérez', $person->name);
        self::assertSame('ana.perez@mail.com', $person->email);
        self::assertSame('097680102', $person->phone);
        self::assertNull($person->id);
        self::assertNull($person->createdAt);
    }

    public function testNormalizesDecomposedAccents(): void
    {
        $decomposed = "Jose\u{0301}";

        [$person] = $this->validator->validate([Constants::FIELD['NAME'] => $decomposed] + self::VALID_INPUT);

        self::assertSame('José', $person->name);
    }

    public function testReportsAllErrorsAtOnce(): void
    {
        [$person, $errors] = $this->validator->validate([
            Constants::FIELD['NAME'] => '   ',
            Constants::FIELD['EMAIL'] => 'not-an-email',
            Constants::FIELD['PHONE'] => '12345',
        ]);

        self::assertNull($person);
        self::assertSame([
            Constants::FIELD['NAME'] => PersonValidator::message(Constants::FIELD['NAME'], Constants::RULE['REQUIRED']),
            Constants::FIELD['EMAIL'] => PersonValidator::message(Constants::FIELD['EMAIL'], Constants::RULE['FORMAT']),
            Constants::FIELD['PHONE'] => PersonValidator::message(Constants::FIELD['PHONE'], Constants::RULE['FORMAT']),
        ], $errors);
    }

    public function testTreatsMissingAndNonStringInputAsEmpty(): void
    {
        [, $errors] = $this->validator->validate([Constants::FIELD['NAME'] => ['Ana'], Constants::FIELD['PHONE'] => 97680102]);

        self::assertSame(PersonValidator::message(Constants::FIELD['NAME'], Constants::RULE['REQUIRED']), $errors[Constants::FIELD['NAME']]);
        self::assertSame(PersonValidator::message(Constants::FIELD['EMAIL'], Constants::RULE['REQUIRED']), $errors[Constants::FIELD['EMAIL']]);
        self::assertArrayNotHasKey(Constants::FIELD['PHONE'], $errors);
    }

    public function testEveryRuleThatCanFailHasAMessage(): void
    {
        foreach (Constants::VALIDATED_FIELDS as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE['FORMAT']] ?? null, "{$field}.format");
        }
        foreach (Constants::REQUIRED_FIELDS as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE['REQUIRED']] ?? null, "{$field}.required");
        }
        foreach (Constants::UNIQUE_FIELDS as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE['DUPLICATE']] ?? null, "{$field}.duplicate");
        }
        foreach (array_keys(Constants::MAX_LENGTH) as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE['LENGTH']] ?? null, "{$field}.length");
        }
    }

    public function testClientRulesExposeServerPatternsAndFormattedMessages(): void
    {
        $rules = PersonValidator::clientRules();

        self::assertSame(Constants::PATTERNS, $rules[Constants::CONFIG['PATTERNS']]);
        self::assertSame(Constants::PATTERN_FLAGS, $rules[Constants::CONFIG['FLAGS']]);
        self::assertSame(Constants::REQUIRED_FIELDS, $rules[Constants::CONFIG['REQUIRED']]);
        self::assertSame(Constants::MAX_LENGTH, $rules[Constants::CONFIG['MAX_LENGTH']]);
        self::assertSame(Constants::COLLAPSE_WHITESPACE_FIELDS, $rules[Constants::CONFIG['COLLAPSE_WHITESPACE']]);
        self::assertSame(Constants::LOWERCASE_FIELDS, $rules[Constants::CONFIG['LOWERCASE']]);
        self::assertStringNotContainsString('%d', json_encode($rules[Constants::CONFIG['MESSAGES']], JSON_UNESCAPED_UNICODE));
    }

    private function assertNoErrorFor(string $field, string $value): void
    {
        [, $errors] = $this->validator->validate([$field => $value] + self::VALID_INPUT);

        self::assertArrayNotHasKey($field, $errors, "Unexpected error for \"{$value}\"");
    }

    private function assertErrorFor(string $field, string $value, string $rule): void
    {
        [$person, $errors] = $this->validator->validate([$field => $value] + self::VALID_INPUT);

        self::assertNull($person);
        self::assertSame(PersonValidator::message($field, $rule), $errors[$field] ?? null, "Wrong result for \"{$value}\"");
    }

    private static function cases(string ...$path): array
    {
        $values = json_decode(file_get_contents(self::CASES_FILE), true, flags: JSON_THROW_ON_ERROR);
        foreach ($path as $key) {
            $values = $values[$key];
        }

        $cases = [];
        foreach ($values as $value) {
            $cases[json_encode($value, JSON_UNESCAPED_UNICODE)] = [$value];
        }

        return $cases;
    }
}

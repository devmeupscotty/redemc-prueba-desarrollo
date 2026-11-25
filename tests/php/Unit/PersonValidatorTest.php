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
    private const VALID_INPUT = [
        Constants::FIELD_NAME => 'Ana Pérez',
        Constants::FIELD_EMAIL => 'ana@mail.com',
        Constants::FIELD_PHONE => '097680102',
    ];

    private PersonValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PersonValidator();
    }

    public static function validNames(): array
    {
        return self::cases([
            'Ana', 'José', 'María José', 'Ñandú', 'Güemes', 'ÁÉÍÓÚ áéíóú', 'Ana Laura Pérez Núñez',
            'a', "Ana\tLaura", '  Ana  ', 'Ana    Laura', str_repeat('a', 100),
        ]);
    }

    public static function invalidNameFormats(): array
    {
        return self::cases([
            'Ana2', 'Ana-María', "O'Neil", 'Ana_Laura', '<script>', 'Ana.', 'Jean@', '😀', 'Иван', 'Zoë', 'Ana, Laura', '123',
        ]);
    }

    public static function validEmails(): array
    {
        return self::cases([
            'ana@mail.com', 'ana.laura@mail.com', 'ana+tag@mail.com', 'ana_l@sub.mail.com.uy', 'a%b@x-y.co',
            'ANA@MAIL.COM', '123@mail.io', 'a@b.cd', '  ana@mail.com  ', 'first.middle.last@domain.travel',
        ]);
    }

    public static function invalidEmailFormats(): array
    {
        return self::cases([
            'ana', 'ana@', '@mail.com', 'ana@mail', 'ana..b@mail.com', '.ana@mail.com', 'ana.@mail.com',
            'ana@-mail.com', 'ana@mail-.com', 'ana@mail.c', 'ana@mail..com', 'ana mail@x.com', 'ana@mail.com.',
            'ana@@mail.com', 'añá@mail.com', 'ana@mail.123', '<a>@mail.com',
        ]);
    }

    public static function validPhones(): array
    {
        return self::cases(['097680102', '091234567', '099999999', '090000000', ' 097680102 ']);
    }

    public static function invalidPhoneFormats(): array
    {
        return self::cases([
            '97680102', '0976801020', '097 680 102', '097-680-102', '+59897680102', '59897680102', '087680102',
            '09768010a', '０９７６８０１０２', '0', '09', '(09)7680102',
        ]);
    }

    #[DataProvider('validNames')]
    public function testAcceptsValidNames(string $name): void
    {
        $this->assertNoErrorFor(Constants::FIELD_NAME, $name);
    }

    #[DataProvider('invalidNameFormats')]
    public function testRejectsInvalidNameFormats(string $name): void
    {
        $this->assertErrorFor(Constants::FIELD_NAME, $name, Constants::RULE_FORMAT);
    }

    #[DataProvider('validEmails')]
    public function testAcceptsValidEmails(string $email): void
    {
        $this->assertNoErrorFor(Constants::FIELD_EMAIL, $email);
    }

    #[DataProvider('invalidEmailFormats')]
    public function testRejectsInvalidEmailFormats(string $email): void
    {
        $this->assertErrorFor(Constants::FIELD_EMAIL, $email, Constants::RULE_FORMAT);
    }

    #[DataProvider('validPhones')]
    public function testAcceptsValidPhones(string $phone): void
    {
        $this->assertNoErrorFor(Constants::FIELD_PHONE, $phone);
    }

    #[DataProvider('invalidPhoneFormats')]
    public function testRejectsInvalidPhoneFormats(string $phone): void
    {
        $this->assertErrorFor(Constants::FIELD_PHONE, $phone, Constants::RULE_FORMAT);
    }

    public static function emptyValues(): array
    {
        return self::cases(['', ' ', "   \t  "]);
    }

    #[DataProvider('emptyValues')]
    public function testRequiresNameAndEmail(string $empty): void
    {
        $this->assertErrorFor(Constants::FIELD_NAME, $empty, Constants::RULE_REQUIRED);
        $this->assertErrorFor(Constants::FIELD_EMAIL, $empty, Constants::RULE_REQUIRED);
    }

    #[DataProvider('emptyValues')]
    public function testPhoneIsOptional(string $empty): void
    {
        [$person, $errors] = $this->validator->validate([Constants::FIELD_PHONE => $empty] + self::VALID_INPUT);

        self::assertSame([], $errors);
        self::assertNull($person->phone);
    }

    public function testRejectsValuesOverMaxLength(): void
    {
        $tooLongEmail = str_repeat('a', 64) . '@' . str_repeat('b', 60) . '.' . str_repeat('c', 60) . '.' . str_repeat('d', 70) . '.com';

        $this->assertErrorFor(Constants::FIELD_NAME, str_repeat('a', 101), Constants::RULE_LENGTH);
        $this->assertErrorFor(Constants::FIELD_EMAIL, $tooLongEmail, Constants::RULE_LENGTH);
    }

    public function testLengthMessageIncludesTheLimit(): void
    {
        self::assertSame(
            'El nombre no puede superar los 100 caracteres.',
            PersonValidator::message(Constants::FIELD_NAME, Constants::RULE_LENGTH),
        );
    }

    public function testReturnsNormalizedPersonWhenValid(): void
    {
        [$person, $errors] = $this->validator->validate([
            Constants::FIELD_NAME => "  María   José\tPérez ",
            Constants::FIELD_EMAIL => ' Ana.Perez@MAIL.com ',
            Constants::FIELD_PHONE => ' 097680102 ',
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

        [$person] = $this->validator->validate([Constants::FIELD_NAME => $decomposed] + self::VALID_INPUT);

        self::assertSame('José', $person->name);
    }

    public function testReportsAllErrorsAtOnce(): void
    {
        [$person, $errors] = $this->validator->validate([
            Constants::FIELD_NAME => '   ',
            Constants::FIELD_EMAIL => 'not-an-email',
            Constants::FIELD_PHONE => '12345',
        ]);

        self::assertNull($person);
        self::assertSame([
            Constants::FIELD_NAME => PersonValidator::message(Constants::FIELD_NAME, Constants::RULE_REQUIRED),
            Constants::FIELD_EMAIL => PersonValidator::message(Constants::FIELD_EMAIL, Constants::RULE_FORMAT),
            Constants::FIELD_PHONE => PersonValidator::message(Constants::FIELD_PHONE, Constants::RULE_FORMAT),
        ], $errors);
    }

    public function testTreatsMissingAndNonStringInputAsEmpty(): void
    {
        [, $errors] = $this->validator->validate([Constants::FIELD_NAME => ['Ana'], Constants::FIELD_PHONE => 97680102]);

        self::assertSame(PersonValidator::message(Constants::FIELD_NAME, Constants::RULE_REQUIRED), $errors[Constants::FIELD_NAME]);
        self::assertSame(PersonValidator::message(Constants::FIELD_EMAIL, Constants::RULE_REQUIRED), $errors[Constants::FIELD_EMAIL]);
        self::assertArrayNotHasKey(Constants::FIELD_PHONE, $errors);
    }

    public function testEveryRuleThatCanFailHasAMessage(): void
    {
        foreach (Constants::VALIDATED_FIELDS as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE_FORMAT] ?? null, "{$field}.format");
        }
        foreach (Constants::REQUIRED_FIELDS as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE_REQUIRED] ?? null, "{$field}.required");
        }
        foreach (Constants::UNIQUE_FIELDS as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE_DUPLICATE] ?? null, "{$field}.duplicate");
        }
        foreach (array_keys(Constants::MAX_LENGTH) as $field) {
            self::assertNotEmpty(Constants::MESSAGES[$field][Constants::RULE_LENGTH] ?? null, "{$field}.length");
        }
    }

    public function testClientRulesExposeServerPatternsAndFormattedMessages(): void
    {
        $rules = PersonValidator::clientRules();

        self::assertSame(Constants::PATTERNS, $rules['patterns']);
        self::assertSame(Constants::PATTERN_FLAGS, $rules['flags']);
        self::assertSame(Constants::REQUIRED_FIELDS, $rules['required']);
        self::assertSame(Constants::MAX_LENGTH, $rules['maxLength']);
        self::assertStringNotContainsString('%d', json_encode($rules['messages'], JSON_UNESCAPED_UNICODE));
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

    /** @param list<string> $values */
    private static function cases(array $values): array
    {
        $cases = [];
        foreach ($values as $value) {
            $cases[json_encode($value, JSON_UNESCAPED_UNICODE)] = [$value];
        }

        return $cases;
    }
}

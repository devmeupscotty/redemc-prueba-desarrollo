<?php

declare(strict_types=1);

namespace App;

use Normalizer;

final class PersonValidator
{
    private const PATTERN_DELIMITER = '~';
    private const DOLLAR_END_ONLY = 'D';
    private const EDGE_WHITESPACE = '/^[\s\x{FEFF}]+|[\s\x{FEFF}]+$/u';
    private const WHITESPACE_RUN = '/[\s\x{FEFF}]+/u';

    /**
     * @param array<string, mixed> $input
     * @return array{0: ?Person, 1: array<string, string>} the valid Person, or null plus the errors by field
     */
    public function validate(array $input): array
    {
        $data = $this->normalize($input);
        $errors = [];

        foreach (Constants::VALIDATED_FIELDS as $field) {
            $rule = $this->failedRule($field, $data[$field]);
            if ($rule !== null) {
                $errors[$field] = self::message($field, $rule);
            }
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [new Person(
            $data[Constants::FIELD_NAME],
            $data[Constants::FIELD_EMAIL],
            $data[Constants::FIELD_PHONE],
        ), []];
    }

    public static function message(string $field, string $rule): string
    {
        return sprintf(Constants::MESSAGES[$field][$rule], Constants::MAX_LENGTH[$field] ?? 0);
    }

    /** @return array<string, mixed> */
    public static function clientRules(): array
    {
        $messages = [];
        foreach (Constants::MESSAGES as $field => $rules) {
            foreach (array_keys($rules) as $rule) {
                $messages[$field][$rule] = self::message($field, $rule);
            }
        }

        return [
            Constants::CONFIG_FIELDS => Constants::VALIDATED_FIELDS,
            Constants::CONFIG_REQUIRED => Constants::REQUIRED_FIELDS,
            Constants::CONFIG_PATTERNS => Constants::PATTERNS,
            Constants::CONFIG_FLAGS => Constants::PATTERN_FLAGS,
            Constants::CONFIG_MAX_LENGTH => Constants::MAX_LENGTH,
            Constants::CONFIG_COLLAPSE_WHITESPACE => Constants::COLLAPSE_WHITESPACE_FIELDS,
            Constants::CONFIG_LOWERCASE => Constants::LOWERCASE_FIELDS,
            Constants::CONFIG_MESSAGES => $messages,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, ?string>
     */
    private function normalize(array $input): array
    {
        $data = [];
        foreach (Constants::VALIDATED_FIELDS as $field) {
            $value = self::text($input, $field);
            if (in_array($field, Constants::COLLAPSE_WHITESPACE_FIELDS, true)) {
                $value = preg_replace(self::WHITESPACE_RUN, ' ', $value) ?? $value;
            }
            if (in_array($field, Constants::LOWERCASE_FIELDS, true)) {
                $value = mb_strtolower($value);
            }
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function failedRule(string $field, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return in_array($field, Constants::REQUIRED_FIELDS, true) ? Constants::RULE_REQUIRED : null;
        }

        $maxLength = Constants::MAX_LENGTH[$field] ?? null;
        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            return Constants::RULE_LENGTH;
        }

        return preg_match(self::serverPattern($field), $value) === 1 ? null : Constants::RULE_FORMAT;
    }

    /**
     * PCRE's "$" also matches before a trailing newline; the D modifier makes it behave like JavaScript's.
     */
    private static function serverPattern(string $field): string
    {
        return self::PATTERN_DELIMITER . Constants::PATTERNS[$field] . self::PATTERN_DELIMITER
            . Constants::PATTERN_FLAGS . self::DOLLAR_END_ONLY;
    }

    /** @param array<string, mixed> $input */
    private static function text(array $input, string $field): string
    {
        $value = $input[$field] ?? '';
        if (!is_string($value)) {
            return '';
        }

        $value = preg_replace(self::EDGE_WHITESPACE, '', $value) ?? $value;
        return Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
    }
}

<?php

declare(strict_types=1);

namespace App;

use Normalizer;

final class PersonValidator
{
    private const PATTERN_DELIMITER = '/';
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

    /**
     * @param array<string, mixed> $input
     * @return array<string, ?string>
     */
    public function normalize(array $input): array
    {
        $name = self::collapseWhitespace(self::text($input, Constants::FIELD_NAME));
        $email = mb_strtolower(self::text($input, Constants::FIELD_EMAIL));
        $phone = self::text($input, Constants::FIELD_PHONE);

        return [
            Constants::FIELD_NAME => $name,
            Constants::FIELD_EMAIL => $email,
            Constants::FIELD_PHONE => $phone === '' ? null : $phone,
        ];
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
            'fields' => Constants::VALIDATED_FIELDS,
            'required' => Constants::REQUIRED_FIELDS,
            'patterns' => Constants::PATTERNS,
            'flags' => Constants::PATTERN_FLAGS,
            'maxLength' => Constants::MAX_LENGTH,
            'messages' => $messages,
        ];
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

        $pattern = self::PATTERN_DELIMITER . Constants::PATTERNS[$field] . self::PATTERN_DELIMITER . Constants::PATTERN_FLAGS;

        return preg_match($pattern, $value) === 1 ? null : Constants::RULE_FORMAT;
    }

    /** @param array<string, mixed> $input */
    private static function text(array $input, string $field): string
    {
        $value = $input[$field] ?? '';
        if (!is_string($value)) {
            return '';
        }

        $value = preg_replace(self::EDGE_WHITESPACE, '', $value) ?? $value;
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        return $value;
    }

    private static function collapseWhitespace(string $value): string
    {
        return preg_replace(self::WHITESPACE_RUN, ' ', $value) ?? $value;
    }
}

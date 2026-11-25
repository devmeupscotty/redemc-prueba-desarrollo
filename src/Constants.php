<?php

declare(strict_types=1);

namespace App;

final class Constants
{
    public const ENV_FILE = '.env';
    public const DB_CHARSET = 'utf8mb4';
    public const DB_DEFAULT_PORT = '3306';
    public const DB_TIME_ZONE = '+00:00';

    public const PAGE_SIZE = 8;
    public const FIRST_PAGE = 1;
    public const QUERY_PAGE = 'page';

    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_UNPROCESSABLE = 422;
    public const HTTP_SERVER_ERROR = 500;

    public const RESPONSE_DATA = 'data';
    public const RESPONSE_META = 'meta';
    public const RESPONSE_ERRORS = 'errors';
    public const RESPONSE_MESSAGE = 'message';

    public const DUPLICATE_ENTRY_ERROR = 1062;
    public const DUPLICATE_KEYS = [
        'uq_person_email' => self::FIELD_EMAIL,
        'uq_person_phone' => self::FIELD_PHONE,
    ];

    public const FIELD_ID = 'id';
    public const FIELD_NAME = 'name';
    public const FIELD_EMAIL = 'email';
    public const FIELD_PHONE = 'phone';
    public const FIELD_CREATED_AT = 'createdAt';

    public const RULE_REQUIRED = 'required';
    public const RULE_LENGTH = 'length';
    public const RULE_FORMAT = 'format';
    public const RULE_DUPLICATE = 'duplicate';

    public const VALIDATED_FIELDS = [self::FIELD_NAME, self::FIELD_EMAIL, self::FIELD_PHONE];
    public const REQUIRED_FIELDS = [self::FIELD_NAME, self::FIELD_EMAIL];
    public const UNIQUE_FIELDS = [self::FIELD_EMAIL, self::FIELD_PHONE];

    public const PATTERN_FLAGS = 'u';
    public const PATTERNS = [
        self::FIELD_NAME => '^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$',
        self::FIELD_EMAIL => '^[A-Za-z0-9_%+-]+(?:\.[A-Za-z0-9_%+-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$',
        self::FIELD_PHONE => '^09[0-9]{7}$',
    ];

    public const MAX_LENGTH = [
        self::FIELD_NAME => 100,
        self::FIELD_EMAIL => 254,
    ];

    public const MESSAGES = [
        self::FIELD_NAME => [
            self::RULE_REQUIRED => 'El nombre es obligatorio.',
            self::RULE_LENGTH => 'El nombre no puede superar los %d caracteres.',
            self::RULE_FORMAT => 'El nombre solo puede contener letras y espacios.',
        ],
        self::FIELD_EMAIL => [
            self::RULE_REQUIRED => 'El email es obligatorio.',
            self::RULE_LENGTH => 'El email no puede superar los %d caracteres.',
            self::RULE_FORMAT => 'El formato del email no es válido (ej: nombre@dominio.com).',
            self::RULE_DUPLICATE => 'Este email ya está registrado.',
        ],
        self::FIELD_PHONE => [
            self::RULE_FORMAT => 'El teléfono debe tener 9 dígitos y comenzar con 09, sin espacios ni guiones (ej: 097680102).',
            self::RULE_DUPLICATE => 'Este teléfono ya está registrado.',
        ],
    ];
}

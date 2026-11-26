<?php

declare(strict_types=1);

namespace App;

final class Constants
{
    public const ENV_FILE = '.env';

    public const ENV_DB = [
        'HOST' => 'DB_HOST',
        'PORT' => 'DB_PORT',
        'NAME' => 'DB_NAME',
        'USER' => 'DB_USER',
        'PASSWORD' => 'DB_PASSWORD',
        'TEST_NAME' => 'DB_TEST_NAME',
    ];

    public const DB = [
        'CHARSET' => 'utf8mb4',
        'COLLATION' => 'utf8mb4_unicode_ci',
        'NAME_PATTERN' => '/^[A-Za-z0-9_]+$/',
        'DEFAULT_PORT' => '3306',
        'TIME_ZONE' => '+00:00',
    ];

    public const PAGINATION = [
        'PAGE_SIZE' => 8,
        'FIRST_PAGE' => 1,
        'QUERY_PARAM' => 'page',
    ];

    public const ROUTE = [
        'HOME' => '/',
        'PERSONS' => '/api/persons',
    ];

    public const METHOD = [
        'GET' => 'GET',
        'HEAD' => 'HEAD',
        'POST' => 'POST',
    ];

    public const ROUTE_METHODS = [
        self::ROUTE['HOME'] => [self::METHOD['GET'], self::METHOD['HEAD']],
        self::ROUTE['PERSONS'] => [self::METHOD['GET'], self::METHOD['HEAD'], self::METHOD['POST']],
    ];

    public const FETCH_SITE = [
        'HEADER' => 'HTTP_SEC_FETCH_SITE',
        'CROSS_SITE' => 'cross-site',
    ];

    public const ERROR_MESSAGE = [
        'NOT_FOUND' => 'Recurso no encontrado.',
        'CROSS_SITE' => 'No se aceptan envíos desde otros sitios.',
        'METHOD_NOT_ALLOWED' => 'Método no permitido.',
        'SERVER_ERROR' => 'Ocurrió un error inesperado. Intentá nuevamente en unos instantes.',
    ];

    public const HTTP = [
        'OK' => 200,
        'CREATED' => 201,
        'FORBIDDEN' => 403,
        'NOT_FOUND' => 404,
        'METHOD_NOT_ALLOWED' => 405,
        'UNPROCESSABLE' => 422,
        'SERVER_ERROR' => 500,
    ];

    public const RESPONSE = [
        'DATA' => 'data',
        'META' => 'meta',
        'ERRORS' => 'errors',
        'MESSAGE' => 'message',
    ];

    public const META = [
        'PAGE' => 'page',
        'PER_PAGE' => 'perPage',
        'TOTAL' => 'total',
        'TOTAL_PAGES' => 'totalPages',
    ];

    public const CONFIG = [
        'FIELDS' => 'fields',
        'REQUIRED' => 'required',
        'PATTERNS' => 'patterns',
        'FLAGS' => 'flags',
        'MAX_LENGTH' => 'maxLength',
        'COLLAPSE_WHITESPACE' => 'collapseWhitespace',
        'LOWERCASE' => 'lowercase',
        'MESSAGES' => 'messages',
        'ENDPOINT' => 'endpoint',
        'PAGE_SIZE' => 'pageSize',
        'UNPROCESSABLE_STATUS' => 'unprocessableStatus',
        'SERVER_ERROR' => 'serverError',
    ];

    public const FIELD = [
        'ID' => 'id',
        'NAME' => 'name',
        'EMAIL' => 'email',
        'PHONE' => 'phone',
        'CREATED_AT' => 'createdAt',
    ];

    public const DUPLICATE = [
        'ENTRY_ERROR' => 1062,
        'KEYS' => [
            'uq_person_email' => self::FIELD['EMAIL'],
            'uq_person_phone' => self::FIELD['PHONE'],
        ],
    ];

    public const RULE = [
        'REQUIRED' => 'required',
        'LENGTH' => 'length',
        'FORMAT' => 'format',
        'DUPLICATE' => 'duplicate',
    ];

    public const VALIDATED_FIELDS = [self::FIELD['NAME'], self::FIELD['EMAIL'], self::FIELD['PHONE']];
    public const REQUIRED_FIELDS = [self::FIELD['NAME'], self::FIELD['EMAIL']];
    public const UNIQUE_FIELDS = [self::FIELD['EMAIL'], self::FIELD['PHONE']];
    public const COLLAPSE_WHITESPACE_FIELDS = [self::FIELD['NAME']];
    public const LOWERCASE_FIELDS = [self::FIELD['EMAIL']];

    public const PATTERN_FLAGS = 'u';
    public const PATTERNS = [
        self::FIELD['NAME'] => '^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?: [A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$',
        self::FIELD['EMAIL'] => '^[A-Za-z0-9_%+-]+(?:\.[A-Za-z0-9_%+-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$',
        self::FIELD['PHONE'] => '^09[0-9]{7}$',
    ];

    public const MAX_LENGTH = [
        self::FIELD['NAME'] => 100,
        self::FIELD['EMAIL'] => 254,
    ];

    public const MESSAGES = [
        self::FIELD['NAME'] => [
            self::RULE['REQUIRED'] => 'El nombre es obligatorio.',
            self::RULE['LENGTH'] => 'El nombre no puede superar los %d caracteres.',
            self::RULE['FORMAT'] => 'El nombre solo puede contener letras y espacios.',
        ],
        self::FIELD['EMAIL'] => [
            self::RULE['REQUIRED'] => 'El email es obligatorio.',
            self::RULE['LENGTH'] => 'El email no puede superar los %d caracteres.',
            self::RULE['FORMAT'] => 'El formato del email no es válido (ej: nombre@dominio.com).',
            self::RULE['DUPLICATE'] => 'Este email ya está registrado.',
        ],
        self::FIELD['PHONE'] => [
            self::RULE['FORMAT'] => 'El teléfono debe tener 9 dígitos y comenzar con 09, sin espacios ni guiones (ej: 097680102).',
            self::RULE['DUPLICATE'] => 'Este teléfono ya está registrado.',
        ],
    ];
}

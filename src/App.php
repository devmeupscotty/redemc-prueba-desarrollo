<?php

declare(strict_types=1);

namespace App;

use Throwable;

final class App
{
    private const PUBLIC_DIR = __DIR__ . '/../public';
    private const HOME_TEMPLATE = __DIR__ . '/../templates/home.php';
    private const BUILT_IN_SERVER = 'cli-server';
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
    private const INLINE_JSON_FLAGS = self::JSON_FLAGS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    /**
     * Returns false only under PHP's built-in server, to let it serve existing static files.
     */
    public static function run(): bool
    {
        $path = self::requestPath();
        if (PHP_SAPI === self::BUILT_IN_SERVER && self::isPublicFile($path)) {
            return false;
        }

        try {
            self::dispatch($path, $_SERVER['REQUEST_METHOD'] ?? Constants::METHOD['GET']);
        } catch (Throwable $exception) {
            error_log((string) $exception);
            self::sendJson(Constants::HTTP['SERVER_ERROR'], [Constants::RESPONSE['MESSAGE'] => Constants::ERROR_MESSAGE['SERVER_ERROR']]);
        }

        return true;
    }

    private static function dispatch(string $path, string $method): void
    {
        $allowedMethods = Constants::ROUTE_METHODS[$path] ?? null;

        if ($allowedMethods === null) {
            self::sendJson(Constants::HTTP['NOT_FOUND'], [Constants::RESPONSE['MESSAGE'] => Constants::ERROR_MESSAGE['NOT_FOUND']]);
            return;
        }

        if (!in_array($method, $allowedMethods, true)) {
            header('Allow: ' . implode(', ', $allowedMethods));
            self::sendJson(Constants::HTTP['METHOD_NOT_ALLOWED'], [Constants::RESPONSE['MESSAGE'] => Constants::ERROR_MESSAGE['METHOD_NOT_ALLOWED']]);
            return;
        }

        if ($method === Constants::METHOD['POST'] && self::isCrossSiteRequest()) {
            self::sendJson(Constants::HTTP['FORBIDDEN'], [Constants::RESPONSE['MESSAGE'] => Constants::ERROR_MESSAGE['CROSS_SITE']]);
            return;
        }

        if ($path === Constants::ROUTE['HOME']) {
            self::renderHome();
            return;
        }

        $controller = new PersonController(new PersonValidator(), new PersonRepository(Database::connect()));
        [$status, $body] = $method === Constants::METHOD['POST']
            ? $controller->store($_POST)
            : $controller->index($_GET);

        self::sendJson($status, $body);
    }

    /**
     * There are no sessions or cookies to protect, but this stops other sites from
     * making their visitors' browsers submit the form (spam via cross-site POSTs).
     */
    private static function isCrossSiteRequest(): bool
    {
        return ($_SERVER[Constants::FETCH_SITE['HEADER']] ?? null) === Constants::FETCH_SITE['CROSS_SITE'];
    }

    private static function renderHome(): void
    {
        $clientConfig = json_encode(PersonValidator::clientRules() + [
            Constants::CONFIG['ENDPOINT'] => Constants::ROUTE['PERSONS'],
            Constants::CONFIG['PAGE_SIZE'] => Constants::PAGINATION['PAGE_SIZE'],
            Constants::CONFIG['UNPROCESSABLE_STATUS'] => Constants::HTTP['UNPROCESSABLE'],
            Constants::CONFIG['SERVER_ERROR'] => Constants::ERROR_MESSAGE['SERVER_ERROR'],
        ], self::INLINE_JSON_FLAGS);

        header('Content-Type: text/html; charset=utf-8');
        require self::HOME_TEMPLATE;
    }

    /** @param array<string, mixed> $body */
    private static function sendJson(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($body, self::JSON_FLAGS);
    }

    private static function requestPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? rtrim($path, '/') : '';

        return $path === '' ? '/' : $path;
    }

    private static function isPublicFile(string $path): bool
    {
        $publicDir = realpath(self::PUBLIC_DIR);
        $file = realpath(self::PUBLIC_DIR . $path);

        return $publicDir !== false && $file !== false && is_file($file) && str_starts_with($file, $publicDir);
    }
}

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
            self::dispatch($path, $_SERVER['REQUEST_METHOD'] ?? Constants::METHOD_GET);
        } catch (Throwable $exception) {
            error_log((string) $exception);
            self::sendJson(Constants::HTTP_SERVER_ERROR, [Constants::RESPONSE_MESSAGE => Constants::MESSAGE_SERVER_ERROR]);
        }

        return true;
    }

    private static function dispatch(string $path, string $method): void
    {
        $allowedMethods = match ($path) {
            Constants::ROUTE_HOME => Constants::HOME_METHODS,
            Constants::ROUTE_PERSONS => Constants::PERSONS_METHODS,
            default => null,
        };

        if ($allowedMethods === null) {
            self::sendJson(Constants::HTTP_NOT_FOUND, [Constants::RESPONSE_MESSAGE => Constants::MESSAGE_NOT_FOUND]);
            return;
        }

        if (!in_array($method, $allowedMethods, true)) {
            header('Allow: ' . implode(', ', $allowedMethods));
            self::sendJson(Constants::HTTP_METHOD_NOT_ALLOWED, [Constants::RESPONSE_MESSAGE => Constants::MESSAGE_METHOD_NOT_ALLOWED]);
            return;
        }

        if ($method === Constants::METHOD_POST && self::isCrossSiteRequest()) {
            self::sendJson(Constants::HTTP_FORBIDDEN, [Constants::RESPONSE_MESSAGE => Constants::MESSAGE_CROSS_SITE]);
            return;
        }

        if ($path === Constants::ROUTE_HOME) {
            self::renderHome();
            return;
        }

        $controller = new PersonController(new PersonValidator(), new PersonRepository(Database::connect()));
        [$status, $body] = $method === Constants::METHOD_POST
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
        return ($_SERVER[Constants::FETCH_SITE_HEADER] ?? null) === Constants::FETCH_SITE_CROSS_SITE;
    }

    private static function renderHome(): void
    {
        $clientConfig = json_encode(PersonValidator::clientRules() + [
            Constants::CONFIG_ENDPOINT => Constants::ROUTE_PERSONS,
            Constants::CONFIG_PAGE_SIZE => Constants::PAGE_SIZE,
            Constants::CONFIG_UNPROCESSABLE_STATUS => Constants::HTTP_UNPROCESSABLE,
            Constants::CONFIG_SERVER_ERROR => Constants::MESSAGE_SERVER_ERROR,
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

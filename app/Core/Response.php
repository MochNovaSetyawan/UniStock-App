<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response – HTTP response helpers.
 */
final class Response
{
    private function __construct() {}

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonOk(mixed $data = null, string $message = ''): never
    {
        $payload = ['ok' => true];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        self::json($payload);
    }

    public static function jsonError(string $message, int $status = 400): never
    {
        self::json(['ok' => false, 'error' => $message], $status);
    }
}

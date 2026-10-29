<?php
declare(strict_types=1);

namespace EventSpot;

final class Http
{
    public static function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            return $_POST;
        }
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return [];
        }
        try {
            $input = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::error('INVALID_JSON', 'Malformed JSON request body.', 400);
        }
        return is_array($input) ? $input : [];
    }

    public static function json(array $data = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $code, string $message, int $status = 400, array $fields = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $message, 'fields' => $fields]], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
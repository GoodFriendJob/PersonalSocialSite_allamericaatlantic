<?php

class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }

    public static function success(array $data = [], int $status = 200): void
    {
        $payload = array_merge(['success' => true], $data);
        self::json($payload, $status);
    }
}

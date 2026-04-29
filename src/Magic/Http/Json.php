<?php

namespace Magic\Http;

final class Json
{
    /**
     * Returns the decoded JSON body of the current request, or [] if absent.
     * @return array<string,mixed>
     */
    public static function readBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function send(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function error(string $message, int $status = 400, array $extra = []): never
    {
        self::send(['error' => $message] + $extra, $status);
    }
}

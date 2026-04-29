<?php

namespace Magic;

/**
 * Thin facade over the host site's AuthService.
 *
 * Future extraction: drop in the magic project's own auth here. Every Magic
 * class reads the current user via Auth::current().
 */
final class Auth
{
    public static function current(\PDO $pdo): ?array
    {
        require_once __DIR__ . '/../Service/AuthService.php';
        $svc = new \AuthService($pdo);
        return $svc->getAuthenticatedUser();
    }
}

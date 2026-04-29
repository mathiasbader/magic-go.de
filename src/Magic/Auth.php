<?php

namespace Magic;

/**
 * Thin facade in front of the legacy global-namespace AuthService — keeps the
 * domain layer's call site simple (`Auth::current($pdo)`).
 */
final class Auth
{
    public static function current(\PDO $pdo): ?array
    {
        return (new \AuthService($pdo))->getAuthenticatedUser();
    }
}

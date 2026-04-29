<?php

namespace Magic;

/**
 * Thin facade over the host site's DbService.
 *
 * Future extraction: replace the require_once with the magic project's own
 * DbService (or any PDO factory) — every Magic class only ever asks Db::connect().
 */
final class Db
{
    private static ?\PDO $pdo = null;

    public static function connect(): \PDO
    {
        if (self::$pdo !== null) return self::$pdo;

        require_once __DIR__ . '/../Service/DbService.php';
        $svc = new \DbService();
        $svc->runMigrations();
        self::$pdo = $svc->getConnection();
        return self::$pdo;
    }
}

<?php

namespace Magic;

/**
 * Thin facade in front of the legacy global-namespace DbService — keeps the
 * domain layer's call site simple (`Db::connect()`) and gives us one place
 * to swap PDO factories later if we ever do.
 */
final class Db
{
    private static ?\PDO $pdo = null;

    public static function connect(): \PDO
    {
        if (self::$pdo !== null) return self::$pdo;
        $svc = new \DbService();
        $svc->assertSchemaInitialized();
        $svc->runMigrations();
        self::$pdo = $svc->getConnection();
        return self::$pdo;
    }
}

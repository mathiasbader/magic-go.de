<?php

namespace Magic;

/**
 * Single bootstrap entry for the Magic module.
 *
 * Constructs the shared services (PDO + authenticated user) used by every
 * entry point. Class loading is handled by Composer's PSR-4 autoloader —
 * entry points must `require __DIR__ . '/.../vendor/autoload.php'` before
 * calling Bootstrap::init().
 */
final class Bootstrap
{
    public static function init(): self
    {
        return new self();
    }

    private ?\PDO $pdo = null;
    private ?array $user = null;
    private bool $userLoaded = false;

    public function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Db::connect();
        }
        return $this->pdo;
    }

    /** @return array{id:int,...}|null */
    public function user(): ?array
    {
        if (!$this->userLoaded) {
            $this->user = Auth::current($this->pdo());
            $this->userLoaded = true;
        }
        return $this->user;
    }

    /**
     * Redirects to login if the user is unauthenticated. Returns the user array.
     * @return array{id:int,...}
     */
    public function requireUser(string $loginUrl = '/'): array
    {
        $u = $this->user();
        if (!$u) {
            header('Location: ' . $loginUrl);
            exit;
        }
        return $u;
    }
}

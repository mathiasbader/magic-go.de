<?php

namespace Magic;

/**
 * Single bootstrap entry for the Magic module.
 *
 * Registers a PSR-4 autoloader for the Magic\ namespace and constructs the
 * shared services (PDO + authenticated user) used by every entry point.
 *
 * The Magic module is designed to live as a self-contained subtree under
 * src/Magic/ so it can be extracted into its own project later. The only
 * external dependency today is on the host site's DbService + AuthService,
 * which are wrapped behind Magic\Db and Magic\Auth so the seam is small.
 */
final class Bootstrap
{
    private static bool $autoloaderRegistered = false;

    public static function init(): self
    {
        self::registerAutoloader();
        return new self();
    }

    private static function registerAutoloader(): void
    {
        if (self::$autoloaderRegistered) return;
        spl_autoload_register(static function (string $class): void {
            if (strncmp($class, 'Magic\\', 6) !== 0) return;
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 6));
            $path = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';
            if (is_file($path)) require $path;
        });
        self::$autoloaderRegistered = true;
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

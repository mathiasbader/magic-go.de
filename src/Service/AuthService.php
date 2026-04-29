<?php

class AuthService
{
    private PDO $pdo;
    private const COOKIE_NAME = 'auth_token';
    private const LIFETIME_DAYS = 60;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAuthenticatedUser(): ?array
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$token) return null;

        try {
            $stmt = $this->pdo->prepare('SELECT u.id, u.email FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.token = :token AND s.expires_at > NOW()');
            $stmt->execute(['token' => $token]);
            $user = $stmt->fetch();
        } catch (\PDOException $e) {
            return null;
        }

        if (!$user) {
            $this->clearCookie();
            return null;
        }

        // Refresh expiry
        $newExpiry = date('Y-m-d H:i:s', strtotime('+' . self::LIFETIME_DAYS . ' days'));
        $this->pdo->prepare('UPDATE sessions SET expires_at = :expires WHERE token = :token')
            ->execute(['expires' => $newExpiry, 'token' => $token]);
        $this->setCookie($token);

        return $user;
    }

    public function login(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT id, password FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+' . self::LIFETIME_DAYS . ' days'));

        $this->pdo->prepare('INSERT INTO sessions (token, user_id, expires_at) VALUES (:token, :user_id, :expires)')
            ->execute(['token' => $token, 'user_id' => $user['id'], 'expires' => $expires]);

        $this->setCookie($token);
        return true;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $stmt = $this->pdo->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->pdo->prepare('UPDATE users SET password = :password WHERE id = :id')
            ->execute(['password' => $hash, 'id' => $userId]);

        return true;
    }

    public function logout(): void
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($token) {
            $this->pdo->prepare('DELETE FROM sessions WHERE token = :token')->execute(['token' => $token]);
        }
        $this->clearCookie();
    }

    private function setCookie(string $token): void
    {
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::LIFETIME_DAYS * 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', ['expires' => 1, 'path' => '/']);
    }
}

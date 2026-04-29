<?php

namespace Magic\Service;

final class UserSettingsService
{
    public function __construct(private \PDO $pdo) {}

    public function get(int $userId, string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM user_settings WHERE user_id = :uid AND setting_key = :k');
        $stmt->execute(['uid' => $userId, 'k' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    public function has(int $userId, string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM user_settings WHERE user_id = :uid AND setting_key = :k');
        $stmt->execute(['uid' => $userId, 'k' => $key]);
        return (bool)$stmt->fetchColumn();
    }

    public function save(int $userId, string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (:uid, :k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute(['uid' => $userId, 'k' => $key, 'v' => $value]);
    }
}

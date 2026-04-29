<?php

class DbService
{
    private PDO $pdo;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/db.php';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'] ?? 3306, $config['database']);
        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
        } catch (\PDOException $e) {
            http_response_code(503);
            echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Keine Verbindung</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh}.box{background:#1e293b;border:1px solid #475569;border-radius:12px;padding:2rem;width:380px;max-width:90vw;text-align:center}.icon{margin:0 auto 1.25rem}h1{font-size:1.1rem;margin-bottom:.75rem;color:#f87171}p{font-size:.88rem;color:#94a3b8;line-height:1.5}.retry{display:inline-block;margin-top:1.25rem;background:#334155;color:#e2e8f0;border:1px solid #475569;border-radius:8px;padding:.5rem 1.2rem;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit;transition:background .12s}.retry:hover{background:#475569}</style></head><body><div class="box"><div class="icon"><svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/><line x1="2" y1="2" x2="22" y2="22" stroke="#f87171" stroke-width="2"/></svg></div><h1>Keine Datenbankverbindung</h1><p>Der Datenbankserver ist nicht erreichbar. Bitte stelle sicher, dass MariaDb l&auml;uft und versuche es erneut.</p><a href="" class="retry">Erneut versuchen</a></div></body></html>';
            exit;
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function runMigrations(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        $executed = $this->pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        $migrationsDir = __DIR__ . '/../../migrations';
        $files = glob($migrationsDir . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $executed)) {
                continue;
            }

            $sql = file_get_contents($file);
            // Remove SQL comments
            $sql = preg_replace('/--.*$/m', '', $sql);
            $statements = preg_split('/;\s*\n/', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement, " \t\n\r\0\x0B;");
                if ($statement === '') continue;
                try {
                    $result = $this->pdo->query($statement);
                    if ($result) $result->closeCursor();
                } catch (\PDOException $e) {
                    // Skip errors for migrations that may partially apply (e.g. table already renamed)
                }
            }

            $insert = $this->pdo->prepare('INSERT INTO migrations (filename) VALUES (:filename)');
            $insert->execute(['filename' => $filename]);
            $insert->closeCursor();
        }
    }
}

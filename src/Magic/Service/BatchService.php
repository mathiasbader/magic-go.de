<?php

namespace Magic\Service;

final class BatchService
{
    public function __construct(private \PDO $pdo) {}

    /** @return array{batch_id:int} */
    public function create(int $userId, ?string $filename, ?string $format, int $cardCount): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO magic_import_batches (user_id, filename, format, card_count)
             VALUES (:uid, :filename, :format, :count)'
        );
        $stmt->execute(['uid' => $userId, 'filename' => $filename, 'format' => $format, 'count' => $cardCount]);
        return ['batch_id' => (int)$this->pdo->lastInsertId()];
    }

    public function updateCount(int $userId, int $batchId, int $cardCount, ?string $sets = null): void
    {
        $this->pdo->prepare(
            'UPDATE magic_import_batches SET card_count = :count, sets_imported = :sets
             WHERE id = :id AND user_id = :uid'
        )->execute([
            'count' => $cardCount,
            'sets' => $sets,
            'id' => $batchId,
            'uid' => $userId,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function listAll(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT ib.*, COUNT(mc.id) AS current_cards
            FROM magic_import_batches ib
            LEFT JOIN magic_cards mc ON mc.import_batch_id = ib.id
            WHERE ib.user_id = :uid
            GROUP BY ib.id
            ORDER BY ib.imported_at DESC
        ');
        $stmt->execute(['uid' => $userId]);
        $batches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $binderStmt = $this->pdo->prepare(
            "SELECT COALESCE(binder, '') AS binder, COUNT(*) AS cnt
             FROM magic_cards WHERE import_batch_id = :bid AND user_id = :uid
             GROUP BY COALESCE(binder, '')"
        );
        foreach ($batches as &$b) {
            $binderStmt->execute(['bid' => $b['id'], 'uid' => $userId]);
            $b['binders'] = $binderStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $batches;
    }

    public function delete(int $userId, int $batchId): void
    {
        $this->pdo->prepare('DELETE FROM magic_cards WHERE import_batch_id = :bid AND user_id = :uid')
            ->execute(['bid' => $batchId, 'uid' => $userId]);
        $this->pdo->prepare('DELETE FROM magic_import_batches WHERE id = :bid AND user_id = :uid')
            ->execute(['bid' => $batchId, 'uid' => $userId]);
    }

    public function deleteUnassigned(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM magic_cards WHERE import_batch_id IS NULL AND user_id = :uid')
            ->execute(['uid' => $userId]);
    }

    public function countUnassigned(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM magic_cards WHERE import_batch_id IS NULL AND user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}

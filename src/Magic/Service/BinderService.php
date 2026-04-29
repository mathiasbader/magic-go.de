<?php

namespace Magic\Service;

final class BinderService
{
    public function __construct(private \PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function listAll(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(binder, '') AS binder,
                    COUNT(*) AS card_count,
                    COALESCE(SUM(market_price), 0) AS total_price,
                    CASE WHEN COUNT(DISTINCT language) = 1 THEN MIN(language) ELSE NULL END AS common_language
             FROM magic_cards
             WHERE user_id = :uid
             GROUP BY COALESCE(binder, '')
             ORDER BY binder"
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function move(int $userId, string $from, ?string $to, ?int $batchId = null): void
    {
        $batchClause = $batchId ? ' AND import_batch_id = :bid' : '';
        $params = ['to' => $to ?: null, 'uid' => $userId];
        if ($batchId) $params['bid'] = $batchId;

        if ($from === '') {
            $sql = 'UPDATE magic_cards SET binder = :to WHERE (binder IS NULL OR binder = "") AND user_id = :uid' . $batchClause;
        } else {
            $params['from'] = $from;
            $sql = 'UPDATE magic_cards SET binder = :to WHERE binder = :from AND user_id = :uid' . $batchClause;
        }
        $this->pdo->prepare($sql)->execute($params);
    }

    /** @return list<array<string,mixed>> */
    public function listCards(int $userId, string $binder): array
    {
        if ($binder === '') {
            $stmt = $this->pdo->prepare(
                'SELECT id, scryfall_id, set_code, collector_number, language
                 FROM magic_cards WHERE user_id = :uid AND (binder IS NULL OR binder = "")'
            );
            $stmt->execute(['uid' => $userId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, scryfall_id, set_code, collector_number, language
                 FROM magic_cards WHERE user_id = :uid AND binder = :binder'
            );
            $stmt->execute(['uid' => $userId, 'binder' => $binder]);
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

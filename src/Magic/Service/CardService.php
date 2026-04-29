<?php

namespace Magic\Service;

final class CardService
{
    public function __construct(
        private \PDO $pdo,
        private ArtistService $artists,
    ) {}

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mc.*, ma.name AS artist
             FROM magic_cards mc
             LEFT JOIN magic_artists ma ON mc.artist_id = ma.id
             WHERE mc.user_id = :uid
             ORDER BY mc.name ASC'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Inserts N copies of a single card. Each row is a separate physical copy.
     * @param array<string,mixed> $data
     */
    public function add(int $userId, array $data): void
    {
        $artistId = !empty($data['artist'])
            ? $this->artists->findOrCreate((string)$data['artist'])
            : null;

        $qty = max(1, (int)($data['quantity'] ?? 1));
        $stmt = $this->pdo->prepare('
            INSERT INTO magic_cards
                (user_id, scryfall_id, name, set_code, collector_number, quantity, notes,
                 image_uri_small, image_uri_normal, image_is_fallback, image_language,
                 mana_cost, type_line, rarity, language, foil, `condition`,
                 purchase_price, purchase_currency, binder, artist_id, import_batch_id)
            VALUES (:uid, :sid, :name, :set, :num, 1, :notes,
                    :img_s, :img_n, :img_fallback, :img_lang,
                    :mana, :type, :rarity, :lang, :foil, :cond,
                    :price, :currency, :binder, :artist_id, :batch_id)
        ');
        $params = [
            'uid' => $userId,
            'sid' => $data['scryfall_id'],
            'name' => $data['name'],
            'set' => $data['set_code'],
            'num' => $data['collector_number'],
            'notes' => $data['notes'] ?? null,
            'img_s' => $data['image_uri_small'] ?? null,
            'img_n' => $data['image_uri_normal'] ?? null,
            'img_fallback' => $data['image_is_fallback'] ?? 0,
            'img_lang' => $data['image_language'] ?? $data['language'] ?? 'en',
            'mana' => $data['mana_cost'] ?? null,
            'type' => $data['type_line'] ?? null,
            'rarity' => $data['rarity'] ?? null,
            'lang' => $data['language'] ?? 'en',
            'foil' => $data['foil'] ?? 0,
            'cond' => $data['condition'] ?? 'near_mint',
            'price' => $data['purchase_price'] ?? null,
            'currency' => $data['purchase_currency'] ?? null,
            'binder' => $data['binder'] ?? null,
            'artist_id' => $artistId,
            'batch_id' => $data['batch_id'] ?? null,
        ];
        for ($q = 0; $q < $qty; $q++) {
            $stmt->execute($params);
        }
    }

    /** @param array<string,mixed> $data */
    public function update(int $userId, array $data): void
    {
        $fields = [];
        $params = ['id' => $data['id'], 'uid' => $userId];
        if (isset($data['quantity'])) {
            $fields[] = 'quantity = :qty';
            $params['qty'] = $data['quantity'];
        }
        if (array_key_exists('notes', $data)) {
            $fields[] = 'notes = :notes';
            $params['notes'] = $data['notes'];
        }
        if (!$fields) return;
        $sql = 'UPDATE magic_cards SET ' . implode(', ', $fields) . ' WHERE id = :id AND user_id = :uid';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $userId, int $cardId): void
    {
        $this->pdo->prepare('DELETE FROM magic_cards WHERE id = :id AND user_id = :uid')
            ->execute(['id' => $cardId, 'uid' => $userId]);
    }

    /**
     * Resolves the card detail page input (numeric DB id or scryfall UUID) into
     * the "current" card row plus all owned copies sharing the same scryfall_id.
     *
     * @return array{
     *   scryfall_id: string,
     *   current: ?array<string,mixed>,
     *   copies: list<array<string,mixed>>,
     * }|null  null when neither id nor scryfall_id resolves to anything.
     */
    public function findByIdOrScryfall(int $userId, string $idOrScryfall): ?array
    {
        if ($idOrScryfall === '') return null;

        $current = null;
        $scryfallId = '';
        if (ctype_digit($idOrScryfall)) {
            $stmt = $this->pdo->prepare(
                'SELECT mc.*, ma.name AS artist
                 FROM magic_cards mc LEFT JOIN magic_artists ma ON mc.artist_id = ma.id
                 WHERE mc.id = :id AND mc.user_id = :uid'
            );
            $stmt->execute(['id' => (int)$idOrScryfall, 'uid' => $userId]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            $scryfallId = (string)($current['scryfall_id'] ?? '');
        } else {
            $scryfallId = $idOrScryfall;
        }

        $copies = [];
        if ($scryfallId !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT mc.*, ma.name AS artist
                 FROM magic_cards mc LEFT JOIN magic_artists ma ON mc.artist_id = ma.id
                 WHERE mc.user_id = :uid AND mc.scryfall_id = :sid
                 ORDER BY mc.id'
            );
            $stmt->execute(['uid' => $userId, 'sid' => $scryfallId]);
            $copies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!$current) $current = $copies[0] ?? null;
        } elseif ($current) {
            $copies = [$current];
        }

        if (!$current && $scryfallId === '') return null;

        return [
            'scryfall_id' => $scryfallId,
            'current' => $current,
            'copies' => $copies,
        ];
    }

    public function deleteAll(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM magic_cards WHERE user_id = :uid')->execute(['uid' => $userId]);
        $this->pdo->prepare('DELETE FROM magic_import_batches WHERE user_id = :uid')->execute(['uid' => $userId]);
    }

    /**
     * For an array of cards (each {scryfall_id, set_code, collector_number}),
     * return how many already exist plus a small per-duplicate summary.
     *
     * @param list<array<string,mixed>> $cards
     * @return array{count:int, duplicates:list<array<string,mixed>>}
     */
    public function checkExisting(int $userId, array $cards): array
    {
        if (!$cards) return ['count' => 0, 'duplicates' => []];

        $stmt = $this->pdo->prepare(
            'SELECT id, name, image_uri_small, COUNT(*) as copies
             FROM magic_cards
             WHERE user_id = :uid AND (scryfall_id = :sid OR (set_code = :set AND collector_number = :num))
             GROUP BY scryfall_id LIMIT 1'
        );
        $count = 0;
        $duplicates = [];
        foreach ($cards as $c) {
            $stmt->execute([
                'uid' => $userId,
                'sid' => $c['scryfall_id'] ?? '',
                'set' => $c['set_code'] ?? '',
                'num' => $c['collector_number'] ?? '',
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $count++;
                $duplicates[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'image' => $row['image_uri_small'],
                    'copies' => (int)$row['copies'],
                ];
            }
        }
        return ['count' => $count, 'duplicates' => $duplicates];
    }

    /** @param array<string,mixed> $data */
    public function updateLanguage(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE magic_cards
             SET scryfall_id = :sid, language = :lang, name = :name,
                 image_uri_small = :img_s, image_uri_normal = :img_n,
                 image_language = :img_lang, image_is_fallback = :img_fb
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            'sid' => $data['scryfall_id'],
            'lang' => $data['language'],
            'name' => $data['name'],
            'img_s' => $data['image_uri_small'] ?? null,
            'img_n' => $data['image_uri_normal'] ?? null,
            'img_lang' => $data['image_language'] ?? $data['language'],
            'img_fb' => $data['image_is_fallback'] ?? 0,
            'id' => $data['id'],
            'uid' => $userId,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function updatePrice(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE magic_cards
             SET market_price = :price, market_price_date = :date, market_price_is_english = :is_en
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            'price' => $data['market_price'],
            'date' => $data['market_price_date'],
            'is_en' => $data['market_price_is_english'] ?? null,
            'id' => $data['id'],
            'uid' => $userId,
        ]);
    }

    /**
     * Aggregate the user's collection by name+characteristics for AI input.
     * @return list<array{name:string, mana_cost:?string, type_line:?string, rarity:?string, qty:int}>
     */
    public function aggregateForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT name, mana_cost, type_line, rarity, COUNT(*) AS qty
            FROM magic_cards
            WHERE user_id = :uid
            GROUP BY name, mana_cost, type_line, rarity
            ORDER BY name
        ');
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$r) $r['qty'] = (int)$r['qty'];
        return $rows;
    }
}

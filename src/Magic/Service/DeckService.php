<?php

namespace Magic\Service;

final class DeckService
{
    public function __construct(private \PDO $pdo) {}

    /**
     * List all decks for a user, decoding JSON columns and resolving the main
     * card image (with fallback to any owned key card if the main isn't found).
     * @return list<array<string,mixed>>
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM magic_decks WHERE user_id = :uid ORDER BY is_favorite DESC, created_at DESC'
        );
        $stmt->execute(['uid' => $userId]);
        $decks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($decks as &$d) {
            $this->hydrate($d);
            $d['main_card_image'] = $this->findFirstCardImage(
                $userId,
                array_filter(array_merge([$d['main_card'] ?? null], $d['key_cards']))
            );
        }
        return $decks;
    }

    public function findById(int $userId, int $deckId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM magic_decks WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $deckId, 'uid' => $userId]);
        $deck = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$deck) return null;
        $this->hydrate($deck);
        return $deck;
    }

    /**
     * Returns the deck plus its full decklist resolved against the user's
     * collection. Cards are split into owned (with image + DB id) and unowned
     * (just name + count). Also picks a main-card image to show in the header.
     *
     * @return array{
     *   deck: array<string,mixed>,
     *   owned: list<array<string,mixed>>,
     *   unowned: list<array{name:string,count:int}>,
     *   main_card_image: ?string,
     *   main_card_card_id: ?int,
     *   total_listed: int,
     *   total_owned: int,
     * }|null
     */
    public function getDeckWithCards(int $userId, int $deckId): ?array
    {
        $deck = $this->findById($userId, $deckId);
        if (!$deck) return null;

        // Build the canonical decklist: prefer the structured `cards` field,
        // fall back to legacy `key_cards` (one of each) for older decks.
        $entries = [];
        if (!empty($deck['cards'])) {
            foreach ($deck['cards'] as $entry) {
                if (!is_array($entry)) continue;
                $name = trim((string)($entry['name'] ?? ''));
                if ($name === '') continue;
                $entries[] = ['name' => $name, 'count' => max(1, (int)($entry['count'] ?? 1))];
            }
        } else {
            foreach ($deck['key_cards'] as $name) {
                $name = trim((string)$name);
                if ($name === '') continue;
                $entries[] = ['name' => $name, 'count' => 1];
            }
        }

        $keyNamesLower = array_map('strtolower', array_map('trim', $deck['key_cards']));

        $owned = [];
        $unowned = [];
        foreach ($entries as $entry) {
            $row = $this->findOwnedCardByName($userId, $entry['name']);
            if ($row) {
                $row['suggested_name'] = $entry['name'];
                $row['count'] = $entry['count'];
                $row['is_anchor'] = in_array(strtolower($entry['name']), $keyNamesLower, true);
                $owned[] = $row;
            } else {
                $unowned[] = $entry;
            }
        }

        // Main-card image: try named main_card first, then any owned card.
        $mainCardImage = null;
        $mainCardCardId = null;
        if (!empty($deck['main_card'])) {
            $main = $this->findOwnedCardByName($userId, (string)$deck['main_card']);
            if ($main) {
                $mainCardImage = $main['image_uri_normal'] ?: $main['image_uri_small'];
                $mainCardCardId = (int)$main['id'];
            }
        }
        if (!$mainCardImage) {
            foreach ($owned as $k) {
                $img = $k['image_uri_normal'] ?: $k['image_uri_small'];
                if ($img) { $mainCardImage = $img; $mainCardCardId = (int)$k['id']; break; }
            }
        }

        return [
            'deck' => $deck,
            'owned' => $owned,
            'unowned' => $unowned,
            'main_card_image' => $mainCardImage,
            'main_card_card_id' => $mainCardCardId,
            'total_listed' => array_sum(array_column($entries, 'count')),
            'total_owned' => array_sum(array_column($owned, 'count')),
        ];
    }

    public function delete(int $userId, int $deckId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM magic_decks WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $deckId, 'uid' => $userId]);
        return $stmt->rowCount();
    }

    public function toggleFavorite(int $userId, int $deckId): bool
    {
        $this->pdo->prepare('UPDATE magic_decks SET is_favorite = 1 - is_favorite WHERE id = :id AND user_id = :uid')
            ->execute(['id' => $deckId, 'uid' => $userId]);
        $stmt = $this->pdo->prepare('SELECT is_favorite FROM magic_decks WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $deckId, 'uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    /** @param array<string,mixed> $deck */
    public function insertSuggested(int $userId, array $deck): int
    {
        $colors = strtoupper(preg_replace('/[^WUBRG]/i', '', (string)($deck['colors'] ?? '')));

        $cardsList = [];
        foreach ((array)($deck['cards'] ?? []) as $entry) {
            if (!is_array($entry)) continue;
            $name = trim((string)($entry['name'] ?? ''));
            $count = max(1, (int)($entry['count'] ?? 1));
            if ($name !== '') $cardsList[] = ['name' => $name, 'count' => $count];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO magic_decks
                (user_id, name, colors, format, archetype, card_count, main_card,
                 strategy, strengths, weaknesses, key_cards, missing_cards, mana_curve, cards, source)
            VALUES (:uid, :name, :colors, :format, :archetype, :card_count, :main_card,
                    :strategy, :strengths, :weaknesses, :key_cards, :missing_cards, :mana_curve, :cards, :source)
        ');
        $stmt->execute([
            'uid' => $userId,
            'name' => substr((string)($deck['name'] ?? 'Untitled deck'), 0, 255),
            'colors' => substr($colors, 0, 5),
            'format' => substr((string)($deck['format'] ?? ''), 0, 32) ?: null,
            'archetype' => substr((string)($deck['archetype'] ?? ''), 0, 64) ?: null,
            'card_count' => (int)($deck['card_count'] ?? 60),
            'main_card' => substr((string)($deck['main_card'] ?? ''), 0, 255) ?: null,
            'strategy' => (string)($deck['strategy'] ?? '') ?: null,
            'strengths' => (string)($deck['strengths'] ?? '') ?: null,
            'weaknesses' => (string)($deck['weaknesses'] ?? '') ?: null,
            'key_cards' => json_encode(array_values(array_filter((array)($deck['key_cards'] ?? []), 'is_string'))),
            'missing_cards' => json_encode(array_values(array_filter((array)($deck['missing_cards'] ?? []), 'is_string'))),
            'mana_curve' => json_encode(array_values(array_map('intval', (array)($deck['mana_curve'] ?? [])))),
            'cards' => json_encode($cardsList),
            'source' => 'ai_suggested',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Look up a card in the user's collection by name. Tries exact match (case
     * insensitive) and double-faced variants ("X // Y" matches both "X" and "Y").
     * Returns the full row (including image URLs and IDs) or null.
     */
    public function findOwnedCardByName(int $userId, string $name): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, scryfall_id, name, image_uri_normal, image_uri_small,
                   mana_cost, type_line, rarity
            FROM magic_cards
            WHERE user_id = :uid
              AND (
                  LOWER(name) = LOWER(:n)
                  OR LOWER(name) LIKE LOWER(CONCAT(:n2, " //%"))
                  OR LOWER(name) LIKE LOWER(CONCAT("% // ", :n3))
              )
            ORDER BY image_uri_normal IS NULL, id
            LIMIT 1
        ');
        $stmt->execute(['uid' => $userId, 'n' => $name, 'n2' => $name, 'n3' => $name]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Walks an ordered list of card-name candidates and returns the first
     * image URL it can resolve from the user's collection.
     * @param iterable<string|null> $candidates
     */
    public function findFirstCardImage(int $userId, iterable $candidates): ?string
    {
        $stmt = $this->pdo->prepare('
            SELECT COALESCE(image_uri_normal, image_uri_small) AS img
            FROM magic_cards
            WHERE user_id = :uid
              AND (image_uri_normal IS NOT NULL OR image_uri_small IS NOT NULL)
              AND (
                  LOWER(name) = LOWER(:n)
                  OR LOWER(name) LIKE LOWER(CONCAT(:n2, " //%"))
                  OR LOWER(name) LIKE LOWER(CONCAT("% // ", :n3))
              )
            LIMIT 1
        ');
        foreach ($candidates as $name) {
            $name = trim((string)$name);
            if ($name === '') continue;
            $stmt->execute(['uid' => $userId, 'n' => $name, 'n2' => $name, 'n3' => $name]);
            $img = $stmt->fetchColumn();
            if ($img) return (string)$img;
        }
        return null;
    }

    /** @param array<string,mixed> $deck mutated in place */
    private function hydrate(array &$deck): void
    {
        $deck['key_cards'] = $deck['key_cards'] ? (json_decode($deck['key_cards'], true) ?: []) : [];
        $deck['missing_cards'] = $deck['missing_cards'] ? (json_decode($deck['missing_cards'], true) ?: []) : [];
        $deck['mana_curve'] = $deck['mana_curve'] ? (json_decode($deck['mana_curve'], true) ?: null) : null;
        $deck['cards'] = $deck['cards'] ? (json_decode($deck['cards'], true) ?: []) : [];
        $deck['is_favorite'] = (bool)$deck['is_favorite'];
    }
}

<?php

namespace Magic\Service;

final class ArtistService
{
    public function __construct(private \PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT ma.id, ma.name, ma.country, ma.birth_year, ma.lang,
                   COUNT(mc.id) AS card_count, SUM(mc.quantity) AS total_cards,
                   (SELECT mc2.image_uri_normal FROM magic_cards mc2
                    WHERE mc2.artist_id = ma.id AND mc2.user_id = :uid2 AND mc2.image_uri_normal IS NOT NULL
                    LIMIT 1) AS sample_image
            FROM magic_artists ma
            JOIN magic_cards mc ON mc.artist_id = ma.id AND mc.user_id = :uid
            GROUP BY ma.id, ma.name, ma.country, ma.birth_year, ma.lang
            ORDER BY ma.name ASC
        ');
        $stmt->execute(['uid' => $userId, 'uid2' => $userId]);
        $artists = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $urlStmt = $this->pdo->prepare('SELECT * FROM magic_artist_urls WHERE artist_id = :aid');
        foreach ($artists as &$a) {
            $urlStmt->execute(['aid' => $a['id']]);
            $a['urls'] = $urlStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $artists;
    }

    public function findOrCreate(string $name): ?int
    {
        if ($name === '') return null;
        $this->pdo->prepare('INSERT IGNORE INTO magic_artists (name) VALUES (:name)')->execute(['name' => $name]);
        $stmt = $this->pdo->prepare('SELECT id FROM magic_artists WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    /** @return array{id:int} */
    public function addUrl(int $artistId, string $url, ?string $label = null): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO magic_artist_urls (artist_id, url, label) VALUES (:aid, :url, :label)'
        );
        $stmt->execute(['aid' => $artistId, 'url' => $url, 'label' => $label]);
        return ['id' => (int)$this->pdo->lastInsertId()];
    }

    public function deleteUrl(int $urlId): void
    {
        $this->pdo->prepare('DELETE FROM magic_artist_urls WHERE id = :id')->execute(['id' => $urlId]);
    }

    public function countOrphans(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM magic_artists ma
             WHERE NOT EXISTS (SELECT 1 FROM magic_cards mc WHERE mc.artist_id = ma.id AND mc.user_id = :uid)'
        );
        $stmt->execute(['uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function deleteOrphans(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM magic_artists
             WHERE NOT EXISTS (SELECT 1 FROM magic_cards mc WHERE mc.artist_id = magic_artists.id AND mc.user_id = :uid)'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Returns the full artist row, their saved URLs, and all of the user's cards
     * by this artist (grouped by scryfall_id, with copy counts and rolled-up price).
     * Returns null if the artist doesn't exist.
     *
     * @return array{
     *   artist: array<string,mixed>,
     *   urls: list<array<string,mixed>>,
     *   grouped_cards: list<array<string,mixed>>,
     *   total_cards: int,
     *   total_value: float,
     * }|null
     */
    public function findById(int $artistId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM magic_artists WHERE id = :id');
        $stmt->execute(['id' => $artistId]);
        $artist = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$artist) return null;

        $cardsStmt = $this->pdo->prepare(
            'SELECT * FROM magic_cards WHERE artist_id = :aid AND user_id = :uid ORDER BY name ASC'
        );
        $cardsStmt->execute(['aid' => $artistId, 'uid' => $userId]);
        $cards = $cardsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $urlStmt = $this->pdo->prepare('SELECT * FROM magic_artist_urls WHERE artist_id = :aid');
        $urlStmt->execute(['aid' => $artistId]);
        $urls = $urlStmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalCards = 0;
        $totalValue = 0.0;
        $grouped = [];
        foreach ($cards as $c) {
            $qty = (int)($c['quantity'] ?? 1);
            $totalCards += $qty;
            $totalValue += ((float)($c['market_price'] ?? 0)) * $qty;
            $key = $c['scryfall_id'];
            if (!isset($grouped[$key])) {
                $c['count'] = 1;
                $c['total_price'] = (float)($c['market_price'] ?? 0);
                $grouped[$key] = $c;
            } else {
                $grouped[$key]['count']++;
                $grouped[$key]['total_price'] += (float)($c['market_price'] ?? 0);
            }
        }

        return [
            'artist' => $artist,
            'urls' => $urls,
            'grouped_cards' => array_values($grouped),
            'total_cards' => $totalCards,
            'total_value' => $totalValue,
        ];
    }

    /**
     * @return list<array<string,mixed>>  rows of {id, name} for artists missing
     *   bio data — used by the one-shot mtg.wiki enrichment script.
     */
    public function listMissingBio(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name FROM magic_artists
             WHERE country IS NULL AND birth_year IS NULL AND bio IS NULL
             ORDER BY name'
        );
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    /** @param array{country:?string, birth_year:?int, bio:?string} $data */
    public function updateBio(int $artistId, array $data): void
    {
        $this->pdo->prepare(
            'UPDATE magic_artists SET country = :country, birth_year = :birth_year, bio = :bio WHERE id = :id'
        )->execute([
            'country' => $data['country'] ?? null,
            'birth_year' => $data['birth_year'] ?? null,
            'bio' => $data['bio'] ?? null,
            'id' => $artistId,
        ]);
    }
}

<?php
require_once __DIR__ . '/../../../src/Magic/Bootstrap.php';

use Magic\Bootstrap;
use Magic\Http\Csrf;
use Magic\Service\DeckService;

$boot = Bootstrap::init();
$user = $boot->requireUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /decks/');
    exit;
}

$resolved = (new DeckService($boot->pdo()))->getDeckWithCards((int)$user['id'], $id);
if (!$resolved) {
    header('Location: /decks/');
    exit;
}
$deck = $resolved['deck'];
$ownedDeck = $resolved['owned'];
$unownedDeck = $resolved['unowned'];
$mainCardImage = $resolved['main_card_image'];
$mainCardCardId = $resolved['main_card_card_id'];
$totalListed = $resolved['total_listed'];
$totalOwned = $resolved['total_owned'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($deck['name']) ?> - Magic Decks</title>
    <link rel="icon" href="/img/favicon.png">
    <link rel="stylesheet" href="/cards/assets/deck.css">
</head>
<body>
    <div class="container">
        <a href="/decks/" class="back-link">&larr; My Decks</a>

        <div class="deck-header">
            <?php if ($mainCardImage): ?>
            <div class="deck-header-img">
                <?php if ($mainCardCardId): ?>
                    <a href="/cards/<?= $mainCardCardId ?>"><img src="/img/cache?url=<?= urlencode($mainCardImage) ?>" alt="<?= htmlspecialchars($deck['main_card']) ?>"></a>
                <?php else: ?>
                    <img src="/img/cache?url=<?= urlencode($mainCardImage) ?>" alt="<?= htmlspecialchars($deck['main_card']) ?>">
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="deck-header-info">
                <h1>
                    <?php
                    $colors = strtoupper($deck['colors'] ?? '');
                    if ($colors === '') {
                        echo '<span class="mana C" title="Colorless">C</span>';
                    } else {
                        foreach (str_split($colors) as $c) {
                            echo '<span class="mana ' . htmlspecialchars($c) . '" title="' . $c . '">' . $c . '</span>';
                        }
                    }
                    ?>
                    <span><?= htmlspecialchars($deck['name']) ?></span>
                </h1>
                <div class="deck-meta">
                    <?php if ($deck['format']): ?><span><b><?= htmlspecialchars($deck['format']) ?></b></span><?php endif; ?>
                    <?php if ($deck['archetype']): ?><span><?= htmlspecialchars($deck['archetype']) ?></span><?php endif; ?>
                    <?php if ($deck['card_count']): ?><span><?= (int)$deck['card_count'] ?> cards</span><?php endif; ?>
                    <?php if ($deck['main_card']): ?><span>Main: <b><?= htmlspecialchars($deck['main_card']) ?></b></span><?php endif; ?>
                </div>
                <?php if ($deck['strategy']): ?>
                    <p style="font-size:0.95rem;line-height:1.55;color:var(--text);"><?= nl2br(htmlspecialchars($deck['strategy'])) ?></p>
                <?php endif; ?>
                <div class="deck-actions">
                    <button class="fav <?= $deck['is_favorite'] ? 'on' : '' ?>" data-act="fav"><?= $deck['is_favorite'] ? '★ Favorite' : '☆ Favorite' ?></button>
                    <button class="del" data-act="del">Delete deck</button>
                </div>
            </div>
        </div>

        <div class="deck-body">
            <?php if ($deck['strengths']): ?>
            <section><h2>Strengths</h2><p><?= nl2br(htmlspecialchars($deck['strengths'])) ?></p></section>
            <?php endif; ?>
            <?php if ($deck['weaknesses']): ?>
            <section><h2>Weaknesses</h2><p><?= nl2br(htmlspecialchars($deck['weaknesses'])) ?></p></section>
            <?php endif; ?>

            <?php if (!empty($ownedDeck) || !empty($unownedDeck)): ?>
            <section>
                <h2>Decklist (<?= $totalOwned ?> / <?= $totalListed ?> in collection)</h2>
                <div class="card-grid">
                    <?php foreach ($ownedDeck as $c): ?>
                        <?php $img = $c['image_uri_normal'] ?: $c['image_uri_small']; ?>
                        <a class="card-tile<?= $c['is_anchor'] ? ' anchor' : '' ?>" href="/cards/<?= (int)$c['id'] ?>" title="<?= htmlspecialchars($c['name']) ?>">
                            <div class="card-tile-wrap">
                                <?php if ($img): ?>
                                    <img src="/img/cache?url=<?= urlencode($img) ?>" alt="<?= htmlspecialchars($c['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="placeholder"><?= htmlspecialchars($c['name']) ?></div>
                                <?php endif; ?>
                                <?php if ((int)$c['count'] > 1): ?>
                                    <span class="qty-badge"><?= (int)$c['count'] ?>x</span>
                                <?php endif; ?>
                            </div>
                            <div class="body">
                                <div class="name"><?= htmlspecialchars($c['name']) ?></div>
                                <div class="meta">
                                    <?= htmlspecialchars($c['type_line'] ?: '') ?>
                                    <?php if ($c['mana_cost']): ?> &middot; <?= htmlspecialchars($c['mana_cost']) ?><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php foreach ($unownedDeck as $entry): ?>
                        <div class="card-tile not-owned" title="Not in your collection">
                            <div class="card-tile-wrap">
                                <div class="placeholder"><?= htmlspecialchars($entry['name']) ?></div>
                                <?php if ($entry['count'] > 1): ?>
                                    <span class="qty-badge"><?= (int)$entry['count'] ?>x</span>
                                <?php endif; ?>
                            </div>
                            <div class="body">
                                <div class="name"><?= htmlspecialchars($entry['name']) ?></div>
                                <div class="meta" style="color:var(--red);">Not in collection</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($deck['missing_cards'])): ?>
            <section>
                <h2>Cards that would improve the deck</h2>
                <div class="missing-list">
                    <?php foreach ($deck['missing_cards'] as $name): ?>
                        <span><?= htmlspecialchars($name) ?></span>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (is_array($deck['mana_curve']) && count($deck['mana_curve']) > 0): ?>
            <section>
                <h2>Mana curve</h2>
                <?php $max = max(max($deck['mana_curve']), 1); $labels = ['0','1','2','3','4','5','6+']; ?>
                <div class="deck-curve">
                    <?php foreach ($deck['mana_curve'] as $i => $n): ?>
                        <div class="bar" style="height:<?= round(($n / $max) * 100) ?>%;">
                            <b><?= (int)$n ?></b>
                            <span><?= $labels[$i] ?? $i ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>

    <script>
        window.MAGIC_DECK = {
            id: <?= (int)$deck['id'] ?>,
            csrf: <?= json_encode(Csrf::token()) ?>,
        };
    </script>
    <script src="/cards/assets/deck.js"></script>
</body>
</html>

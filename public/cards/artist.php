<?php
require_once __DIR__ . '/../../src/Magic/Bootstrap.php';

use Magic\Bootstrap;
use Magic\Http\Csrf;
use Magic\Service\ArtistService;

$boot = Bootstrap::init();
$user = $boot->requireUser();

$artistId = (int)($_GET['id'] ?? 0);
if (!$artistId) { header('Location: /artists/'); exit; }

$resolved = (new ArtistService($boot->pdo()))->findById($artistId, (int)$user['id']);
if (!$resolved) { header('Location: /artists/'); exit; }
$artist = $resolved['artist'];
$urls = $resolved['urls'];
$grouped = $resolved['grouped_cards'];
$totalCards = $resolved['total_cards'];
$totalValue = $resolved['total_value'];

$countryNames = ['US'=>'United States','GB'=>'United Kingdom','DE'=>'Germany','FR'=>'France','IT'=>'Italy','ES'=>'Spain','PL'=>'Poland','NL'=>'Netherlands','BE'=>'Belgium','AT'=>'Austria','SE'=>'Sweden','HR'=>'Croatia','HU'=>'Hungary','BG'=>'Bulgaria','RU'=>'Russia','GR'=>'Greece','JP'=>'Japan','CN'=>'China','KR'=>'South Korea','PH'=>'Philippines','ID'=>'Indonesia','AU'=>'Australia','NZ'=>'New Zealand','CA'=>'Canada','BR'=>'Brazil','MX'=>'Mexico','CU'=>'Cuba','JO'=>'Jordan','VN'=>'Vietnam'];
$langNames = ['en'=>'English','de'=>'German','fr'=>'French','es'=>'Spanish','it'=>'Italian','pl'=>'Polish','nl'=>'Dutch','hr'=>'Croatian','hu'=>'Hungarian','bg'=>'Bulgarian','ru'=>'Russian','el'=>'Greek','ja'=>'Japanese','zh'=>'Chinese','ko'=>'Korean','id'=>'Indonesian','pt'=>'Portuguese','ar'=>'Arabic','vi'=>'Vietnamese','sv'=>'Swedish'];
$details = [];
if (!empty($artist['country'])) $details[] = 'Origin: ' . ($countryNames[$artist['country']] ?? $artist['country']);
if (!empty($artist['lang'])) $details[] = 'Language: ' . ($langNames[$artist['lang']] ?? $artist['lang']);
if (!empty($artist['bio'])) $details[] = $artist['bio'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($artist['name']) ?> - Magic: The Gathering</title>
    <link rel="icon" href="/img/favicon.png">
    <link rel="stylesheet" href="/cards/assets/artist.css">
</head>
<body>
    <div class="container">
        <a href="/artists/" class="back-link">&larr; Artists</a>
        <h1><?= htmlspecialchars($artist['name']) ?></h1>
        <div class="artist-meta">
            <?= count($grouped) ?> card<?= count($grouped) != 1 ? 's' : '' ?> &middot; <?= number_format($totalValue, 2, ',', '.') ?> &euro;
            <?php if (!empty($artist['birth_year'])) echo ' &middot; b. ' . $artist['birth_year']; ?>
        </div>
        <?php if ($details): ?>
            <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.75rem;line-height:1.5;white-space:pre-line;"><?= htmlspecialchars(implode("\n", $details)) ?></div>
        <?php endif; ?>

        <div class="artist-url" id="artist-url-area">
            <?php foreach ($urls as $u): ?>
                <div style="margin-bottom:0.3rem;">
                    <a href="<?= htmlspecialchars($u['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($u['label'] ?: $u['url']) ?></a>
                    <span class="no-url" onclick="deleteUrl(<?= $u['id'] ?>)" title="Remove">&times;</span>
                </div>
            <?php endforeach; ?>
            <span class="no-url" onclick="showUrlEdit()">+ Add link</span>
        </div>

        <div class="card-grid">
            <?php foreach ($grouped as $card): ?>
            <div class="card-item">
                <a href="/cards/<?= $card['id'] ?>">
                    <?php if ($card['image_uri_normal']): ?>
                        <img src="/img/cache?url=<?= urlencode($card['image_uri_normal']) ?>" alt="<?= htmlspecialchars($card['name']) ?>" loading="lazy">
                    <?php endif; ?>
                </a>
                <div class="card-item-info">
                    <div class="card-item-name" title="<?= htmlspecialchars($card['name']) ?>"><?= htmlspecialchars($card['name']) ?><?= $card['count'] > 1 ? ' <span style="color:var(--text-muted);">(' . $card['count'] . 'x)</span>' : '' ?></div>
                    <?php if ($card['total_price'] > 0): ?>
                        <div class="card-item-price"><?= number_format($card['total_price'], 2, ',', '.') ?> &euro;</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        window.MAGIC_ARTIST = {
            id: <?= $artistId ?>,
            name: <?= json_encode($artist['name']) ?>,
            csrf: <?= json_encode(Csrf::token()) ?>,
        };
    </script>
    <script src="/cards/assets/artist.js"></script>
</body>
</html>

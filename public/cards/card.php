<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Magic\Bootstrap;
use Magic\Http\Csrf;
use Magic\Service\ArtistService;
use Magic\Service\CardService;

$boot = Bootstrap::init();
$user = $boot->requireUser();

$id = (string)($_GET['id'] ?? '');
if ($id === '') {
    header('Location: /cards/');
    exit;
}

$cards = new CardService($boot->pdo(), new ArtistService($boot->pdo()));
$resolved = $cards->findByIdOrScryfall((int)$user['id'], $id);
if (!$resolved) {
    header('Location: /cards/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Details - Magic: The Gathering</title>
    <link rel="icon" type="image/svg+xml" href="/img/favicon.svg"><link rel="icon" type="image/png" href="/img/favicon.png">
    <link rel="stylesheet" href="/cards/assets/card.css">
</head>
<body>
    <div class="container">
        <a href="/cards/" class="back-link">&larr; My Cards</a>
        <div id="content" class="loading">Loading card data...</div>
    </div>

    <script>
        window.MAGIC_CARD = {
            scryfallId: <?= json_encode($resolved['scryfall_id']) ?>,
            owned: <?= $resolved['current'] ? json_encode($resolved['current']) : 'null' ?>,
            allCopies: <?= json_encode($resolved['copies']) ?>,
            csrf: <?= json_encode(Csrf::token()) ?>,
        };
    </script>
    <script src="/cards/assets/card.js"></script>
</body>
</html>

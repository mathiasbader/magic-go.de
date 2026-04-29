<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Magic\Bootstrap;
use Magic\Http\Csrf;
use Magic\Service\DeckService;
use Magic\View;

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

View::display('deck.html.twig', [
    'deck' => $resolved['deck'],
    'owned' => $resolved['owned'],
    'unowned' => $resolved['unowned'],
    'main_card_image' => $resolved['main_card_image'],
    'main_card_card_id' => $resolved['main_card_card_id'],
    'total_listed' => $resolved['total_listed'],
    'total_owned' => $resolved['total_owned'],
    'csrf' => Csrf::token(),
]);

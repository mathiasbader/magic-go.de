<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Magic\Bootstrap;
use Magic\Controller\CardsApiController;
use Magic\Http\Csrf;
use Magic\Service\UserSettingsService;
use Magic\View;

$boot = Bootstrap::init();

// JSON API endpoints — dispatched and exited before any HTML is emitted.
if (CardsApiController::isApiRequest()) {
    CardsApiController::dispatch($boot);
}

$user = $boot->requireUser();
$pdo = $boot->pdo();

$initialTab = $_GET['tab'] ?? 'cards';
$hasClaudeApiKey = (new UserSettingsService($pdo))->has((int)$user['id'], 'claude_api_key');

// Sets data — pre-rendered server-side because the per-set markup is large
// and the data is fully known at request time.
$allSets = require __DIR__ . '/../sets_data.php';

View::display('cards.html.twig', [
    'csrf' => Csrf::token(),
    'initial_tab' => $initialTab,
    'has_claude_key' => $hasClaudeApiKey,
    'all_sets' => $allSets,
    'sets_latest_year' => (string)max(array_keys($allSets)),
    'today' => date('Y-m-d'),
]);

<?php

namespace Magic\Controller;

use Magic\Bootstrap;
use Magic\Http\Csrf;
use Magic\Http\Json;
use Magic\Service\ArtistService;
use Magic\Service\BatchService;
use Magic\Service\BinderService;
use Magic\Service\CardService;
use Magic\Service\ClaudeApiException;
use Magic\Service\ClaudeClient;
use Magic\Service\DeckService;
use Magic\Service\DeckSuggester;
use Magic\Service\UserSettingsService;

/**
 * Single dispatcher for the JSON POST API at /cards/.
 *
 * Each action is a small method that delegates to a service; controller-level
 * concerns (auth, CSRF, shaping the JSON response, error envelope) live here.
 */
final class CardsApiController
{
    /** Actions exempted from CSRF — read-only / safe-to-replay queries. */
    private const SAFE_ACTIONS = [
        'list', 'list_artists', 'list_batches', 'count_unassigned',
        'check_existing', 'list_binders', 'list_binder_cards', 'list_decks',
        'list_recent_run_seconds',
    ];

    public function __construct(
        private \PDO $pdo,
        private array $user,
    ) {}

    public static function isApiRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    /**
     * Routes the current request and exits. Call once at the top of index.php
     * if isApiRequest() returns true.
     */
    public static function dispatch(Bootstrap $boot): never
    {
        $user = $boot->user();
        if (!$user) Json::error('Unauthorized', 401);

        $input = Json::readBody();
        $action = (string)($input['action'] ?? '');

        if (!in_array($action, self::SAFE_ACTIONS, true)) {
            if (!Csrf::verify((string)($input['_csrf'] ?? ''))) {
                Json::error('Invalid CSRF token', 403);
            }
        }

        $controller = new self($boot->pdo(), $user);
        try {
            $controller->handle($action, $input);
        } catch (ClaudeApiException $e) {
            Json::error($e->getMessage(), 502, ['detail' => $e->detail, 'status' => $e->upstreamStatus]);
        } catch (\Throwable $e) {
            Json::error('Server error', 500, [
                'detail' => $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
        Json::error('Unknown action', 400);
    }

    /** @param array<string,mixed> $input */
    private function handle(string $action, array $input): void
    {
        $uid = (int)$this->user['id'];

        switch ($action) {
            // Cards
            case 'list':
                Json::send($this->cards()->listForUser($uid));
            case 'add':
                $this->cards()->add($uid, $input);
                Json::send(['ok' => true]);
            case 'update':
                $this->cards()->update($uid, $input);
                Json::send(['ok' => true]);
            case 'delete':
                $this->cards()->delete($uid, (int)($input['id'] ?? 0));
                Json::send(['ok' => true]);
            case 'delete_all':
                $this->cards()->deleteAll($uid);
                Json::send(['ok' => true]);
            case 'check_existing':
                Json::send($this->cards()->checkExisting($uid, (array)($input['cards'] ?? [])));
            case 'update_card_language':
                $this->cards()->updateLanguage($uid, $input);
                Json::send(['ok' => true]);
            case 'update_price':
                $this->cards()->updatePrice($uid, $input);
                Json::send(['ok' => true]);

            // Batches
            case 'create_batch':
                Json::send(['ok' => true] + $this->batches()->create(
                    $uid,
                    $input['filename'] ?? null,
                    $input['format'] ?? null,
                    (int)($input['card_count'] ?? 0),
                ));
            case 'update_batch_count':
                $this->batches()->updateCount(
                    $uid, (int)$input['batch_id'], (int)$input['card_count'], $input['sets'] ?? null,
                );
                Json::send(['ok' => true]);
            case 'list_batches':
                Json::send($this->batches()->listAll($uid));
            case 'delete_batch':
                $this->batches()->delete($uid, (int)$input['batch_id']);
                Json::send(['ok' => true]);
            case 'delete_unassigned':
                $this->batches()->deleteUnassigned($uid);
                Json::send(['ok' => true]);
            case 'count_unassigned':
                Json::send(['cnt' => $this->batches()->countUnassigned($uid)]);

            // Binders
            case 'list_binders':
                Json::send($this->binders()->listAll($uid));
            case 'move_binder':
                $this->binders()->move($uid, (string)$input['from'], $input['to'] ?? null, $input['batch_id'] ?? null);
                Json::send(['ok' => true]);
            case 'list_binder_cards':
                Json::send($this->binders()->listCards($uid, (string)($input['binder'] ?? '')));

            // Artists
            case 'list_artists':
                Json::send($this->artists()->listForUser($uid));
            case 'add_artist_url':
                Json::send(['ok' => true] + $this->artists()->addUrl(
                    (int)$input['artist_id'], (string)$input['url'], $input['label'] ?? null,
                ));
            case 'delete_artist_url':
                $this->artists()->deleteUrl((int)$input['id']);
                Json::send(['ok' => true]);
            case 'count_orphan_artists':
                Json::send(['cnt' => $this->artists()->countOrphans($uid)]);
            case 'delete_orphan_artists':
                Json::send(['ok' => true, 'deleted' => $this->artists()->deleteOrphans($uid)]);

            // Settings
            case 'save_setting':
                $this->saveSetting($uid, $input);
                Json::send(['ok' => true]);

            // Decks
            case 'list_decks':
                Json::send($this->decks()->listForUser($uid));
            case 'delete_deck':
                Json::send(['ok' => true, 'deleted' => $this->decks()->delete($uid, (int)($input['id'] ?? 0))]);
            case 'toggle_deck_favorite':
                Json::send(['ok' => true, 'is_favorite' => $this->decks()->toggleFavorite($uid, (int)($input['id'] ?? 0))]);
            case 'suggest_decks':
                $this->suggestDecks($uid);
            case 'list_recent_run_seconds':
                Json::send(['seconds' => $this->decks()->recentRunSeconds($uid)]);

            default:
                Json::error('Unknown action');
        }
    }

    /** @param array<string,mixed> $input */
    private function saveSetting(int $uid, array $input): void
    {
        $key = trim((string)($input['key'] ?? ''));
        $value = trim((string)($input['value'] ?? ''));
        if ($key === '' || $value === '') Json::error('Missing key or value');
        $this->settings()->save($uid, $key, $value);
    }

    private function suggestDecks(int $uid): never
    {
        @set_time_limit(0);
        $apiKey = $this->settings()->get($uid, 'claude_api_key');
        if (!$apiKey) Json::error('Claude API key not set');

        try {
            $suggester = new DeckSuggester($this->cards(), $this->decks(), new ClaudeClient($apiKey));
            $result = $suggester->suggest($uid);
        } catch (ClaudeApiException $e) {
            Json::error($e->getMessage(), 502, ['detail' => $e->detail, 'status' => $e->upstreamStatus]);
        } catch (\RuntimeException $e) {
            Json::error($e->getMessage(), 400);
        }
        Json::send(['ok' => true] + $result);
    }

    // Lazy-built service singletons — keeps construction cheap when only one
    // action is hit per request.
    private ?CardService $cardSvc = null;
    private ?BatchService $batchSvc = null;
    private ?BinderService $binderSvc = null;
    private ?ArtistService $artistSvc = null;
    private ?DeckService $deckSvc = null;
    private ?UserSettingsService $settingSvc = null;

    private function artists(): ArtistService { return $this->artistSvc ??= new ArtistService($this->pdo); }
    private function cards(): CardService { return $this->cardSvc ??= new CardService($this->pdo, $this->artists()); }
    private function batches(): BatchService { return $this->batchSvc ??= new BatchService($this->pdo); }
    private function binders(): BinderService { return $this->binderSvc ??= new BinderService($this->pdo); }
    private function decks(): DeckService { return $this->deckSvc ??= new DeckService($this->pdo); }
    private function settings(): UserSettingsService { return $this->settingSvc ??= new UserSettingsService($this->pdo); }
}

<?php
require_once __DIR__ . '/../../../src/Magic/Bootstrap.php';

use Magic\Bootstrap;
use Magic\Controller\CardsApiController;
use Magic\Http\Csrf;
use Magic\Service\UserSettingsService;

$boot = Bootstrap::init();

// JSON API endpoints — dispatched and exited before any HTML is emitted.
if (CardsApiController::isApiRequest()) {
    CardsApiController::dispatch($boot);
}

$user = $boot->requireUser();
$pdo = $boot->pdo();

$csrfToken = Csrf::token();
$initialTab = $_GET['tab'] ?? 'cards';
$hasClaudeApiKey = (new UserSettingsService($pdo))->has((int)$user['id'], 'claude_api_key');

// Sets data — pre-rendered server-side because the per-set markup is large
// and the data is fully known at request time.
require_once __DIR__ . '/../sets_data.php';
$setsYears = array_keys($allSets);
$setsLatestYear = max($setsYears);
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cards - Magic: The Gathering</title>
    <link rel="icon" href="/img/favicon.png">
    <link rel="stylesheet" href="/cards/assets/cards.css">
</head>
<body>
    <div class="container">
        <div class="page-tabs">
            <button class="page-tab<?= $initialTab === 'cards' ? ' active' : '' ?>" data-tab="cards">My Cards</button>
            <button class="page-tab<?= $initialTab === 'decks' ? ' active' : '' ?>" data-tab="decks">My Decks</button>
            <button class="page-tab<?= $initialTab === 'artists' ? ' active' : '' ?>" data-tab="artists">Artists</button>
            <button class="page-tab<?= $initialTab === 'sets' ? ' active' : '' ?>" data-tab="sets">Sets</button>
        </div>

        <div class="tab-panel<?= $initialTab === 'cards' ? ' active' : '' ?>" id="tab-cards">
            <div class="search-section">
                <div class="search-bar">
                    <input type="text" id="search-input" placeholder="Search cards on Scryfall..." autocomplete="off">
                    <a href="/cards/imports" class="import-btn" style="text-decoration:none;text-align:center;">Imports</a>
                </div>
                <div class="search-results" id="search-results"></div>
            </div>

            <div class="collection-bar">
                <input type="text" id="filter-input" placeholder="Filter collection...">
                <select id="binder-select" style="display:none;"><option value="">All binders</option></select>
                <span class="collection-stats" id="collection-stats"></span>
            </div>
            <div class="set-filters">
                <select id="set-filter-select" style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:0.4rem 0.6rem;font-size:0.85rem;color:var(--text);outline:none;font-family:inherit;">
                    <option value="">All sets</option>
                </select>
                <span id="lang-filters" style="display:contents;"></span>
                <span id="rarity-filters" style="display:contents;"></span>
                <span id="foil-filters" style="display:contents;"></span>
            </div>

            <div class="sort-bar">
                <span class="sort-label">Sort:</span>
                <button class="sort-btn" data-sort="name">Name <span class="sort-arrow">&uarr;</span></button>
                <button class="sort-btn active" data-sort="price">Price <span class="sort-arrow">&darr;</span></button>
                <button class="sort-btn" data-sort="set">Set <span class="sort-arrow">&uarr;</span></button>
                <button class="sort-btn" data-sort="copies">Copies <span class="sort-arrow">&darr;</span></button>
            </div>

            <div id="card-grid" class="card-grid"></div>
            <div id="empty-state" class="empty-state" style="display:none;">
                <p>No cards in your collection yet.</p>
                <p>Search for cards above or import from a file.</p>
                <a href="/cards/imports" style="display:inline-block;margin-top:0.75rem;background:var(--accent);color:var(--bg);border-radius:8px;padding:0.5rem 1.2rem;font-weight:600;text-decoration:none;font-size:0.9rem;">Import cards from file</a>
            </div>
        </div>

        <div class="tab-panel<?= $initialTab === 'decks' ? ' active' : '' ?>" id="tab-decks">
            <div id="decks-api-key-form" style="<?= $hasClaudeApiKey ? 'display:none;' : '' ?>max-width:480px;margin:2rem auto;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1.5rem;">
                <h2 style="font-size:1rem;margin:0 0 0.5rem;color:var(--text);">Claude API key required</h2>
                <p style="font-size:0.85rem;color:var(--text-muted);margin:0 0 1rem;line-height:1.5;">This module needs a Claude API key to generate decks. Paste yours below — it's stored only against your account.</p>
                <form id="claude-key-form" style="display:flex;flex-direction:column;gap:0.75rem;">
                    <input type="password" id="claude-key-input" placeholder="sk-ant-..." autocomplete="off" required style="background:var(--surface-alt);border:1px solid var(--border);border-radius:6px;padding:0.5rem 0.7rem;font-size:0.85rem;color:var(--text);outline:none;font-family:inherit;">
                    <div style="display:flex;gap:0.75rem;align-items:center;">
                        <button type="submit" style="background:var(--accent);color:var(--bg);border:none;border-radius:6px;padding:0.5rem 1rem;font-size:0.85rem;font-weight:600;cursor:pointer;font-family:inherit;">Save key</button>
                        <a href="#" id="claude-key-cancel" style="display:<?= $hasClaudeApiKey ? 'inline' : 'none' ?>;color:var(--text-muted);font-size:0.8rem;text-decoration:none;border-bottom:1px dotted var(--border);">Cancel</a>
                    </div>
                    <span id="claude-key-error" style="display:none;color:var(--red);font-size:0.8rem;"></span>
                </form>
            </div>
            <div id="decks-main" style="<?= $hasClaudeApiKey ? '' : 'display:none;' ?>max-width:1100px;margin:1.5rem auto;">
                <div id="decks-toolbar" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;">
                    <button id="suggest-decks-btn" style="background:var(--accent);color:var(--bg);border:none;border-radius:8px;padding:0.55rem 1.1rem;font-size:0.9rem;font-weight:600;cursor:pointer;font-family:inherit;">Suggest more decks</button>
                    <span id="suggest-decks-status" style="color:var(--text-muted);font-size:0.8rem;"></span>
                    <a href="#" id="claude-key-update" style="margin-left:auto;color:var(--text-muted);font-size:0.75rem;text-decoration:none;border-bottom:1px dotted var(--border);">Update API key</a>
                </div>
                <div id="decks-loading" style="display:none;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem;padding:4rem 1rem;text-align:center;">
                    <div class="planeswalker-spinner"></div>
                    <div id="decks-loading-msg" style="font-size:1.1rem;color:var(--text);font-weight:500;min-height:1.6rem;"></div>
                    <div style="width:min(420px, 80%);">
                        <div class="progress-track"><div class="progress-fill" id="decks-progress-fill"></div></div>
                        <div id="decks-progress-label" style="font-size:0.75rem;color:var(--text-muted);margin-top:0.4rem;"></div>
                        <button id="decks-cancel-btn" type="button" style="margin-top:0.75rem;background:transparent;color:var(--text-muted);border:1px solid var(--border);border-radius:6px;padding:0.35rem 0.9rem;font-size:0.75rem;cursor:pointer;font-family:inherit;">Cancel</button>
                    </div>
                </div>
                <div id="decks-list" class="decks-list"></div>
                <div id="decks-empty-msg" style="display:none;text-align:center;color:var(--text-muted);margin:3rem auto;font-size:0.9rem;">No decks yet. Click <b>Suggest more decks</b> to generate some from your collection.</div>
            </div>
        </div>

        <div class="tab-panel<?= $initialTab === 'artists' ? ' active' : '' ?>" id="tab-artists">
            <div class="artist-wrapper">
                <div style="margin-bottom:0;display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="artist-filter-input" placeholder="Filter artists..." style="flex:1;min-width:150px;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:0.4rem 0.6rem;font-size:0.85rem;color:var(--text);outline:none;font-family:inherit;">
                    <select id="artist-country-filter" style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:0.4rem 0.5rem;font-size:0.85rem;color:var(--text);outline:none;font-family:inherit;"><option value="">All countries</option></select>
                    <select id="artist-lang-filter" style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:0.4rem 0.5rem;font-size:0.85rem;color:var(--text);outline:none;font-family:inherit;"><option value="">All languages</option></select>
                    <span class="sort-label">Sort:</span>
                    <button class="sort-btn active" data-artist-sort="name">Name <span class="sort-arrow">&uarr;</span></button>
                    <button class="sort-btn" data-artist-sort="designs">Designs <span class="sort-arrow">&darr;</span></button>
                </div>
                <div id="artist-list" class="artist-list"></div>
            </div>
            <div id="artist-orphans" style="display:none;text-align:center;margin-top:1.5rem;font-size:0.8rem;color:var(--text-muted);"></div>
            <div style="text-align:center;margin-top:0.75rem;"><a href="/cards/enrich_artists.php" style="color:var(--text-muted);font-size:0.75rem;text-decoration:none;border-bottom:1px dotted var(--border);">Enrich artists from mtg.wiki</a></div>
        </div>

        <div class="tab-panel<?= $initialTab === 'sets' ? ' active' : '' ?>" id="tab-sets">
            <div class="sets-container">
                <div class="year-tabs">
                    <?php foreach ($setsYears as $year): ?>
                        <button class="year-tab" data-year="<?= $year ?>"><?= $year ?></button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($allSets as $year => $sets): ?>
                <div class="year-panel" data-year="<?= $year ?>">
                    <div class="sets-legend">
                        <button class="sets-filter-btn" data-filter="original" type="button"><span class="dot dot-original"></span> Magic Original</button>
                        <button class="sets-filter-btn" data-filter="ub" type="button"><span class="dot dot-ub"></span> Beyond</button>
                        <button class="sets-filter-btn" data-filter="released" type="button"><span class="dot dot-released"></span> Released</button>
                        <button class="sets-filter-btn" data-filter="upcoming" type="button"><span class="dot dot-upcoming"></span> Upcoming</button>
                    </div>

                    <?php foreach ($sets as $idx => $set):
                        $released = $set['date'] <= $today;
                        $isUB = $set['universe'] === 'Universes Beyond';
                        $dateFormatted = date('M j, Y', strtotime($set['date']));
                        $scryfallIcon = '/img/cache?url=' . urlencode('https://svgs.scryfall.io/sets/' . strtolower($set['code']) . '.svg');
                    ?>
                    <div class="set-card<?= !$released ? ' set-card-upcoming' : '' ?>" data-universe="<?= $isUB ? 'ub' : 'original' ?>" data-status="<?= $released ? 'released' : 'upcoming' ?>">
                        <a href="<?= htmlspecialchars($set['wotc_url']) ?>" target="_blank" rel="noopener" class="set-icon">
                            <img src="<?= $scryfallIcon ?>" alt="<?= htmlspecialchars($set['code']) ?>"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="set-fallback-icon" style="display:none;">&#127183;</div>
                        </a>
                        <div class="set-body">
                            <span class="set-number"><?= $year ?>-<?= $idx + 1 ?></span>
                            <div class="set-header">
                                <a href="<?= htmlspecialchars($set['wotc_url']) ?>" target="_blank" rel="noopener" class="set-name"><?= htmlspecialchars($set['name']) ?></a>
                                <span class="set-code"><?= htmlspecialchars($set['code']) ?></span>
                                <span class="badge <?= $isUB ? 'badge-ub' : 'badge-original' ?>"><?= $isUB ? 'Beyond' : 'Original' ?></span>
                                <?php if (!$released): ?><span class="badge badge-upcoming">Upcoming</span><?php endif; ?>
                            </div>
                            <div class="set-meta">
                                <span>&#128197; <?= $dateFormatted ?></span>
                                <?php if ($set['cards']): ?><span>&#127183; <?= $set['cards'] ?> cards</span><?php endif; ?>
                                <?php if ($set['commander_code']): ?><span>Commander: <?= htmlspecialchars($set['commander_code']) ?></span><?php endif; ?>
                            </div>
                            <div class="set-desc"><?= htmlspecialchars($set['description']) ?></div>
                            <div class="set-formats">
                                <?php foreach ($set['formats'] as $f): ?><span class="format-tag"><?= htmlspecialchars($f) ?></span><?php endforeach; ?>
                            </div>
                            <div class="set-links">
                                <a href="<?= htmlspecialchars($set['scryfall_url']) ?>" target="_blank" rel="noopener">Scryfall</a>
                                <a href="<?= htmlspecialchars($set['wiki_url']) ?>" target="_blank" rel="noopener">MTG Wiki</a>
                                <a href="<?= htmlspecialchars($set['goldfish_url']) ?>" target="_blank" rel="noopener">MTG Goldfish</a>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars($set['wotc_url']) ?>" target="_blank" rel="noopener" class="set-preview">
                            <img src="/img/<?= strtolower($set['code']) ?>.jpg"
                                 alt="<?= htmlspecialchars($set['name']) ?> key art" loading="lazy"
                                 <?php if (!empty($set['image_pos'])): ?>style="object-position: <?= $set['image_pos'] ?>"<?php endif; ?>>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <div class="sets-note">All Beyond sets are Standard-legal starting 2025. Set icons from Scryfall.</div>
            </div>
        </div>
    </div>
    <div class="toast" id="toast"></div>

    <script>
        window.MAGIC = {
            csrf: <?= json_encode($csrfToken) ?>,
            hasClaudeKey: <?= $hasClaudeApiKey ? 'true' : 'false' ?>,
            initialTab: <?= json_encode($initialTab) ?>,
            setsLatestYear: <?= json_encode((string)$setsLatestYear) ?>,
        };
    </script>
    <script src="/cards/assets/core.js"></script>
    <script src="/cards/assets/cards-tab.js"></script>
    <script src="/cards/assets/artists-tab.js"></script>
    <script src="/cards/assets/sets-tab.js"></script>
    <script src="/cards/assets/decks-tab.js"></script>
</body>
</html>

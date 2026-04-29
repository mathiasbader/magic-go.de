<?php
/**
 * One-time script to enrich artist data from mtg.wiki.
 * Run via browser: /cards/enrich_artists.php
 * Safe to re-run — only updates artists with empty fields.
 */
require_once __DIR__ . '/../../src/Magic/Bootstrap.php';

use Magic\Bootstrap;
use Magic\Service\ArtistService;
use Magic\Service\WikiArtistEnricher;

set_time_limit(600);

$boot = Bootstrap::init();
$boot->requireUser();

header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
while (ob_get_level()) ob_end_flush();
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);

$artistSvc = new ArtistService($boot->pdo());
$artists = $artistSvc->listMissingBio();
$total = count($artists);
?><!DOCTYPE html>
<html><head><title>Enrich Artists</title>
<style>
    body { background:#0f172a; color:#e2e8f0; font-family:'Segoe UI',system-ui,sans-serif; margin:0; padding:1.5rem; }
    .progress-wrap { background:#1e293b; border-radius:8px; height:24px; margin-bottom:1rem; overflow:hidden; position:sticky; top:0; z-index:10; }
    .progress-fill { background:#38bdf8; height:100%; width:0%; transition:width 0.3s; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:600; color:#0f172a; min-width:2rem; }
    .stats { font-size:0.85rem; color:#94a3b8; margin-bottom:1rem; }
    .stats span { color:#e2e8f0; font-weight:600; }
    .log { font-family:monospace; font-size:0.8rem; line-height:1.6; }
    .found { color:#4ade80; }
    .miss { color:#475569; }
    .err { color:#f87171; }
</style>
</head><body>
<h2 style="margin:0 0 1rem;font-size:1.2rem;">Artist Enrichment from mtg.wiki</h2>
<div class="progress-wrap"><div class="progress-fill" id="bar">0%</div></div>
<div class="stats">
    Total: <span><?= $total ?></span> |
    Enriched: <span id="s-found">0</span> |
    No data: <span id="s-miss">0</span> |
    Errors: <span id="s-err">0</span> |
    Remaining: <span id="s-rem"><?= $total ?></span>
</div>
<div class="log" id="log">
<?php
echo str_repeat(' ', 4096); // force initial flush
flush();

$enricher = new WikiArtistEnricher();
$found = 0;
$notFound = 0;
$errors = 0;
$debugCount = 0;

foreach ($artists as $i => $artist) {
    $num = $i + 1;
    $pct = round($num / $total * 100);
    $name = htmlspecialchars($artist['name']);

    $result = $enricher->fetch($artist['name']);
    $class = 'miss';
    $msg = '';
    $sleepUs = 200000;

    switch ($result['status']) {
        case 'enriched':
            $artistSvc->updateBio((int)$artist['id'], $result['data']);
            $found++;
            $class = 'found';
            $parts = [];
            if (!empty($result['data']['country'])) $parts[] = $result['data']['country'];
            if (!empty($result['data']['birth_year'])) $parts[] = 'b. ' . $result['data']['birth_year'];
            if (!empty($result['data']['bio'])) $parts[] = '+bio';
            $msg = "{$name}: " . implode(', ', $parts);
            break;
        case 'request_failed':
            $notFound++;
            $msg = "{$name}: <span class='err'>request failed</span>";
            if ($debugCount < 3) {
                $msg .= " (url: " . htmlspecialchars(substr($result['url'] ?? '', 0, 120)) . ")";
                $debugCount++;
            }
            $sleepUs = 500000;
            break;
        case 'no_page':
            $notFound++;
            $msg = "{$name}: -";
            break;
        case 'redirect':
            $notFound++;
            $msg = "{$name}: redirect (no infobox)";
            break;
        case 'no_infobox':
            $notFound++;
            $msg = "{$name}: page exists, no infobox";
            if ($debugCount < 3) {
                $msg .= " (first 100 chars: " . htmlspecialchars($result['snippet'] ?? '') . ")";
                $debugCount++;
            }
            break;
        case 'empty_infobox':
        default:
            $notFound++;
            $msg = "{$name}: infobox empty";
            break;
    }

    $rem = $total - $num;
    echo "<span class='{$class}'>[{$num}/{$total}] {$msg}</span>\n";
    echo "<script>document.getElementById('bar').style.width='{$pct}%';document.getElementById('bar').textContent='{$pct}%';document.getElementById('s-found').textContent='{$found}';document.getElementById('s-miss').textContent='{$notFound}';document.getElementById('s-err').textContent='{$errors}';document.getElementById('s-rem').textContent='{$rem}';</script>\n";
    flush();
    usleep($sleepUs);
}

echo "\n<b>Done! Enriched: {$found}, No data: {$notFound}, Errors: {$errors}</b>\n";
echo "<script>document.getElementById('bar').style.width='100%';document.getElementById('bar').textContent='Done!';</script>\n";
?>
</div>
<div style="margin-top:1.5rem;"><a href="/artists/" style="color:#38bdf8;text-decoration:none;">&larr; Back to Artists</a></div>
</body></html>

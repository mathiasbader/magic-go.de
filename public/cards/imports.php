<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Magic\Bootstrap;
use Magic\Http\Csrf;

$boot = Bootstrap::init();
$boot->requireUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imports - Magic: The Gathering</title>
    <link rel="icon" type="image/svg+xml" href="/img/favicon.svg"><link rel="icon" type="image/png" href="/img/favicon.png">
    <link rel="stylesheet" href="/cards/assets/imports.css">
</head>
<body>
    <div class="container">
        <a href="/cards/" class="back-link">&larr; My Cards</a>
        <h1>Imports</h1>

        <div class="drop-zone" id="drop-zone">
            <input type="file" id="csv-file" accept=".csv" style="display:none">
            <h2>Drop CSV file here or click to select</h2>
            <p>Supports ManaBox and Delver MTG exports</p>
        </div>
        <div id="import-message" style="display:none;margin-bottom:1.5rem;"></div>
        <div id="import-info" class="import-info-box" style="display:none;">
            <div id="import-title" style="font-size:1rem;font-weight:600;margin-bottom:0.4rem;"></div>
            <div class="import-status" id="import-status"></div>
            <div class="progress-bar" id="import-progress-wrap" style="display:none;">
                <div class="progress-bar-fill" id="import-progress-bar"></div>
            </div>
            <div id="import-done-top" style="display:none;margin-top:1rem;margin-bottom:0.5rem;text-align:center;">
                <a href="#" onclick="document.getElementById('import-info').style.display='none';return false;" style="color:var(--accent);text-decoration:none;font-size:1.1rem;font-weight:600;">Close</a>
            </div>
            <div id="import-preview-cards" style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;margin-top:0.75rem;"></div>
            <div id="import-done-bottom" style="display:none;margin-top:1.5rem;margin-bottom:0.5rem;text-align:center;">
                <a href="#" onclick="document.getElementById('import-info').style.display='none';return false;" style="color:var(--accent);text-decoration:none;font-size:1.1rem;font-weight:600;">Close</a>
            </div>
        </div>

        <div id="content"></div>

        <h2 style="font-size:1.1rem;margin-top:2rem;margin-bottom:1rem;">Binders</h2>
        <div id="binder-content"></div>
    </div>
    <div class="toast" id="toast"></div>

    <script>
        window.MAGIC_IMPORTS = {
            csrf: <?= json_encode(Csrf::token()) ?>,
        };
    </script>
    <script src="/cards/assets/imports.js"></script>
</body>
</html>

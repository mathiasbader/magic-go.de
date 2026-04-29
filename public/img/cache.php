<?php
/**
 * Image cache proxy for Scryfall card images.
 * Downloads once, serves from local disk thereafter.
 *
 * Usage: /img/cache.php?url=https://cards.scryfall.io/...
 *
 * Hardening: a corporate TLS proxy may intercept the HTTPS handshake (presenting
 * its own root CA from the system trust store) and rewrite the response into an
 * HTML challenge page. We use CURLSSLOPT_NATIVE_CA so the handshake succeeds
 * against the system store, then validate that what we got back is actually
 * an image — both via Content-Type and the file's magic bytes — before writing
 * it to disk. If either check fails we 302 to the original URL so the browser
 * (which has the corporate session cookie) can fetch it directly.
 */

$url = $_GET['url'] ?? '';
if (!$url) {
    http_response_code(400);
    exit;
}

// Only allow Scryfall domains
$parsed = parse_url($url);
$allowedHosts = ['cards.scryfall.io', 'svgs.scryfall.io'];
if (!$parsed || !in_array($parsed['host'] ?? '', $allowedHosts, true)) {
    http_response_code(403);
    exit;
}

$cacheDir = __DIR__ . '/card_cache';
$hash = md5($url);
$ext = pathinfo($parsed['path'] ?? '', PATHINFO_EXTENSION) ?: 'jpg';
$cachePath = $cacheDir . '/' . substr($hash, 0, 2) . '/' . $hash . '.' . $ext;

// Serve from cache if exists
if (file_exists($cachePath)) {
    serveFile($cachePath, $ext);
    exit;
}

// Download from Scryfall
$ch = curl_init($url);
$opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MagicCardCache/1.0)',
    CURLOPT_HTTPHEADER => ['Accept: image/*'],
];
// Use Windows / system trust store so corporate-MITM TLS handshakes succeed.
if (defined('CURLSSLOPT_NATIVE_CA')) {
    $opts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
}
curl_setopt_array($ch, $opts);
$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($httpCode !== 200 || !$data || !looksLikeImage($contentType, $data)) {
    // Either the request failed or something other than an image came back
    // (commonly a corporate web-filter HTML challenge page). Redirect to
    // the original URL so the browser can try directly with its own session.
    header('Location: ' . $url, true, 302);
    exit;
}

// Store in cache
$dir = dirname($cachePath);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
file_put_contents($cachePath, $data);

serveFile($cachePath, $ext);

function serveFile(string $path, string $ext): void
{
    $types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];
    $type = $types[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($path);
}

/**
 * True if the response looks like a real image: Content-Type starts with
 * image/* AND the body's magic bytes match a known image format.
 */
function looksLikeImage(string $contentType, string $data): bool
{
    if (stripos($contentType, 'image/') !== 0) return false;
    if (strlen($data) < 4) return false;
    $head = substr($data, 0, 8);
    return str_starts_with($head, "\xFF\xD8\xFF")           // JPEG
        || str_starts_with($head, "\x89PNG\r\n\x1A\n")      // PNG
        || str_starts_with($head, "GIF87a")                 // GIF87a
        || str_starts_with($head, "GIF89a")                 // GIF89a
        || str_starts_with($head, "RIFF")                   // WebP (RIFF...WEBP)
        || str_starts_with(ltrim($data), '<svg')            // SVG
        || str_starts_with(ltrim($data), '<?xml');          // SVG with XML decl
}

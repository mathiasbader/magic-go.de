<?php
/**
 * Server-side proxy for Scryfall API calls.
 * Avoids CORS issues when running locally with self-signed certificates.
 */

$path = $_GET['path'] ?? '';
if (!$path || !preg_match('#^/(cards|sets)(/|$)#', $path)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid path']);
    exit;
}

$url = 'https://api.scryfall.com' . $path;
$method = $_SERVER['REQUEST_METHOD'];

$ch = curl_init($url);
$headers = ['User-Agent: MagicCardProxy/1.0'];

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $headers[] = 'Content-Type: application/json';
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => $headers,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Scryfall request failed', 'detail' => $error]);
    exit;
}

header('Content-Type: ' . ($contentType ?: 'application/json'));
http_response_code($httpCode ?: 502);
echo $response;

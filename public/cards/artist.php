<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Magic\Bootstrap;
use Magic\Http\Csrf;
use Magic\Service\ArtistService;
use Magic\View;

$boot = Bootstrap::init();
$user = $boot->requireUser();

$artistId = (int)($_GET['id'] ?? 0);
if (!$artistId) { header('Location: /artists/'); exit; }

$resolved = (new ArtistService($boot->pdo()))->findById($artistId, (int)$user['id']);
if (!$resolved) { header('Location: /artists/'); exit; }

$artist = $resolved['artist'];

$countryNames = ['US'=>'United States','GB'=>'United Kingdom','DE'=>'Germany','FR'=>'France','IT'=>'Italy','ES'=>'Spain','PL'=>'Poland','NL'=>'Netherlands','BE'=>'Belgium','AT'=>'Austria','SE'=>'Sweden','HR'=>'Croatia','HU'=>'Hungary','BG'=>'Bulgaria','RU'=>'Russia','GR'=>'Greece','JP'=>'Japan','CN'=>'China','KR'=>'South Korea','PH'=>'Philippines','ID'=>'Indonesia','AU'=>'Australia','NZ'=>'New Zealand','CA'=>'Canada','BR'=>'Brazil','MX'=>'Mexico','CU'=>'Cuba','JO'=>'Jordan','VN'=>'Vietnam'];
$langNames = ['en'=>'English','de'=>'German','fr'=>'French','es'=>'Spanish','it'=>'Italian','pl'=>'Polish','nl'=>'Dutch','hr'=>'Croatian','hu'=>'Hungarian','bg'=>'Bulgarian','ru'=>'Russian','el'=>'Greek','ja'=>'Japanese','zh'=>'Chinese','ko'=>'Korean','id'=>'Indonesian','pt'=>'Portuguese','ar'=>'Arabic','vi'=>'Vietnamese','sv'=>'Swedish'];
$details = [];
if (!empty($artist['country'])) $details[] = 'Origin: ' . ($countryNames[$artist['country']] ?? $artist['country']);
if (!empty($artist['lang'])) $details[] = 'Language: ' . ($langNames[$artist['lang']] ?? $artist['lang']);
if (!empty($artist['bio'])) $details[] = $artist['bio'];

View::display('artist.html.twig', [
    'artist' => $artist,
    'urls' => $resolved['urls'],
    'grouped' => $resolved['grouped_cards'],
    'total_cards' => $resolved['total_cards'],
    'total_value' => $resolved['total_value'],
    'details' => $details,
    'csrf' => Csrf::token(),
]);

<?php

namespace Magic\Service;

/**
 * Pulls bio data (country, birth year, style/training) from mtg.wiki for a
 * single artist by name. Pure HTTP + parsing — persistence is the caller's job
 * (use ArtistService::updateBio).
 */
final class WikiArtistEnricher
{
    private const ENDPOINT = 'https://mtg.wiki/api.php';
    private const USER_AGENT = 'MathiasBaderMTGCollection/1.0';
    private const TIMEOUT_SECONDS = 10;

    /**
     * Looks up a single artist. Returns one of:
     *   ['status' => 'enriched', 'data' => ['country' => ..., 'birth_year' => ..., 'bio' => ...]]
     *   ['status' => 'no_infobox']
     *   ['status' => 'redirect']
     *   ['status' => 'no_page']
     *   ['status' => 'request_failed', 'url' => '...']
     *   ['status' => 'empty_infobox']
     *
     * @return array{status:string, data?:array, url?:string, snippet?:string}
     */
    public function fetch(string $artistName): array
    {
        $apiUrl = self::ENDPOINT . '?' . http_build_query([
            'action' => 'parse',
            'page' => str_replace(' ', '_', $artistName),
            'prop' => 'wikitext',
            'format' => 'json',
        ]);

        $ctx = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT_SECONDS,
                'header' => 'User-Agent: ' . self::USER_AGENT . "\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);

        $response = @file_get_contents($apiUrl, false, $ctx);
        if ($response === false) {
            return ['status' => 'request_failed', 'url' => $apiUrl];
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            return ['status' => 'no_page'];
        }

        $wikitext = $data['parse']['wikitext']['*'] ?? '';
        if ($wikitext === '' || strpos($wikitext, '{{Infobox') === false) {
            $isRedirect = stripos($wikitext, '#REDIRECT') !== false;
            return $isRedirect
                ? ['status' => 'redirect']
                : ['status' => 'no_infobox', 'snippet' => substr($wikitext, 0, 100)];
        }

        $parsed = self::parseInfobox($wikitext);
        if (!$parsed['country'] && !$parsed['birth_year'] && !$parsed['bio']) {
            return ['status' => 'empty_infobox'];
        }
        return ['status' => 'enriched', 'data' => $parsed];
    }

    /**
     * Pulls country, birth year, and a short style/training bio out of the
     * raw MediaWiki text of an artist page.
     *
     * @return array{country:?string, birth_year:?int, bio:?string}
     */
    private static function parseInfobox(string $wikitext): array
    {
        $country = null;
        $birthYear = null;

        if (preg_match('/\|\s*born\s*=\s*(.+)/i', $wikitext, $m)) {
            $bornClean = trim(strip_tags(
                preg_replace('/\[\[([^\]|]+\|)?([^\]]+)\]\]/', '$2', trim($m[1]))
            ));

            if (preg_match('/\b(19\d{2}|20\d{2})\b/', $bornClean, $ym)) {
                $birthYear = (int)$ym[1];
            }

            $locPart = trim(preg_replace('/\(?\d{4}\)?/', '', $bornClean), " ,.\t\n\r");
            if ($locPart !== '') {
                $parts = array_map('trim', explode(',', $locPart));
                $lastPart = end($parts);
                if ($lastPart !== false && strlen($lastPart) > 1) {
                    $country = $lastPart;
                }
            }
        }

        $bioParts = [];
        foreach (['style', 'training'] as $key) {
            if (preg_match('/\|\s*' . $key . '\s*=\s*(.+)/i', $wikitext, $m)) {
                $val = trim(strip_tags(
                    preg_replace('/\[\[([^\]|]+\|)?([^\]]+)\]\]/', '$2', trim($m[1]))
                ));
                if ($val !== '') $bioParts[] = ucfirst($key) . ': ' . $val;
            }
        }

        return [
            'country' => $country,
            'birth_year' => $birthYear,
            'bio' => $bioParts ? implode("\n", $bioParts) : null,
        ];
    }
}

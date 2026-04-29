<?php

namespace Magic\Service;

/**
 * Orchestrates the "suggest deck" feature end to end:
 *  1. Aggregates the user's collection into a compact card list
 *  2. Lists their existing AI-suggested decks so Claude can avoid repeats
 *  3. Calls Claude with a JSON-schema constrained prompt for ONE new deck
 *  4. Persists the returned deck via DeckService
 *
 * Throws ClaudeApiException on transport / API failures so the controller
 * can render a clean error.
 */
final class DeckSuggester
{
    private const MODEL = 'claude-opus-4-7';
    private const MAX_TOKENS = 16000;

    public function __construct(
        private CardService $cards,
        private DeckService $decks,
        private ClaudeClient $claude,
    ) {}

    /**
     * @return array{
     *   saved_id: ?int,
     *   unique_cards: int,
     *   total_cards: int,
     *   usage: array<string,mixed>|null,
     * }
     * @throws ClaudeApiException
     * @throws \RuntimeException when the user has no cards or Claude returns an unparseable shape
     */
    public function suggest(int $userId): array
    {
        $cards = $this->cards->aggregateForUser($userId);
        if (!$cards) {
            throw new \RuntimeException('No cards in collection');
        }

        $cardLines = array_map(static fn (array $c): string => sprintf(
            '%dx %s | %s | %s | %s',
            (int)$c['qty'],
            $c['name'],
            $c['mana_cost'] !== null && $c['mana_cost'] !== '' ? $c['mana_cost'] : '-',
            $c['type_line'] ?: '-',
            $c['rarity'] ?: '-',
        ), $cards);

        $existing = $this->decks->listForUser($userId);
        $existingSummary = $this->summarizeExisting($existing);

        $payload = [
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'thinking' => ['type' => 'adaptive'],
            'output_config' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => self::deckSchema(),
                ],
            ],
            'system' => [
                [
                    'type' => 'text',
                    'text' => self::systemPrompt(),
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => "Here is my Magic: The Gathering collection (format: `quantity x name | mana cost | type | rarity`):\n\n"
                    . implode("\n", $cardLines)
                    . "\n\n"
                    . $existingSummary
                    . "Suggest ONE new deck I could build from these cards. It should be meaningfully DIFFERENT from the decks I already have — pick a fresh combination of colors, a different archetype, or an unusual angle/twist on a familiar one. Surprise me with a build I haven't seen yet.",
            ]],
        ];

        $startedAt = microtime(true);
        $response = $this->claude->messages($payload);
        $text = ClaudeClient::extractText($response);
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['name'])) {
            throw new ClaudeApiException('Could not parse deck suggestion', 502, $text);
        }

        $elapsedSeconds = (int)round(microtime(true) - $startedAt);
        $savedId = $this->decks->insertSuggested($userId, $parsed, $elapsedSeconds);

        return [
            'saved_id' => $savedId,
            'unique_cards' => count($cards),
            'total_cards' => array_sum(array_column($cards, 'qty')),
            'usage' => $response['usage'] ?? null,
        ];
    }

    /**
     * Build a compact summary of decks the user already has, so Claude has
     * concrete things to avoid repeating. Empty string when no prior decks.
     *
     * @param list<array<string,mixed>> $existing
     */
    private function summarizeExisting(array $existing): string
    {
        if (!$existing) return '';
        $lines = [];
        foreach ($existing as $d) {
            $colors = (string)($d['colors'] ?? '');
            $colors = $colors !== '' ? $colors : 'Colorless';
            $bits = [
                $d['name'] ?? 'Untitled',
                '(' . $colors . ($d['archetype'] ? ', ' . $d['archetype'] : '') . ($d['format'] ? ', ' . $d['format'] : '') . ')',
            ];
            if (!empty($d['main_card'])) $bits[] = '- main: ' . $d['main_card'];
            $lines[] = '- ' . implode(' ', $bits);
        }
        return "I already have these decks (DO NOT repeat their flavor — pick a different combination of colors, archetype, or angle):\n"
            . implode("\n", $lines)
            . "\n\n";
    }

    private static function systemPrompt(): string
    {
        return "You are a Magic: The Gathering deck-building expert. The user will share their card collection and any decks they already have. Suggest exactly ONE new deck they could realistically build using cards from their collection — and it must be genuinely different from the decks they already have.\n\nPrioritise creative, unexpected angles: a different color identity, a different archetype, or a fresh twist on a familiar one (e.g. a Goblin tribal that wins through aristocrats sacrifice, not just go-wide aggro). If the existing decks already cover all the obvious angles, find something weirder — a janky combo, a tribal nobody asked for, a 5-color pile of bombs.\n\nReturn a single deck object with these fields:\n- name: short deck name (e.g. \"Mono-Red Aggro\", \"Esper Control\")\n- colors: subset of WUBRG as a single uppercase string (e.g. \"R\", \"WU\", \"BRG\"). Empty string for colorless.\n- format: one of Standard, Modern, Pioneer, Legacy, Commander, Casual, Pauper\n- archetype: one of Aggro, Midrange, Control, Combo, Tribal, Ramp, Tempo, Voltron, Stax\n- card_count: 60 for constructed, 100 for Commander\n- main_card: the single most important card (commander for EDH, key payoff otherwise) — must exist in the collection\n- strategy: 2-3 sentence explanation of how the deck wins, including what makes it DIFFERENT from the user's existing decks\n- strengths: 1-2 sentences on what the deck does well\n- weaknesses: 1-2 sentences on what beats it\n- key_cards: 6-12 card names from the collection that anchor the deck (exact names) — a HIGHLIGHTS subset of `cards`\n- missing_cards: 0-6 card names NOT in the collection that would meaningfully improve it\n- mana_curve: array of 7 integers — counts of cards at converted mana cost [0,1,2,3,4,5,6+] across the proposed deck\n- cards: the COMPLETE decklist as an array of objects {name, count}. The sum of counts MUST equal card_count. Use ONLY card names that exist in the user's collection (do NOT invent cards or use cards from missing_cards here). Include lands. For constructed (60), use up to 4 copies per non-land card (basic lands unlimited). For Commander (100), all non-basic cards are singletons. If the collection lacks enough cards to fill a sensible decklist, scale card_count down to what is actually possible and explain in `strategy`.\n\nBase the suggestion strictly on cards actually present in the collection.";
    }

    /** @return array<string,mixed> */
    private static function deckSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'colors' => ['type' => 'string'],
                'format' => ['type' => 'string'],
                'archetype' => ['type' => 'string'],
                'card_count' => ['type' => 'integer'],
                'main_card' => ['type' => 'string'],
                'strategy' => ['type' => 'string'],
                'strengths' => ['type' => 'string'],
                'weaknesses' => ['type' => 'string'],
                'key_cards' => ['type' => 'array', 'items' => ['type' => 'string']],
                'missing_cards' => ['type' => 'array', 'items' => ['type' => 'string']],
                'mana_curve' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'cards' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'count' => ['type' => 'integer'],
                        ],
                        'required' => ['name', 'count'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['name','colors','format','archetype','card_count','main_card','strategy','strengths','weaknesses','key_cards','missing_cards','mana_curve','cards'],
            'additionalProperties' => false,
        ];
    }
}

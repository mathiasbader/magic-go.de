<?php

namespace Magic\Service;

/**
 * Orchestrates the "suggest decks" feature end to end:
 *  1. Aggregates the user's collection into a compact card list
 *  2. Calls Claude with a JSON-schema constrained prompt
 *  3. Persists each returned deck via DeckService
 *
 * Throws ClaudeApiException on transport / API failures so the controller
 * can render a clean error.
 */
final class DeckSuggester
{
    private const MODEL = 'claude-opus-4-7';
    private const MAX_TOKENS = 32000;

    public function __construct(
        private CardService $cards,
        private DeckService $decks,
        private ClaudeClient $claude,
    ) {}

    /**
     * @return array{
     *   saved_ids: list<int>,
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
                    . "\n\nSuggest decks I could build from these cards.",
            ]],
        ];

        $response = $this->claude->messages($payload);
        $text = ClaudeClient::extractText($response);
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['decks']) || !is_array($parsed['decks'])) {
            throw new ClaudeApiException('Could not parse deck suggestions', 502, $text);
        }

        $savedIds = [];
        foreach ($parsed['decks'] as $deck) {
            if (is_array($deck)) {
                $savedIds[] = $this->decks->insertSuggested($userId, $deck);
            }
        }

        return [
            'saved_ids' => $savedIds,
            'unique_cards' => count($cards),
            'total_cards' => array_sum(array_column($cards, 'qty')),
            'usage' => $response['usage'] ?? null,
        ];
    }

    private static function systemPrompt(): string
    {
        return "You are a Magic: The Gathering deck-building expert. The user will share their card collection. Suggest 3 to 5 deck ideas they could realistically build using cards from their collection.\n\nFor each deck, return:\n- name: short deck name (e.g. \"Mono-Red Aggro\", \"Esper Control\")\n- colors: subset of WUBRG as a single uppercase string (e.g. \"R\", \"WU\", \"BRG\"). Empty string for colorless.\n- format: one of Standard, Modern, Pioneer, Legacy, Commander, Casual, Pauper\n- archetype: one of Aggro, Midrange, Control, Combo, Tribal, Ramp, Tempo, Voltron, Stax\n- card_count: 60 for constructed, 100 for Commander\n- main_card: the single most important card (commander for EDH, key payoff otherwise) — must exist in the collection\n- strategy: 2-3 sentence explanation of how the deck wins\n- strengths: 1-2 sentences on what the deck does well\n- weaknesses: 1-2 sentences on what beats it\n- key_cards: 6-12 card names from the collection that anchor the deck (exact names) — a HIGHLIGHTS subset of `cards`\n- missing_cards: 0-6 card names NOT in the collection that would meaningfully improve it\n- mana_curve: array of 7 integers — counts of cards at converted mana cost [0,1,2,3,4,5,6+] across the proposed deck\n- cards: the COMPLETE decklist as an array of objects {name, count}. The sum of counts MUST equal card_count. Use ONLY card names that exist in the user's collection (do NOT invent cards or use cards from missing_cards here). Include lands. For constructed (60), use up to 4 copies per non-land card (basic lands unlimited). For Commander (100), all non-basic cards are singletons. If the collection lacks enough cards to fill a sensible decklist, scale card_count down to what is actually possible and explain in `strategy`.\n\nBase every suggestion strictly on cards actually present in the collection.";
    }

    /** @return array<string,mixed> */
    private static function deckSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'decks' => [
                    'type' => 'array',
                    'items' => [
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
                    ],
                ],
            ],
            'required' => ['decks'],
            'additionalProperties' => false,
        ];
    }
}

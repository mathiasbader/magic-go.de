<?php

namespace Magic\Service;

/**
 * Minimal HTTP client for the Anthropic Messages API.
 *
 * Doesn't pretend to be a full SDK — just enough to call /v1/messages with a
 * structured-output schema. Uses cURL because the host has no Composer setup.
 *
 * Throws ClaudeApiException on transport / API-level failure so the controller
 * can render a consistent JSON error.
 */
final class ClaudeClient
{
    public function __construct(
        private string $apiKey,
        private int $timeoutSeconds = 600,
        private string $endpoint = 'https://api.anthropic.com/v1/messages',
    ) {}

    /** @param array<string,mixed> $payload  @return array<string,mixed> */
    public function messages(array $payload): array
    {
        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new ClaudeApiException('Claude API request failed', 502, $err);
        }
        $data = json_decode((string)$body, true);
        if ($httpCode >= 400) {
            $message = is_array($data) ? ($data['error']['message'] ?? (string)$body) : (string)$body;
            throw new ClaudeApiException('Claude API error', 502, $message, $httpCode);
        }
        if (!is_array($data)) {
            throw new ClaudeApiException('Invalid Claude response', 502, 'Non-JSON body');
        }
        return $data;
    }

    /**
     * Concatenates the text content blocks from a /v1/messages response.
     * @param array<string,mixed> $response
     */
    public static function extractText(array $response): string
    {
        $text = '';
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        return $text;
    }
}


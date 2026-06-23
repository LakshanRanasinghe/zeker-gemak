<?php

namespace App\Services;

use App\Concerns\NormalizesTrixHtml;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Translates FAQ content (pages and items) between locales with the
 * Anthropic API. Caller-agnostic: it takes a flat field map and echoes
 * the same keys back with translated values.
 */
class FaqTranslator
{
    use NormalizesTrixHtml;

    /** Generous ceiling — a FAQ page (intro + several sections) can be long. */
    protected const MAX_TOKENS = 8192;

    protected const LANGUAGES = [
        'en' => 'English',
        'nl' => 'Dutch',
    ];

    /**
     * Translate a flat map of FAQ field values from one locale to another
     * in a single API call. Keys are arbitrary and echoed back unchanged;
     * only the values are translated.
     *
     * @param  array<string, string>  $fields  field key => source-locale value
     * @param  list<string>  $htmlFields  keys whose values are Trix HTML (tags preserved)
     * @return array<string, string> field key => target-locale value
     *
     * @throws \RuntimeException
     */
    public function translate(array $fields, string $sourceLocale, string $targetLocale, array $htmlFields = []): array
    {
        // Drop empty values — nothing to translate, and it keeps the prompt lean.
        $fields = array_filter($fields, fn ($value) => is_string($value) && trim($value) !== '');

        if (empty($fields)) {
            return [];
        }

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-sonnet-4-6'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => $this->buildSystemPrompt($sourceLocale, $targetLocale, $htmlFields),
            'messages' => [
                ['role' => 'user', 'content' => $this->buildUserPrompt($fields)],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Anthropic translation request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('AI translation request failed.');
        }

        return $this->parseResponse((string) $response->json('content.0.text', ''), array_keys($fields), $htmlFields);
    }

    protected function buildSystemPrompt(string $sourceLocale, string $targetLocale, array $htmlFields): string
    {
        $from = self::LANGUAGES[$sourceLocale] ?? strtoupper($sourceLocale);
        $to = self::LANGUAGES[$targetLocale] ?? strtoupper($targetLocale);

        $lines = [
            'You are a professional translator for an e-commerce label & printing business.',
            "Translate the FAQ field values below from {$from} to {$to}.",
            '',
            'Rules:',
            "- Translate naturally and fluently into {$to} — never leave text in {$from}.",
            '- Keep the meaning, tone, and technical accuracy. Do not translate brand or product names.',
            '- Preserve every JSON key exactly as given. Translate only the values.',
        ];

        if (! empty($htmlFields)) {
            $lines[] = '- These fields contain HTML: '.implode(', ', $htmlFields).'.';
            $lines[] = '  Keep every HTML tag and attribute exactly as-is — translate only the human-readable text between tags.';
            $lines[] = '  Allowed tags: <div>, <strong>, <em>, <del>, <ul>, <ol>, <li>, <blockquote>, <a>, <br>. Do not add other tags or markdown.';
        }

        $lines[] = '- All other fields are plain text — return plain text with no HTML.';
        $lines[] = '- Return a single flat JSON object and nothing else: no preamble, no explanation, no markdown code fences.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $fields
     */
    protected function buildUserPrompt(array $fields): string
    {
        return "Translate the values in this JSON object:\n\n"
            .json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parse the flat JSON response, keeping only the requested keys.
     *
     * @param  list<string>  $requestedKeys
     * @param  list<string>  $htmlFields
     * @return array<string, string>
     */
    protected function parseResponse(string $raw, array $requestedKeys, array $htmlFields): array
    {
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/i', '', (string) $raw);

        $data = json_decode((string) $raw, true);

        if (! is_array($data)) {
            Log::error('FaqTranslator: failed to parse AI response', ['raw' => $raw]);
            throw new \RuntimeException('Failed to parse AI translation response.');
        }

        $out = [];

        foreach ($requestedKeys as $key) {
            if (! array_key_exists($key, $data) || ! is_string($data[$key])) {
                continue;
            }

            $value = in_array($key, $htmlFields, true)
                ? $this->normalizeTrixHtml($data[$key])
                : trim(strip_tags($data[$key]));

            // Skip empties — never let a malformed response wipe target content.
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}

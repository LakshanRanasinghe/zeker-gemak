<?php

namespace App\Services;

use App\Concerns\NormalizesTrixHtml;
use App\Models\AiSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductContentGenerator
{
    use NormalizesTrixHtml;

    public const TRIX_FIELDS = ['short_description', 'content', 'product_information'];

    public const PLAIN_FIELDS = ['title', 'subtitle', 'excerpt', 'meta_title', 'meta_description'];

    public const ALL_AI_FIELDS = [
        'title',
        'subtitle',
        'excerpt',
        'short_description',
        'content',
        'product_information',
        'meta_title',
        'meta_description',
    ];

    /**
     * Generate the requested fields for a single locale in a single API call.
     *
     * @param  list<string>  $fields  field keys requested for this locale (excluding 'slug')
     * @param  string  $locale  e.g. 'en' or 'nl'
     * @param  array  $context  product context: title, brand, category, short_description, attributes (key => value)
     * @param  string  $scope  AiSetting::SCOPE_PRODUCT or AiSetting::SCOPE_GROUP_PRODUCT
     * @return array<string,string> map of field key => generated value
     *
     * @throws \RuntimeException
     */
    public function generateFields(array $fields, string $locale, array $context, string $scope = AiSetting::SCOPE_PRODUCT): array
    {
        $fields = array_values(array_intersect($fields, self::ALL_AI_FIELDS));

        if (empty($fields)) {
            return [];
        }

        $settings = new AiSetting(AiSetting::defaults($scope));
        $systemPrompt = $this->buildSystemPrompt($settings, $locale, $scope);
        $userPrompt = $this->buildUserPrompt($fields, $context);

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-sonnet-4-6'),
            'max_tokens' => (int) config('services.anthropic.max_tokens', 2048),
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error('Anthropic API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('AI content generation request failed.');
        }

        $body = $response->json();
        $raw = $body['content'][0]['text'] ?? '';

        return $this->parseFlatResponse($raw, $fields);
    }

    public function buildSystemPrompt(AiSetting $settings, string $locale, string $scope = AiSetting::SCOPE_PRODUCT): string
    {
        return $this->loadBasePrompt($scope)."\n\n".$this->buildRuntimeInstructions($settings, $locale);
    }

    public function buildRuntimeInstructions(AiSetting $settings, string $locale): string
    {
        $config = $settings->settingsForLocale($locale);

        $role = trim((string) ($config['role_description'] ?? '')) ?: 'You are an expert product content writer.';
        $tone = AiSetting::TONES[$config['tone'] ?? 'professional'] ?? 'Professional';
        $style = trim((string) ($config['style_notes'] ?? ''));
        $taxonomy = trim((string) ($config['taxonomy_guidelines'] ?? ''));

        $names = ['en' => 'English', 'nl' => 'Dutch'];
        $langName = $names[$locale] ?? strtoupper($locale);

        $runtime = [];
        $runtime[] = '--- RUNTIME INSTRUCTIONS ---';
        $runtime[] = '';
        $runtime[] = 'Output language: '.$langName.' ('.$locale.')';
        $runtime[] = 'Write every generated field value entirely in '.$langName.'. The base prompt above is in English for instruction purposes only — do not output English content.';
        if ($locale !== 'en') {
            $runtime[] = 'If an EN title is provided as reference, translate from it exactly — same meaning, '.$langName.' language only.';
        }
        $runtime[] = '';
        $runtime[] = 'Role: '.$role;
        $runtime[] = 'Tone: '.$tone;
        if ($style !== '') {
            $runtime[] = 'Style notes: '.$style;
        }
        if ($taxonomy !== '') {
            $runtime[] = 'Taxonomies: '.$taxonomy;
        }
        $runtime[] = '';
        $runtime[] = 'Word count targets:';
        $runtime[] = '- excerpt: '.(int) ($config['wc_excerpt'] ?? 40).' words';
        $runtime[] = '- short_description: '.(int) ($config['wc_short_description'] ?? 75).' words';
        $runtime[] = '- content: '.(int) ($config['wc_content'] ?? 300).' words';
        $runtime[] = '- product_information: '.(int) ($config['wc_product_information'] ?? 150).' words';
        $runtime[] = '- meta_title: maximum 60 characters';
        $runtime[] = '- meta_description: maximum 160 characters';
        $runtime[] = '';
        $runtime[] = 'Output format rules:';
        $runtime[] = '- Trix HTML fields (short_description, content, product_information): return Trix-compatible HTML only.';
        $runtime[] = '  Use only these tags: <div>, <strong>, <em>, <del>, <ul>, <li>, <ol>, <blockquote>, <a>, <br>.';
        $runtime[] = '  Never use <h1>-<h6>, <p>, <table>, <span>, <style>, <script>, or markdown syntax (no ##, no **text**, no - bullets, no backticks).';
        $runtime[] = '- Plain text fields (title, subtitle, excerpt, meta_title, meta_description): return plain text only. No HTML tags.';
        $runtime[] = '- Return a single flat JSON object. Keys must exactly match the requested field names.';
        $runtime[] = '  No preamble, no explanation, no markdown code fences — only the raw JSON object.';

        return implode("\n", $runtime);
    }

    public function getBasePrompt(string $scope = AiSetting::SCOPE_PRODUCT): string
    {
        return $this->loadBasePrompt($scope);
    }

    public function getBasePromptForPreview(string $locale, string $scope = AiSetting::SCOPE_PRODUCT): string
    {
        $filename = $this->basePromptFilename($scope);

        if ($locale !== 'en') {
            $path = resource_path("prompts/{$filename}.{$locale}.md");
            if (File::exists($path)) {
                return File::get($path);
            }
        }

        return $this->loadBasePrompt($scope);
    }

    public function buildRuntimeInstructionsForPreview(AiSetting $settings, string $locale, string $scope = AiSetting::SCOPE_PRODUCT): string
    {
        if ($locale === 'nl') {
            return $this->buildRuntimeInstructionsNl($settings);
        }

        return $this->buildRuntimeInstructions($settings, $locale);
    }

    protected function buildRuntimeInstructionsNl(AiSetting $settings): string
    {
        $config = $settings->settingsForLocale('nl');

        $role = trim((string) ($config['role_description'] ?? '')) ?: 'U bent een expert productinhoudschrijver.';
        $tone = AiSetting::TONES[$config['tone'] ?? 'professional'] ?? 'Professional';
        $style = trim((string) ($config['style_notes'] ?? ''));
        $taxonomy = trim((string) ($config['taxonomy_guidelines'] ?? ''));

        $toneNl = [
            'Professional' => 'Professioneel',
            'Friendly' => 'Vriendelijk',
            'Technical' => 'Technisch',
            'Persuasive' => 'Overtuigend',
            'Neutral' => 'Neutraal',
        ][$tone] ?? $tone;

        $runtime = [];
        $runtime[] = '--- RUNTIME INSTRUCTIES ---';
        $runtime[] = '';
        $runtime[] = 'Uitvoertaal: Nederlands (nl)';
        $runtime[] = 'Schrijf elke gegenereerde veldwaarde volledig in het Nederlands. De bovenstaande basisprompt is voor instructiedoeleinden — voer geen Engelse inhoud uit.';
        $runtime[] = 'Als een EN-titel als referentie is opgegeven, vertaal hier exact van — zelfde betekenis, alleen in het Nederlands.';
        $runtime[] = '';
        $runtime[] = 'Rol: '.$role;
        $runtime[] = 'Toon: '.$toneNl;
        if ($style !== '') {
            $runtime[] = 'Stijlnotities: '.$style;
        }
        if ($taxonomy !== '') {
            $runtime[] = 'Taxonomieën: '.$taxonomy;
        }
        $runtime[] = '';
        $runtime[] = 'Woordteldoelstellingen:';
        $runtime[] = '- excerpt: '.(int) ($config['wc_excerpt'] ?? 40).' woorden';
        $runtime[] = '- short_description: '.(int) ($config['wc_short_description'] ?? 75).' woorden';
        $runtime[] = '- content: '.(int) ($config['wc_content'] ?? 300).' woorden';
        $runtime[] = '- product_information: '.(int) ($config['wc_product_information'] ?? 150).' woorden';
        $runtime[] = '- meta_title: maximaal 60 tekens';
        $runtime[] = '- meta_description: maximaal 160 tekens';
        $runtime[] = '';
        $runtime[] = 'Uitvoerformaatregels:';
        $runtime[] = '- Trix HTML-velden (short_description, content, product_information): geef alleen Trix-compatibele HTML terug.';
        $runtime[] = '  Gebruik alleen deze tags: <div>, <strong>, <em>, <del>, <ul>, <li>, <ol>, <blockquote>, <a>, <br>.';
        $runtime[] = '  Gebruik nooit <h1>-<h6>, <p>, <table>, <span>, <style>, <script>, of markdown-syntaxis (geen ##, geen **tekst**, geen - opsommingstekens, geen backticks).';
        $runtime[] = '- Platte tekstvelden (title, subtitle, excerpt, meta_title, meta_description): geef alleen platte tekst terug. Geen HTML-tags.';
        $runtime[] = '- Geef een enkel plat JSON-object terug. Sleutels moeten exact overeenkomen met de gevraagde veldnamen.';
        $runtime[] = '  Geen inleiding, geen uitleg, geen markdown-omheiningen — alleen het ruwe JSON-object.';

        return implode("\n", $runtime);
    }

    protected function buildUserPrompt(array $fields, array $context): string
    {
        $lines = [
            'Generate the following fields for this product:',
            implode(', ', $fields),
            '',
            'Product context:',
        ];

        $title = trim((string) ($context['title'] ?? ''));
        if ($title !== '') {
            $lines[] = 'Title: '.$title;
        }

        // When generating NL content, provide the EN title so the AI translates.
        // When generating EN content from a NL-only title, provide the NL title.
        $enTitle = trim((string) ($context['en_title'] ?? ''));
        if ($enTitle !== '') {
            $lines[] = 'EN title (translate from this): '.$enTitle;
        }

        $nlTitle = trim((string) ($context['nl_title'] ?? ''));
        if ($nlTitle !== '') {
            $lines[] = 'NL title (translate from this): '.$nlTitle;
        }

        foreach (['brand' => 'Brand', 'category' => 'Category'] as $key => $label) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        $attributes = $context['attributes'] ?? [];
        if (is_array($attributes) && ! empty($attributes)) {
            $pairs = [];
            foreach ($attributes as $k => $v) {
                if (is_string($v) && trim($v) !== '') {
                    $pairs[] = $k.': '.trim($v);
                }
            }
            if (! empty($pairs)) {
                $lines[] = 'Attributes: '.implode(', ', $pairs);
            }
        }

        $components = $context['components'] ?? [];
        if (is_array($components) && ! empty($components)) {
            $lines[] = 'Bundle components:';
            foreach ($components as $component) {
                $name = trim((string) ($component['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $qty = isset($component['quantity']) ? (int) $component['quantity'] : 1;
                $lines[] = '- '.$name.' × '.$qty;
            }
        }

        $lines[] = '';
        $lines[] = 'Return ONLY the JSON object. Keys must exactly match the requested field names.';

        return implode("\n", $lines);
    }

    protected function basePromptFilename(string $scope): string
    {
        return $scope === AiSetting::SCOPE_GROUP_PRODUCT ? 'group-product-content' : 'product-content';
    }

    protected function loadBasePrompt(string $scope = AiSetting::SCOPE_PRODUCT): string
    {
        $filename = $this->basePromptFilename($scope);
        $path = resource_path("prompts/{$filename}.md");

        if (! File::exists($path)) {
            throw new \RuntimeException("Content prompt file not found: {$filename}.md");
        }

        return File::get($path);
    }

    /**
     * Parse a flat JSON response and return only the fields requested.
     *
     * @param  list<string>  $requestedFields
     * @return array<string,string>
     */
    protected function parseFlatResponse(string $raw, array $requestedFields): array
    {
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/i', '', $raw);

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            Log::error('ProductContentGenerator: Failed to parse AI response', ['raw' => $raw]);
            throw new \RuntimeException('Failed to parse AI response.');
        }

        $out = [];
        foreach ($requestedFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            if (in_array($field, self::TRIX_FIELDS, true)) {
                $out[$field] = $this->normalizeTrixHtml($value);
            } else {
                // Plain-text fields: strip any HTML the AI may have included
                $out[$field] = trim(strip_tags($value));
            }

            if ($out[$field] === '') {
                unset($out[$field]);
            }
        }

        return $out;
    }

    protected function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html);

        return trim($html);
    }
}

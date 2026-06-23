<?php

namespace App\Http\Resources\Api;

use App\Support\ApiLocale;
use App\Support\LocalizedModelValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A public FAQ page, serialised with **every supported locale's content**
 * in a single payload. The frontend can render any locale without a
 * round-trip — and swap between them instantly on language switch.
 */
class FaqPageResource extends JsonResource
{
    private const DEFAULT_SUPPORT_TITLE = 'Still in doubt?';

    private const DEFAULT_SUPPORT_TEXT = 'Is your question not here? Contact our experts.';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locales = ApiLocale::supported();
        $localesPayload = [];
        $slugs = [];

        foreach ($locales as $locale) {
            $localesPayload[$locale] = $this->buildLocaleData($locale);
            $slugs[$locale] = LocalizedModelValue::string($this->resource, 'slug', $this->resource->slug, $locale);
        }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'hero_image' => $this->resource->getFirstMediaUrl('hero') ?: null,
            'hero_image_preview' => $this->resource->getFirstMediaUrl('hero', 'preview') ?: null,
            'main_locale' => ApiLocale::main(),
            'available_locales' => $locales,
            'slugs' => $slugs,
            'locales' => $localesPayload,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Per-locale block: title, intro, SEO, support copy and the full
     * section / item tree with localised question + answer content.
     *
     * @return array<string, mixed>
     */
    private function buildLocaleData(string $locale): array
    {
        $supportTitle = LocalizedModelValue::string($this->resource, 'support_title', $this->resource->support_title, $locale);
        $supportText = LocalizedModelValue::string($this->resource, 'support_text', $this->resource->support_text, $locale);

        return [
            'title' => LocalizedModelValue::string($this->resource, 'title', $this->resource->title, $locale),
            'intro' => LocalizedModelValue::string($this->resource, 'intro', $this->resource->intro, $locale),
            'support' => [
                'title' => $supportTitle ?: __(self::DEFAULT_SUPPORT_TITLE, [], $locale),
                'text' => $supportText ?: __(self::DEFAULT_SUPPORT_TEXT, [], $locale),
            ],
            'meta' => [
                'meta_title' => LocalizedModelValue::string($this->resource, 'meta_title', $this->resource->meta_title, $locale),
                'meta_description' => LocalizedModelValue::string($this->resource, 'meta_description', $this->resource->meta_description, $locale),
            ],
            'sections' => $this->buildSections($locale),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSections(string $locale): array
    {
        if (! $this->resource->relationLoaded('sections')) {
            return [];
        }

        return $this->resource->sections
            ->map(fn ($section) => [
                'id' => $section->id,
                'anchor' => $section->anchor,
                'icon' => $section->icon ?: null,
                'name' => LocalizedModelValue::string($section, 'name', $section->name, $locale),
                'subtitle' => LocalizedModelValue::string($section, 'subtitle', $section->subtitle, $locale),
                'items' => $section->items
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'question' => LocalizedModelValue::string($item, 'question', $item->question, $locale),
                        'answer' => LocalizedModelValue::string($item, 'answer', $item->answer, $locale),
                    ])
                    ->all(),
            ])
            ->all();
    }
}

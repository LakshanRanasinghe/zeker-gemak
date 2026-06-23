<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $guarded = [];

    protected $casts = [
        'default_fields_en' => 'array',
        'default_fields_nl' => 'array',
        'language_settings' => 'array',
        'wc_excerpt' => 'integer',
        'wc_short_description' => 'integer',
        'wc_content' => 'integer',
        'wc_product_information' => 'integer',
        'auto_detect_locale' => 'boolean',
    ];

    public const SUPPORTED_LOCALES = ['en', 'nl'];

    public const SCOPE_PRODUCT = 'product';

    public const SCOPE_GROUP_PRODUCT = 'group_product';

    public const SCOPES = [
        self::SCOPE_PRODUCT => 'Products',
        self::SCOPE_GROUP_PRODUCT => 'Group Products',
    ];

    public const GENERATABLE_FIELDS = [
        'slug',
        'subtitle',
        'excerpt',
        'short_description',
        'content',
        'product_information',
        'meta_title',
        'meta_description',
    ];

    public const GROUP_PRODUCT_GENERATABLE_FIELDS = [
        'slug',
        'subtitle',
        'excerpt',
        'short_description',
        'content',
        'meta_title',
        'meta_description',
    ];

    public const TONES = [
        'professional' => 'Professional',
        'friendly' => 'Friendly',
        'technical' => 'Technical',
        'persuasive' => 'Persuasive',
        'neutral' => 'Neutral',
    ];

    public static function generatableFieldsForScope(string $scope): array
    {
        return $scope === self::SCOPE_GROUP_PRODUCT
            ? self::GROUP_PRODUCT_GENERATABLE_FIELDS
            : self::GENERATABLE_FIELDS;
    }

    public static function defaultLocaleSettings(): array
    {
        return [
            'role_description' => 'You are an expert product content writer.',
            'taxonomy_guidelines' => '',
            'tone' => 'professional',
            'style_notes' => '',
            'wc_excerpt' => 40,
            'wc_short_description' => 75,
            'wc_content' => 300,
            'wc_product_information' => 150,
        ];
    }

    public function settingsForLocale(string $locale): array
    {
        $stored = ($this->language_settings ?? [])[$locale] ?? null;

        if ($stored !== null) {
            return array_merge(self::defaultLocaleSettings(), $stored);
        }

        // Backward compatibility: fall back to the old global fields.
        return [
            'role_description' => (string) ($this->role_description ?? ''),
            'taxonomy_guidelines' => (string) ($this->taxonomy_guidelines ?? ''),
            'tone' => (string) ($this->tone ?? 'professional'),
            'style_notes' => (string) ($this->style_notes ?? ''),
            'wc_excerpt' => (int) ($this->wc_excerpt ?? 40),
            'wc_short_description' => (int) ($this->wc_short_description ?? 75),
            'wc_content' => (int) ($this->wc_content ?? 300),
            'wc_product_information' => (int) ($this->wc_product_information ?? 150),
        ];
    }

    public static function defaults(string $scope = self::SCOPE_PRODUCT): array
    {
        $localeDefaults = self::defaultLocaleSettings();
        $languageSettings = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $languageSettings[$locale] = $localeDefaults;
        }

        return [
            'scope' => $scope,
            'role_description' => $localeDefaults['role_description'],
            'taxonomy_guidelines' => $localeDefaults['taxonomy_guidelines'],
            'tone' => $localeDefaults['tone'],
            'style_notes' => $localeDefaults['style_notes'],
            'wc_excerpt' => $localeDefaults['wc_excerpt'],
            'wc_short_description' => $localeDefaults['wc_short_description'],
            'wc_content' => $localeDefaults['wc_content'],
            'wc_product_information' => $localeDefaults['wc_product_information'],
            'language_settings' => $languageSettings,
            'default_fields_en' => [],
            'default_fields_nl' => [],
            'default_language' => 'en',
            'auto_detect_locale' => true,
        ];
    }

    public static function current(string $scope = self::SCOPE_PRODUCT): self
    {
        $setting = static::query()->where('scope', $scope)->orderBy('id')->first();

        if (! $setting) {
            $setting = static::create(static::defaults($scope));
        }

        return $setting;
    }
}

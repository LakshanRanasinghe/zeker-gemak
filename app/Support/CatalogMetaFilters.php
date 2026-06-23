<?php

namespace App\Support;

use Illuminate\Support\Str;

class CatalogMetaFilters
{
    public static function keys(): array
    {
        return array_keys(static::definitions());
    }

    public static function definitions(): array
    {
        return [
            'afwerking' => [
                'query' => 'afwerking',
                'label' => 'Finishing',
                'config_key' => 'finishing',
                'type' => 'multi_select',
                'aliases' => ['finishing'],
            ],
            'lijm' => [
                'query' => 'lijm',
                'label' => 'Adhesive',
                'config_key' => 'glue',
                'type' => 'multi_select',
                'aliases' => ['glue', 'adhesive'],
            ],
            'materiaal-code' => [
                'query' => 'materiaal-code',
                'label' => 'Material Code',
                'config_key' => 'material_code',
                'type' => 'multi_select',
                'aliases' => ['material_code'],
            ],
            'printmethode' => [
                'query' => 'printmethode',
                'label' => 'Print Method',
                'config_key' => 'druktype',
                'type' => 'multi_select',
                'aliases' => ['druktype', 'print_method'],
            ],
            'breedte' => [
                'query' => 'breedte',
                'label' => 'Width',
                'config_key' => 'meta_width',
                'type' => 'range_select',
                'aliases' => ['meta_width', 'width'],
            ],
            'hoogte' => [
                'query' => 'hoogte',
                'label' => 'Height',
                'config_key' => 'meta_height',
                'type' => 'range_select',
                'aliases' => ['meta_height', 'height'],
            ],
            'kern' => [
                'query' => 'kern',
                'label' => 'Core',
                'config_key' => 'kern',
                'type' => 'range_select',
                'aliases' => ['kern', 'core'],
            ],
            'buiten-diameter' => [
                'query' => 'buiten-diameter',
                'label' => 'Outer Diameter',
                'config_key' => 'buitendia',
                'type' => 'range_select',
                'aliases' => ['buitendia', 'outer_diameter'],
            ],
            'detectie' => [
                'query' => 'detectie',
                'label' => 'Detection',
                'config_key' => 'detectie',
                'type' => 'multi_select',
                'aliases' => ['detectie'],
            ],
            'merken' => [
                'query' => 'merken',
                'label' => 'Marks',
                'config_key' => 'merken',
                'type' => 'multi_select',
                'aliases' => ['merken'],
            ],
        ];
    }

    public static function configLabel(string $field, string $value): string
    {
        $definition = static::definitions()[$field] ?? null;
        $configKey = $definition['config_key'] ?? $field;

        return (string) config(
            "products.{$configKey}.{$value}",
            Str::of($value)->replace('_', ' ')->title()->toString()
        );
    }

    public static function extractComparableNumber(string $field, string $value): ?float
    {
        return static::extractComparableNumberFromLabel($value, static::configLabel($field, $value));
    }

    public static function extractComparableNumberFromLabel(string $value, ?string $label = null): ?float
    {
        $label = static::normalizeDecimalSeparators($label ?? $value);
        $value = static::normalizeDecimalSeparators($value);

        if (preg_match('/\(([\d.]+)\s*mm\)/i', $label, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/([\d.]+)\s*mm/i', $label, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match_all('/[\d.]+/', $label, $matches) && $matches[0] !== []) {
            return (float) end($matches[0]);
        }

        if (preg_match_all('/[\d.]+/', $value, $matches) && $matches[0] !== []) {
            return (float) end($matches[0]);
        }

        return null;
    }

    protected static function normalizeDecimalSeparators(string $value): string
    {
        return preg_replace('/(\d),(\d)/', '$1.$2', $value) ?? $value;
    }
}

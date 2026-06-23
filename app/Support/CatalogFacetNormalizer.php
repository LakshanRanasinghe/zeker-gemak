<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogFacetNormalizer
{
    /**
     * @param  iterable<mixed>|mixed  $values
     * @return array<int, string>
     */
    public static function values(mixed $values): array
    {
        return collect(is_iterable($values) ? $values : [$values])
            ->flatMap(fn (mixed $value): array => preg_split('/[,;]+/', (string) $value) ?: [])
            ->map(fn (string $value): string => trim(preg_replace('/\s+/', ' ', $value) ?? $value))
            ->map(fn (string $value): string => trim($value, " \t\n\r\0\x0B,.;:"))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique(fn (string $value): string => Str::lower($value))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, array{value: string, title: string}>>  $propertyValues
     * @return array<int, string>
     */
    public static function materialCodes(array $propertyValues): array
    {
        return static::propertyValues($propertyValues, [
            'materiaal-code',
            'materiaal_code',
            'material-code',
            'material_code',
        ]);
    }

    /**
     * @param  array<string, array<int, array{value: string, title: string}>>  $propertyValues
     * @return array<int, string>
     */
    public static function productBrands(array $propertyValues, mixed $fallback = []): array
    {
        $brands = static::propertyValues($propertyValues, [
            'brand',
            'merk',
            'product-brand',
            'product_brand',
        ]);

        return $brands !== [] ? $brands : static::values($fallback);
    }

    /**
     * @param  array<string, array<int, array{value: string, title: string}>>  $propertyValues
     * @return array<int, string>
     */
    public static function compatibleBrands(array $propertyValues, array $productBrands = []): array
    {
        $productBrands = collect(static::values($productBrands))
            ->map(fn (string $value): string => Str::lower($value))
            ->all();

        return collect(static::propertyValues($propertyValues, ['merken', 'marks', 'compatible-brands', 'compatible_brands']))
            ->reject(fn (string $value): bool => in_array(Str::lower($value), $productBrands, true))
            ->values()
            ->all();
    }

    /**
     * @param  iterable<mixed>|mixed  $values
     * @return array<int, string>
     */
    public static function materialNames(mixed $values): array
    {
        return collect(static::values($values))
            ->reject(fn (string $value): bool => static::looksLikeMaterialCode($value))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<int, array{value: string, title: string}>>  $propertyValues
     * @return array<int, string>
     */
    public static function materialNamesFromProperties(array $propertyValues): array
    {
        return static::materialNames(static::propertyValues($propertyValues, [
            'materiaal',
            'material',
            'material-type',
            'material_type',
        ]));
    }

    protected static function looksLikeMaterialCode(string $value): bool
    {
        $compact = Str::upper(str_replace([' ', '_', '-'], '', $value));

        return (bool) preg_match('/^(DIA\d+[A-Z0-9]*|\d+[A-Z]?|[A-Z]{2,}\d+[A-Z0-9]*)$/', $compact);
    }

    /**
     * @param  array<string, array<int, array{value: string, title: string}>>  $propertyValues
     * @param  array<int, string>  $aliases
     * @return array<int, string>
     */
    protected static function propertyValues(array $propertyValues, array $aliases): array
    {
        return collect($aliases)
            ->flatMap(function (string $alias) use ($propertyValues): Collection {
                $key = Str::slug($alias);

                return collect($propertyValues[$key] ?? [])
                    ->pluck('value');
            })
            ->pipe(fn (Collection $values): array => static::values($values));
    }
}

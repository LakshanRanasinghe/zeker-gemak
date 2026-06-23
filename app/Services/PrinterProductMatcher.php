<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Matches products to printers using Vanilo properties as the canonical
 * compatibility vocabulary on both sides.
 */
class PrinterProductMatcher
{
    /**
     * Returns a Product builder pre-filtered to those compatible with the
     * given printer. Caller adds further scoping (state, template, taxon,
     * pagination, etc.).
     */
    public function getMatchingProducts(Post $printer): Builder
    {
        if ($printer->post_type !== 'printer') {
            throw new InvalidArgumentException('Post must be of type printer');
        }

        $printer->loadMissing('propertyValues.property');

        $specs = $this->extractPrinterMetadata($printer);

        $query = Product::query();

        if (! $this->hasMatchingSpecs($specs)) {
            return $query->whereRaw('1 = 0');
        }

        if ($specs['printmethode_values'] !== []) {
            $this->applyEqualityFilter(
                $query,
                propertySlug: 'printmethode',
                propertyValues: $specs['printmethode_values'],
            );
        }

        if ($specs['width_min'] !== null && $specs['width_max'] !== null) {
            $this->applyNumericRangeFilter(
                $query,
                propertySlug: 'breedte',
                min: $specs['width_min'],
                max: $specs['width_max'],
            );
        } elseif ($specs['width_values'] !== []) {
            $this->applyNumericSetFilter(
                $query,
                propertySlug: 'breedte',
                allowedNumerics: $specs['width_values'],
            );
        }

        if ($specs['kern_values'] !== []) {
            $this->applyValueSetFilter(
                $query,
                propertySlug: 'kern',
                allowedValues: $specs['kern_values'],
            );
        } elseif ($specs['kern_min'] !== null && $specs['kern_max'] !== null) {
            $this->applyNumericRangeFilter(
                $query,
                propertySlug: 'kern',
                min: $specs['kern_min'],
                max: $specs['kern_max'],
            );
        }

        if ($specs['diameter_values'] !== []) {
            $this->applyValueSetFilter(
                $query,
                propertySlug: 'buiten-diameter',
                allowedValues: $specs['diameter_values'],
            );
        } elseif ($specs['max_diameter'] !== null) {
            $this->applyMaxFilter(
                $query,
                propertySlug: 'buiten-diameter',
                max: $specs['max_diameter'],
            );
        }

        return $query;
    }

    /**
     * Extract printer specs and pre-compute everything the SQL builder needs
     * so the main query stays cheap.
     *
     * @return array{
     *   printmethode_values: array<int, string>,
     *   width_values: array<int, float>,
     *   width_min: ?float,
     *   width_max: ?float,
     *   kern_values: array<int, string>,
     *   kern_min: ?float,
     *   kern_max: ?float,
     *   diameter_values: array<int, string>,
     *   max_diameter: ?float,
     *   supports_fan_fold: bool,
     * }
     */
    public function extractPrinterMetadata(Post $printer): array
    {
        $printer->loadMissing('propertyValues.property');

        $printmethodeValues = $this->propertyValues($printer, 'printmethode');
        $widthValues = $this->propertyValues($printer, 'breedte');
        $kernValues = $this->propertyValues($printer, 'kern');
        $diameterValues = $this->propertyValues($printer, 'buiten-diameter');
        $labelTypeValues = $this->propertyValues($printer, 'labeltype');
        $widthMin = $this->firstPropertyNumeric($printer, 'label-breedte-min');
        $widthMax = $this->firstPropertyNumeric($printer, 'label-breedte-max');
        $maxDiameter = $this->firstPropertyNumeric($printer, 'max-buiten-diameter');
        $supportsFanFold = $this->containsFanFold($kernValues)
            || $this->containsFanFold($diameterValues)
            || $this->containsFanFold($labelTypeValues);

        return [
            'printmethode_values' => $printmethodeValues,
            'width_values' => $this->numericSet($widthValues),
            'width_min' => $widthMin,
            'width_max' => $widthMax,
            'kern_values' => $this->withFanFoldSupport($kernValues, $supportsFanFold),
            'kern_min' => null,
            'kern_max' => null,
            'diameter_values' => $this->withFanFoldSupport($diameterValues, $supportsFanFold),
            'max_diameter' => $maxDiameter,
            'supports_fan_fold' => $supportsFanFold,
        ];
    }

    /**
     * @param  array{
     *   printmethode_values: array<int, string>,
     *   width_values: array<int, float>,
     *   width_min: ?float,
     *   width_max: ?float,
     *   kern_values: array<int, string>,
     *   kern_min: ?float,
     *   kern_max: ?float,
     *   diameter_values: array<int, string>,
     *   max_diameter: ?float,
     *   supports_fan_fold: bool,
     * }  $specs
     */
    protected function hasMatchingSpecs(array $specs): bool
    {
        return $specs['printmethode_values'] !== []
            || $specs['width_values'] !== []
            || ($specs['width_min'] !== null && $specs['width_max'] !== null)
            || $specs['kern_values'] !== []
            || ($specs['kern_min'] !== null && $specs['kern_max'] !== null)
            || $specs['diameter_values'] !== []
            || $specs['max_diameter'] !== null
            || $specs['supports_fan_fold'];
    }

    /**
     * @param  array<int, string>  $propertyValues
     */
    protected function applyEqualityFilter(
        Builder $query,
        string $propertySlug,
        array $propertyValues,
    ): void {
        $query->whereHas('propertyValues', function ($pvQuery) use ($propertySlug, $propertyValues) {
            $pvQuery->whereHas('property', fn ($p) => $p->where('slug', $propertySlug))
                ->whereIn('value', $propertyValues);
        });
    }

    /**
     * Numeric-range match against the product's Vanilo property value.
     */
    protected function applyNumericRangeFilter(
        Builder $query,
        string $propertySlug,
        float $min,
        float $max,
    ): void {
        $query->whereHas('propertyValues', function ($pvQuery) use ($propertySlug, $min, $max) {
            $pvQuery->whereHas('property', fn ($p) => $p->where('slug', $propertySlug))
                ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) BETWEEN ? AND ?', [$min, $max]);
        });
    }

    /**
     * Numeric set-membership match against the product's Vanilo property value.
     *
     * @param  array<int, float>  $allowedNumerics
     */
    protected function applyNumericSetFilter(
        Builder $query,
        string $propertySlug,
        array $allowedNumerics,
    ): void {
        $query->whereHas('propertyValues', function ($pvQuery) use ($propertySlug, $allowedNumerics) {
            $placeholders = implode(',', array_fill(0, count($allowedNumerics), '?'));
            $pvQuery->whereHas('property', fn ($p) => $p->where('slug', $propertySlug))
                ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                ->whereRaw("CAST(REPLACE(value, ',', '.') AS DECIMAL(12,4)) IN ($placeholders)", $allowedNumerics);
        });
    }

    /**
     * @param  array<int, string>  $allowedValues
     */
    protected function applyValueSetFilter(
        Builder $query,
        string $propertySlug,
        array $allowedValues,
    ): void {
        $allowedNumerics = $this->numericSet($allowedValues);

        $query->whereHas('propertyValues', function ($pvQuery) use ($propertySlug, $allowedValues, $allowedNumerics) {
            $pvQuery->whereHas('property', fn ($p) => $p->where('slug', $propertySlug))
                ->where(function ($valueQuery) use ($allowedValues, $allowedNumerics) {
                    $valueQuery->whereIn('value', $allowedValues);

                    if ($allowedNumerics !== []) {
                        $placeholders = implode(',', array_fill(0, count($allowedNumerics), '?'));
                        $valueQuery->orWhere(function ($numericQuery) use ($allowedNumerics, $placeholders) {
                            $numericQuery
                                ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                                ->whereRaw("CAST(REPLACE(value, ',', '.') AS DECIMAL(12,4)) IN ($placeholders)", $allowedNumerics);
                        });
                    }
                });
        });
    }

    /**
     * "≤ max" match against the product's Vanilo property value.
     */
    protected function applyMaxFilter(Builder $query, string $propertySlug, float $max): void
    {
        $query->whereHas('propertyValues', function ($pvQuery) use ($propertySlug, $max) {
            $pvQuery->whereHas('property', fn ($p) => $p->where('slug', $propertySlug))
                ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) <= ?', [$max]);
        });
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, float>
     */
    protected function numericSet(array $values): array
    {
        $set = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $string = str_replace(',', '.', (string) $value);

            if (! is_numeric($string)) {
                continue;
            }

            $set[] = (float) $string;
        }

        return array_values(array_unique($set));
    }

    protected function parseSingleNumeric(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $normalized = preg_replace('/(\d),(\d)/', '$1.$2', $value);
        if (! is_string($normalized)) {
            return null;
        }

        if (! preg_match('/\d+(?:\.\d+)?/', $normalized, $matches)) {
            return null;
        }

        return (float) $matches[0];
    }

    /**
     * @return array<int, string>
     */
    protected function propertyValues(Post $printer, string $slug): array
    {
        return $printer->propertyValues
            ->filter(fn ($propertyValue): bool => $propertyValue->property?->slug === $slug)
            ->pluck('value')
            ->map(fn (mixed $value): string => (string) $value)
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function firstPropertyNumeric(Post $printer, string $slug): ?float
    {
        $value = $this->propertyValues($printer, $slug)[0] ?? null;

        return $this->parseSingleNumeric($value);
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function containsFanFold(array $values): bool
    {
        return collect($values)
            ->contains(fn (string $value): bool => strcasecmp(trim($value), 'Fan-fold') === 0);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    protected function withFanFoldSupport(array $values, bool $supportsFanFold): array
    {
        if (! $supportsFanFold || $this->containsFanFold($values)) {
            return $values;
        }

        $values[] = 'Fan-fold';

        return $values;
    }
}

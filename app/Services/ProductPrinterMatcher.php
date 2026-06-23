<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches printers to products using Vanilo properties as the canonical
 * compatibility vocabulary on both sides.
 *
 * This is the reverse of PrinterProductMatcher - it finds printers
 * compatible with a specific product/label.
 */
class ProductPrinterMatcher
{
    /**
     * Returns a Post (printer) builder pre-filtered to those compatible
     * with the given product. Caller adds further scoping (status, ordering,
     * pagination, etc.).
     */
    public function getMatchingPrinters(Product $product): Builder
    {
        $product->loadMissing('propertyValues.property');

        $specs = $this->extractProductMetadata($product);

        $query = Post::query()->where('post_type', 'printer');

        if (! $this->hasMatchingSpecs($specs)) {
            return $query->whereRaw('1 = 0');
        }

        // Filter 1: Print method must match
        // Product has printmethode X → Printer must support printmethode X
        if ($specs['printmethode'] !== null) {
            $this->applyPrintmethodeFilter($query, $specs['printmethode']);
        }

        // Filter 2: Product width must fit in printer's supported width range/set
        // Product breedte = 102 → Printer must have (min ≤ 102 ≤ max) OR 102 in explicit breedte values
        if ($specs['breedte'] !== null) {
            $this->applyBreedteFilter($query, $specs['breedte']);
        }

        if ($specs['is_fan_fold']) {
            $this->applyFanFoldFilter($query);

            return $query;
        }

        // Filter 3: Product kern must be in printer's supported kern values
        // Product kern = 76 → Printer must have kern = 76 (exact or numeric match)
        if ($specs['kern'] !== null) {
            $this->applyKernFilter($query, $specs['kern']);
        }

        // Filter 4: Product outer diameter must be supported by printer
        // Product buiten-diameter = 101 → Printer must have 101 in buiten-diameter set
        // OR product.buiten-diameter ≤ printer.max-buiten-diameter
        if ($specs['buiten_diameter'] !== null || $specs['buiten_diameter_value'] !== null) {
            $this->applyBuitenDiameterFilter($query, $specs['buiten_diameter'], $specs['buiten_diameter_value']);
        }

        return $query;
    }

    /**
     * Extract product specs and pre-compute everything the SQL builder needs.
     *
     * @return array{
     *   printmethode: ?string,
     *   breedte: ?float,
     *   kern: ?string,
     *   buiten_diameter: ?float,
     *   buiten_diameter_value: ?string,
     *   is_fan_fold: bool,
     * }
     */
    public function extractProductMetadata(Product $product): array
    {
        $product->loadMissing('propertyValues.property');

        $kern = $this->firstPropertyValue($product, 'kern');
        $buitenDiameterValue = $this->firstPropertyValue($product, 'buiten-diameter');

        return [
            'printmethode' => $this->firstPropertyValue($product, 'printmethode'),
            'breedte' => $this->firstPropertyNumeric($product, 'breedte'),
            'kern' => $kern,
            'buiten_diameter' => $this->firstPropertyNumeric($product, 'buiten-diameter'),
            'buiten_diameter_value' => $buitenDiameterValue,
            'is_fan_fold' => $this->isFanFold($kern) || $this->isFanFold($buitenDiameterValue),
        ];
    }

    /**
     * @param  array{
     *   printmethode: ?string,
     *   breedte: ?float,
     *   kern: ?string,
     *   buiten_diameter: ?float,
     *   buiten_diameter_value: ?string,
     *   is_fan_fold: bool,
     * }  $specs
     */
    protected function hasMatchingSpecs(array $specs): bool
    {
        return $specs['printmethode'] !== null
            || $specs['breedte'] !== null
            || $specs['kern'] !== null
            || $specs['buiten_diameter'] !== null
            || $specs['buiten_diameter_value'] !== null
            || $specs['is_fan_fold'];
    }

    /**
     * Filter printers that support the product's print method.
     */
    protected function applyPrintmethodeFilter(Builder $query, string $printmethode): void
    {
        $query->whereHas('propertyValues', function ($pvQuery) use ($printmethode) {
            $pvQuery->whereHas('property', fn ($p) => $p->where('slug', 'printmethode'))
                ->where('value', $printmethode);
        });
    }

    /**
     * Filter printers that support the product's width.
     *
     * A printer supports a product width if:
     * 1. Product width is within printer's label-breedte-min and label-breedte-max range, OR
     * 2. Product width exists in printer's explicit breedte values
     */
    protected function applyBreedteFilter(Builder $query, float $breedte): void
    {
        $query->where(function ($outerQuery) use ($breedte) {
            // Option 1: Check if width is within printer's min-max range
            $outerQuery->whereHas('propertyValues', function ($minQuery) use ($breedte) {
                $minQuery->whereHas('property', fn ($p) => $p->where('slug', 'label-breedte-min'))
                    ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                    ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) <= ?', [$breedte]);
            })->whereHas('propertyValues', function ($maxQuery) use ($breedte) {
                $maxQuery->whereHas('property', fn ($p) => $p->where('slug', 'label-breedte-max'))
                    ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                    ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) >= ?', [$breedte]);
            });

            // Option 2: Check if width exists in printer's explicit breedte values
            $outerQuery->orWhereHas('propertyValues', function ($pvQuery) use ($breedte) {
                $pvQuery->whereHas('property', fn ($p) => $p->where('slug', 'breedte'))
                    ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                    ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) = ?', [$breedte]);
            });
        });
    }

    /**
     * Filter printers that support the product's kern (core diameter).
     *
     * A printer supports a product kern if the kern value exists in the
     * printer's kern property values (exact string match or numeric match).
     */
    protected function applyKernFilter(Builder $query, string $kern): void
    {
        $kernNumeric = $this->parseSingleNumeric($kern);

        $query->whereHas('propertyValues', function ($pvQuery) use ($kern, $kernNumeric) {
            $pvQuery->whereHas('property', fn ($p) => $p->where('slug', 'kern'))
                ->where(function ($valueQuery) use ($kern, $kernNumeric) {
                    // Exact string match
                    $valueQuery->where('value', $kern);

                    // Numeric match if kern is numeric
                    if ($kernNumeric !== null) {
                        $valueQuery->orWhere(function ($numericQuery) use ($kernNumeric) {
                            $numericQuery
                                ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                                ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) = ?', [$kernNumeric]);
                        });
                    }
                });
        });
    }

    /**
     * Fan-fold media has no physical roll core. A printer supports it only
     * when Fan-fold is explicitly present on feed-related printer properties.
     */
    protected function applyFanFoldFilter(Builder $query): void
    {
        $query->whereHas('propertyValues', function ($pvQuery) {
            $pvQuery
                ->whereHas('property', fn ($p) => $p->whereIn('slug', ['kern', 'buiten-diameter', 'labeltype']))
                ->whereRaw('LOWER(TRIM(value)) = ?', ['fan-fold']);
        });
    }

    /**
     * Filter printers that support the product's outer diameter.
     *
     * A printer supports a product outer diameter if:
     * 1. The value exists in printer's explicit buiten-diameter values, OR
     * 2. Numeric diameter is ≤ printer's max-buiten-diameter
     */
    protected function applyBuitenDiameterFilter(Builder $query, ?float $diameter, ?string $diameterValue): void
    {
        $query->where(function ($outerQuery) use ($diameter, $diameterValue) {
            // Option 1: Check if diameter exists in printer's explicit values
            $outerQuery->whereHas('propertyValues', function ($pvQuery) use ($diameter, $diameterValue) {
                $pvQuery->whereHas('property', fn ($p) => $p->where('slug', 'buiten-diameter'))
                    ->where(function ($valueQuery) use ($diameter, $diameterValue) {
                        if ($diameterValue !== null) {
                            $valueQuery->where('value', $diameterValue);
                        }

                        if ($diameter !== null) {
                            $method = $diameterValue !== null ? 'orWhere' : 'where';

                            $valueQuery->{$method}(function ($numericQuery) use ($diameter) {
                                $numericQuery
                                    ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                                    ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) = ?', [$diameter]);
                            });
                        }
                    });
            });

            // Option 2: Check if diameter is within printer's max
            if ($diameter !== null) {
                $outerQuery->orWhereHas('propertyValues', function ($pvQuery) use ($diameter) {
                    $pvQuery->whereHas('property', fn ($p) => $p->where('slug', 'max-buiten-diameter'))
                        ->whereRaw("SUBSTR(TRIM(REPLACE(value, ',', '.')), 1, 1) BETWEEN '0' AND '9'")
                        ->whereRaw('CAST(REPLACE(value, ",", ".") AS DECIMAL(12,4)) >= ?', [$diameter]);
                });
            }
        });
    }

    /**
     * Parse a single numeric value from mixed input.
     */
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
     * Get first property value for a given slug.
     */
    protected function firstPropertyValue(Product $product, string $slug): ?string
    {
        $value = $product->propertyValues
            ->filter(fn ($propertyValue): bool => $propertyValue->property?->slug === $slug)
            ->pluck('value')
            ->first();

        return $value !== null ? (string) $value : null;
    }

    /**
     * Get first property value as numeric for a given slug.
     */
    protected function firstPropertyNumeric(Product $product, string $slug): ?float
    {
        $value = $this->firstPropertyValue($product, $slug);

        return $this->parseSingleNumeric($value);
    }

    protected function isFanFold(?string $value): bool
    {
        return $value !== null && strcasecmp(trim($value), 'Fan-fold') === 0;
    }
}

<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductWarrantyOptionService
{
    /**
     * Sync warranty options for a product.
     *
     * @param  Product  $product  The product to sync warranty options for
     * @param  array  $warrantyOptions  Array of warranty option data from request
     */
    public function syncForProduct(Product $product, array $warrantyOptions): void
    {
        DB::transaction(function () use ($product, $warrantyOptions) {
            // Normalize the data
            $normalizedOptions = $this->normalizeWarrantyOptions($warrantyOptions);

            // Collect IDs that should remain
            $submittedIds = collect($normalizedOptions)
                ->filter(fn ($option) => ! empty($option['id']))
                ->pluck('id')
                ->all();

            // Delete warranty options that were removed
            $product->warrantyOptions()
                ->when(! empty($submittedIds), fn ($q) => $q->whereNotIn('id', $submittedIds))
                ->when(empty($submittedIds), fn ($q) => $q)
                ->delete();

            // Create or update warranty options
            foreach ($normalizedOptions as $optionData) {
                if (! empty($optionData['id'])) {
                    // Update existing warranty option (only if it belongs to this product)
                    $product->warrantyOptions()
                        ->where('id', $optionData['id'])
                        ->update([
                            'name' => $optionData['name'],
                            'duration_months' => $optionData['duration_months'],
                            'price' => $optionData['price'],
                            'description' => $optionData['description'] ?? null,
                            'is_active' => $optionData['is_active'],
                            'sort_order' => $optionData['sort_order'],
                        ]);
                } else {
                    // Create new warranty option
                    $product->warrantyOptions()->create([
                        'name' => $optionData['name'],
                        'duration_months' => $optionData['duration_months'],
                        'price' => $optionData['price'],
                        'description' => $optionData['description'] ?? null,
                        'is_active' => $optionData['is_active'],
                        'sort_order' => $optionData['sort_order'],
                    ]);
                }
            }
        });
    }

    /**
     * Normalize warranty options data from request.
     *
     * @param  array  $options  Raw warranty options from request
     * @return array Normalized warranty options
     */
    protected function normalizeWarrantyOptions(array $options): array
    {
        return collect($options)->map(function ($option) {
            return [
                'id' => $option['id'] ?? null,
                'name' => $option['name'] ?? '',
                'duration_months' => (int) ($option['duration_months'] ?? 0),
                'price' => (float) ($option['price'] ?? 0),
                'description' => $option['description'] ?? null,
                // Normalize checkbox value (can be '1', 1, true, 'on', etc.)
                'is_active' => filter_var($option['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => (int) ($option['sort_order'] ?? 0),
            ];
        })->all();
    }

    /**
     * Delete all warranty options for a product.
     */
    public function deleteAllForProduct(Product $product): void
    {
        $product->warrantyOptions()->delete();
    }

    /**
     * Duplicate warranty options from one product to another.
     */
    public function duplicateForProduct(Product $sourceProduct, Product $targetProduct): void
    {
        $sourceProduct->warrantyOptions->each(function ($option) use ($targetProduct) {
            $targetProduct->warrantyOptions()->create([
                'name' => $option->name,
                'duration_months' => $option->duration_months,
                'price' => $option->price,
                'description' => $option->description,
                'is_active' => $option->is_active,
                'sort_order' => $option->sort_order,
            ]);
        });
    }
}

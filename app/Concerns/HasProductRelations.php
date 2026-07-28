<?php

namespace App\Concerns;

use App\Models\Product;
use App\Models\ProductRelation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasProductRelations
{
    public function productRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'product_id');
    }

    public function upSells(): BelongsToMany
    {
        return $this->relatedProductsOfType(ProductRelation::TYPE_UPSELL);
    }

    public function crossSells(): BelongsToMany
    {
        return $this->relatedProductsOfType(ProductRelation::TYPE_CROSSSELL);
    }

    public function syncProductRelations(string $type, array $productIds): void
    {
        $this->productRelations()->where('relation_type', $type)->delete();

        $position = 0;
        $seen = [];

        foreach ($productIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || $id === (int) $this->getKey() || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $this->productRelations()->create([
                'related_product_id' => $id,
                'relation_type' => $type,
                'position' => $position++,
            ]);
        }
    }

    protected function relatedProductsOfType(string $type): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_relations',
            'product_id',
            'related_product_id',
        )
            ->wherePivot('relation_type', $type)
            ->withPivot(['relation_type', 'position'])
            ->orderBy('product_relations.position');
    }
}

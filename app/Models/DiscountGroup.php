<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vanilo\Properties\Models\PropertyValue;

class DiscountGroup extends Model
{
    protected $fillable = [
        'name',
        'discounts',
    ];

    protected static function booted(): void
    {
        static::saved(function (DiscountGroup $discountGroup) {
            // Detach all products currently associated with this group
            $discountGroup->products()->update(['discount_group_id' => null]);

            // Find products matching the new materiaal-code and associate them
            $propertyValue = PropertyValue::findByPropertyAndValue('materiaal-code', $discountGroup->name);

            if ($propertyValue) {
                $productIds = Product::whereHas('propertyValues', function ($query) use ($propertyValue) {
                    $query->where('property_values.id', $propertyValue->id);
                })->pluck('products.id');

                if ($productIds->isNotEmpty()) {
                    Product::whereIn('id', $productIds)->update(['discount_group_id' => $discountGroup->id]);
                }
            }
        });

        static::deleting(function (DiscountGroup $discountGroup) {
            $discountGroup->products()->update(['discount_group_id' => null]);
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

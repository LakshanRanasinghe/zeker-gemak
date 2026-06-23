<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarrantyGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (WarrantyGroup $warrantyGroup): void {
            $productIds = $warrantyGroup->products()->pluck('id');

            $warrantyGroup->products()->update(['warranty_group_id' => null]);

            if ($productIds->isNotEmpty()) {
                Product::query()->whereKey($productIds)->searchable();
            }
        });
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductWarrantyOption::class)->ordered();
    }

    public function activeOptions(): HasMany
    {
        return $this->hasMany(ProductWarrantyOption::class)->active()->ordered();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function reindexAssignedProducts(): void
    {
        $this->products()->searchable();
    }
}

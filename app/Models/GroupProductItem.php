<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupProductItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_product_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the group product this item belongs to.
     */
    public function groupProduct(): BelongsTo
    {
        return $this->belongsTo(GroupProduct::class, 'group_product_id');
    }

    /**
     * Get the component product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Calculate the number of available sets from this component.
     */
    public function availableSets(): int
    {
        if (! $this->product) {
            return 0;
        }

        $productStock = (int) ($this->product->stock ?? 0);
        $requiredQty = max(1, $this->quantity);

        return (int) floor($productStock / $requiredQty);
    }
}

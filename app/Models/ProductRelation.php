<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends Model
{
    public const TYPE_UPSELL = 'upsell';

    public const TYPE_CROSSSELL = 'crosssell';

    protected $fillable = [
        'product_id',
        'related_product_id',
        'relation_type',
        'position',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'related_product_id' => 'integer',
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}

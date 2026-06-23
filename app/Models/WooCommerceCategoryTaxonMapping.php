<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Vanilo\Foundation\Models\Taxon;

class WooCommerceCategoryTaxonMapping extends Model
{
    protected $table = 'woocommerce_category_taxon_mappings';

    protected $fillable = [
        'woocommerce_category_id',
        'taxon_id',
        'slug',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_category_id' => 'integer',
            'taxon_id' => 'integer',
        ];
    }

    public function taxon(): BelongsTo
    {
        return $this->belongsTo(Taxon::class, 'taxon_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWarrantyOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warranty_group_id',
        'name',
        'duration_months',
        'price',
        'description',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price' => 'decimal:4',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the product that owns this warranty option.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warrantyGroup(): BelongsTo
    {
        return $this->belongsTo(WarrantyGroup::class);
    }

    /**
     * Scope a query to only include active warranty options.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order warranty options by sort_order and duration.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('duration_months');
    }

    /**
     * Get formatted duration (e.g., "12 months", "24 months").
     */
    public function getFormattedDurationAttribute(): string
    {
        return $this->duration_months.' '.trans_choice('months', $this->duration_months);
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return config('app.currency_symbol', '€').number_format((float) $this->price, 2, ',', '.');
    }
}

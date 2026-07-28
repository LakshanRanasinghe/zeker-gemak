<?php

namespace App\Models;

class Taxon extends \Vanilo\Foundation\Models\Taxon
{
    protected $fillable = ['woocommerce_id', 'woocommerce_parent_id', 'is_active', 'synced_at', 'taxonomy_id', 'parent_id', 'priority', 'name', 'slug', 'meta_title', 'meta_description', 'description', 'excerpt', 'subtitle', 'top_content', 'bottom_content'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'woocommerce_id' => 'integer',
            'woocommerce_parent_id' => 'integer',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}

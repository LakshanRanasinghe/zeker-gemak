<?php

namespace App\Models;

class Taxon extends \Vanilo\Foundation\Models\Taxon
{
    protected $fillable = ['taxonomy_id', 'parent_id', 'priority', 'name', 'slug', 'meta_title', 'meta_description', 'description', 'excerpt', 'subtitle', 'top_content', 'bottom_content'];

    public function materials()
    {
        return $this->morphedByMany(
            Material::class,
            'model',
            'model_taxons',
            'taxon_id',
            'model_id'
        );
    }
}

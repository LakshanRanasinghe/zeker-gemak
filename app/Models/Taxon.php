<?php

namespace App\Models;

class Taxon extends \Vanilo\Foundation\Models\Taxon
{
    protected $fillable = ['taxonomy_id', 'parent_id', 'priority', 'name', 'slug', 'meta_title', 'meta_description', 'description', 'excerpt', 'subtitle', 'top_content', 'bottom_content'];
}

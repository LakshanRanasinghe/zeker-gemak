<?php

namespace App\Models;

use App\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasFactory;
    use HasUniqueSlug;

    protected $slugSource = 'name';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        // Slugging is handled by HasUniqueSlug trait
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}

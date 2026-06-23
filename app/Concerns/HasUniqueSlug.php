<?php

namespace App\Concerns;

use Illuminate\Support\Str;

trait HasUniqueSlug
{
    /**
     * Boot the trait and register the saving event.
     */
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function ($model) {
            $model->generateUniqueSlug();
        });
    }

    /**
     * Generate a unique slug for the model.
     */
    public function generateUniqueSlug(): void
    {
        $sourceField = property_exists($this, 'slugSource') ? $this->slugSource : 'title';

        // If the slug is already set and we aren't forcing a regeneration,
        // we still need to check for uniqueness in case the user manually
        // entered a slug that already exists.
        $slug = $this->slug ?: Str::slug($this->$sourceField);

        if (empty($slug)) {
            return;
        }

        $originalSlug = $slug;
        $suffix = 1;

        while ($this->slugExists($slug)) {
            $slug = $originalSlug.'-'.$suffix++;
        }

        $this->slug = $slug;
    }

    /**
     * Check if the given slug already exists in the database.
     */
    protected function slugExists(string $slug): bool
    {
        return static::where('slug', $slug)
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->exists();
    }
}

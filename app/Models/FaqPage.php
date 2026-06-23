<?php

namespace App\Models;

use App\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Vanilo\Translation\Traits\HasTranslations;

/**
 * A public-facing FAQ page (a structured FAQ hub).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $intro
 * @property string|null $support_title
 * @property string|null $support_text
 * @property string $status
 */
class FaqPage extends Model implements HasMedia
{
    use HasFactory;
    use HasTranslations;
    use HasUniqueSlug;
    use InteractsWithMedia;
    use SoftDeletes;

    /** Fields editable per-locale (main locale on the model, others in `translations`). */
    public const TRANSLATABLE_FIELDS = [
        'title',
        'slug',
        'intro',
        'support_title',
        'support_text',
        'meta_title',
        'meta_description',
    ];

    protected $slugSource = 'title';

    protected $fillable = [
        'title',
        'slug',
        'intro',
        'support_title',
        'support_text',
        'status',
        'meta_title',
        'meta_description',
    ];

    protected static function booted(): void
    {
        static::deleting(function (FaqPage $page) {
            // Soft delete: keep sections/translations so the page can be restored.
            if (method_exists($page, 'isForceDeleting') && ! $page->isForceDeleting()) {
                return;
            }

            WysiwygMedia::cleanupFromHtml($page->intro);
            $page->translations()->delete();
            // Delete sections through Eloquent so each one cleans up its
            // own translations (a raw FK cascade would skip model events).
            $page->sections()->get()->each->delete();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('preview')
            ->fit(Fit::Contain, 600, 400)
            ->nonQueued();
    }

    /**
     * Sections in display order.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(FaqSection::class, 'faq_page_id')->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Vanilo\Translation\Traits\HasTranslations;

/**
 * A section on a FAQ page. Groups a reusable, ordered set of FAQ items
 * and provides the in-page navigation anchor.
 *
 * @property int $id
 * @property int $faq_page_id
 * @property string $name
 * @property string|null $subtitle
 * @property string|null $icon
 * @property int $sort_order
 */
class FaqSection extends Model
{
    use HasTranslations;

    /** Fields editable per-locale (main locale on the model, others in `translations`). */
    public const TRANSLATABLE_FIELDS = ['name', 'subtitle'];

    protected $fillable = [
        'faq_page_id',
        'name',
        'subtitle',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (FaqSection $section) {
            $section->translations()->delete();
            // faq_section_item rows are removed by the DB cascade.
        });
    }

    /**
     * Stable, locale-independent in-page navigation anchor.
     */
    protected function anchor(): Attribute
    {
        return Attribute::make(get: fn () => 'faq-section-'.$this->id);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(FaqPage::class, 'faq_page_id');
    }

    /**
     * Ordered list of reusable FAQ items shown in this section.
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(FaqItem::class, 'faq_section_item')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('faq_section_item.sort_order');
    }
}

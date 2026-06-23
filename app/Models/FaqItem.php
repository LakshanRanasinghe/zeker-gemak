<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Vanilo\Translation\Traits\HasTranslations;

/**
 * A single reusable question / answer from the central FAQ bank.
 *
 * @property int $id
 * @property string $question
 * @property string|null $answer
 */
class FaqItem extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

    /** Fields editable per-locale (main locale on the model, others in `translations`). */
    public const TRANSLATABLE_FIELDS = ['question', 'answer'];

    protected $fillable = [
        'question',
        'answer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (FaqItem $item) {
            // Keep everything intact on a soft delete so the item can be
            // restored; only purge media/translations on a hard delete.
            if (! $item->isForceDeleting()) {
                return;
            }

            WysiwygMedia::cleanupFromHtml($item->answer);
            $item->translations()->delete();
            $item->sections()->detach();
        });
    }

    /**
     * Sections (across any FAQ page) that reuse this item.
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(FaqSection::class, 'faq_section_item')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('faq_section_item.sort_order');
    }
}

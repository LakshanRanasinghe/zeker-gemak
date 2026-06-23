<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WysiwygMedia extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('wysiwyg')
            ->useDisk('public');
    }

    public static function cleanupFromHtml(?string ...$htmlFields): void
    {
        $ids = [];

        foreach ($htmlFields as $html) {
            if (! $html) {
                continue;
            }

            preg_match_all('/data-trix-attachment="([^"]*)"/', $html, $matches);

            foreach ($matches[1] as $json) {
                $data = json_decode(html_entity_decode($json), true);
                if (! empty($data['wysiwygMediaId'])) {
                    $ids[] = (int) $data['wysiwygMediaId'];
                }
            }
        }

        if (! empty($ids)) {
            static::whereIn('id', $ids)->each(fn ($m) => $m->delete());
        }
    }
}

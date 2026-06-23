<?php

namespace App\Concerns;

use App\Models\WysiwygMedia;

trait HandlesWysiwygMedia
{
    /**
     * Return the current values of all wysiwyg fields on this component.
     */
    abstract protected function wysiwygFieldValues(): array;

    protected function cleanupRemovedWysiwygMedia(array $oldIds): void
    {
        if (empty($oldIds)) {
            return;
        }

        $currentIds = $this->extractWysiwygMediaIds(...$this->wysiwygFieldValues());
        $removedIds = array_diff($oldIds, $currentIds);

        if (! empty($removedIds)) {
            WysiwygMedia::whereIn('id', $removedIds)->each(fn ($m) => $m->delete());
        }
    }

    protected function extractWysiwygMediaIds(?string ...$htmlStrings): array
    {
        $ids = [];
        foreach ($htmlStrings as $html) {
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

            preg_match_all('/data-wysiwyg-media-id="(\d+)"/', $html, $quillMatches);
            foreach ($quillMatches[1] as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_unique($ids);
    }
}

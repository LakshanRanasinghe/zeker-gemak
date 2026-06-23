<?php

namespace App\Services;

class SkuGenerator
{
    /**
     * Generate a unique SKU from a product title.
     *
     * Builds a prefix from the title's initials/words, then appends a
     * timestamp-based suffix (seconds + microseconds in base-36) to
     * guarantee uniqueness without any database lookups.
     */
    public function fromTitle(string $title): string
    {
        $prefix = $this->buildPrefix($title);
        $suffix = $this->timeSuffix();

        return $prefix.$suffix;
    }

    protected function buildPrefix(string $title): string
    {
        $words = preg_split('/[\s\-_]+/', trim($title));
        $words = array_filter($words, fn (string $w) => $w !== '');

        if (count($words) >= 3) {
            $prefix = collect(array_slice($words, 0, 4))
                ->map(fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)))
                ->implode('');
        } elseif (count($words) === 2) {
            $prefix = collect($words)
                ->map(fn (string $w) => mb_strtoupper(mb_substr($w, 0, 2)))
                ->implode('');
        } else {
            $prefix = mb_strtoupper(mb_substr($words[0] ?? 'PROD', 0, 4));
        }

        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix);

        return $prefix !== '' ? $prefix : 'PROD';
    }

    protected function timeSuffix(): string
    {
        // microtime(true) gives e.g. 1750612345.6789
        // We take the last 6 digits of the seconds + 4 digits of microseconds
        // and encode as uppercase base-36 for a short, unique string
        [$micro, $sec] = explode(' ', microtime());
        $unique = substr($sec, -6).substr(str_replace('0.', '', $micro), 0, 4);

        return strtoupper(base_convert($unique, 10, 36));
    }
}

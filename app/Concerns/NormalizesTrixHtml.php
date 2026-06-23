<?php

namespace App\Concerns;

trait NormalizesTrixHtml
{
    /**
     * Normalise AI-generated HTML so Trix's loadHTML() accepts it without
     * silently dropping blocks.
     *
     * Allowed Trix tags: div, strong, em, del, ul, ol, li, blockquote, a, br.
     * Everything else is either converted (p→div, h*→div) or stripped.
     */
    protected function normalizeTrixHtml(string $html): string
    {
        // Security: strip scripts, style blocks, and inline event handlers
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html);

        // Trix does not support <p> — convert to <div>
        $html = preg_replace('/<p(\s[^>]*)?>/', '<div>', $html);
        $html = str_replace('</p>', '</div>', $html);

        // Headings are not supported — convert to <div> (keep text)
        $html = preg_replace('/<h[1-6](\s[^>]*)?>/', '<div>', $html);
        $html = preg_replace('/<\/h[1-6]>/', '</div>', $html);

        // Strip <span> wrappers (keep inner content)
        $html = preg_replace('/<\/?span(\s[^>]*)?>/', '', $html);

        // Strip table structure (keep cell text)
        $html = preg_replace('/<\/?(table|thead|tbody|tfoot|tr|th|td)(\s[^>]*)?>/', '', $html);

        // Strip <img> entirely
        $html = preg_replace('/<img\b[^>]*\/?>/i', '', $html);

        // Replace non-breaking spaces with a regular space
        $html = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $html);

        // Collapse runs of blank <div><br></div> down to a single spacer
        $html = preg_replace('/(<div><br><\/div>\s*){2,}/', '<div><br></div>', $html);

        return trim($html);
    }
}

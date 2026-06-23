<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FaqPageResource;
use App\Models\FaqPage;
use App\Support\ApiLocale;
use Illuminate\Http\Request;
use Vanilo\Translation\Models\Translation;

class FaqController extends Controller
{
    /**
     * List published FAQ pages (lightweight — no sections / items).
     */
    public function index(Request $request)
    {
        app()->setLocale(ApiLocale::resolve($request));

        $pages = FaqPage::published()
            ->with(['translations', 'media'])
            ->latest()
            ->get();

        return FaqPageResource::collection($pages);
    }

    /**
     * Show a single published FAQ page by slug.
     *
     * The slug can be the **main slug** OR any **translated slug** in any
     * supported locale — the response always carries every locale's
     * content, so the frontend can render whichever language the visitor
     * is on.
     */
    public function show(Request $request, string $slug)
    {
        app()->setLocale(ApiLocale::resolve($request));

        $with = [
            'translations',
            'media',
            'sections.translations',
            'sections.items.translations',
        ];

        // 1. Try the main-locale slug column first.
        $page = FaqPage::published()->with($with)->where('slug', $slug)->first();

        // 2. Otherwise, look through translation slugs across every supported locale.
        if (! $page) {
            $morphClass = (new FaqPage)->getMorphClass();

            foreach (ApiLocale::supported() as $locale) {
                if ($locale === ApiLocale::main()) {
                    continue;
                }

                $translation = Translation::findBySlug($morphClass, $slug, $locale);
                if (! $translation) {
                    continue;
                }

                $page = FaqPage::published()
                    ->with($with)
                    ->whereKey($translation->translatable_id)
                    ->first();

                if ($page) {
                    break;
                }
            }
        }

        abort_if($page === null, 404);

        return new FaqPageResource($page);
    }
}

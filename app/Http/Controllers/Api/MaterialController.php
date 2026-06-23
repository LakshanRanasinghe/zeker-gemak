<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MaterialResource;
use App\Models\Material;
use App\Support\ApiLocale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Vanilo\Translation\Models\Translation;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $materials = Material::query()
            ->with(['translations', 'taxons'])
            // Category filters (internally uses Vanilo taxons)
            ->when($request->query('category_id'), function ($q, $id) {
                $q->whereHas('taxons', fn ($query) => $query->where('taxons.id', $id));
            })
            ->when($request->query('category_slug'), function ($q, $slug) {
                $q->whereHas('taxons', fn ($query) => $query->where('taxons.slug', $slug));
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            // Free-text search across base + translated fields
            ->when($request->query('search'), function ($q, $search) {
                $term = trim((string) $search);
                if ($term === '') {
                    return;
                }
                $like = '%'.$term.'%';
                $q->where(function ($inner) use ($like, $term) {
                    $inner->where('title', 'like', $like)
                        ->orWhere('subtitle', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhereHas('translations', function ($translationQuery) use ($term) {
                            // Vanilo translations store localized values in a
                            // JSON `fields` column; match against the raw text.
                            $translationQuery->where('fields', 'like', '%'.$term.'%');
                        });
                });
            })
            ->paginate($request->query('per_page', 15));

        return MaterialResource::collection($materials);
    }

    public function show(int $id)
    {
        $material = Material::with([
            'translations',
            'taxons',
            'products' => function ($query) {
                $query->with('translations')
                    ->where('state', 'active')
                    ->orderBy('name');
            },
        ])->findOrFail($id);

        return new MaterialResource($material);
    }

    public function showBySlug(string $slug)
    {
        $locale = ApiLocale::current();

        $material = Material::with([
            'translations',
            'taxons',
            'products' => function ($query) {
                $query->with('translations')
                    ->where('state', 'active')
                    ->orderBy('name');
            },
        ])
            ->where('slug', $slug)
            ->first();

        if (! $material && $locale !== ApiLocale::main()) {
            $translation = Translation::findBySlug(morph_type_of(Material::class), $slug, $locale);

            if ($translation) {
                $material = Material::with([
                    'translations',
                    'taxons',
                    'products' => function ($query) {
                        $query->with('translations')
                            ->where('state', 'active')
                            ->orderBy('name');
                    },
                ])
                    ->whereKey($translation->translatable_id)
                    ->first();
            }
        }

        abort_unless($material, 404);

        return new MaterialResource($material);
    }

    /**
     * Deliver the material's spec sheet for the requested locale.
     *
     * The locale comes from `?lang=` (resolved by SetApiLocale). If an
     * administrator has uploaded a PDF for that locale, the stored file is
     * streamed directly. Otherwise a PDF is generated on the fly — with the
     * material's content translated into that locale — served to the user,
     * and never persisted, so every click reflects the latest data.
     */
    public function downloadSpecSheet(int $id)
    {
        $material = Material::with('taxons')->findOrFail($id);

        $locale = ApiLocale::current();

        $media = $material->specSheetMedia($locale);

        if ($media) {
            return response()->download($media->getPath(), $media->file_name);
        }

        $localized = $this->localizedMaterialContent($material, $locale);

        $pdf = Pdf::loadView('pdf.material-spec-sheet', [
            'material' => $material,
            'title' => $localized['title'],
            'subtitle' => $localized['subtitle'],
            'specs' => $localized['specs'],
        ])->setPaper('a4');

        $fileName = Str::slug($material->code ?: $material->title ?: 'material-'.$material->id).'-spec-sheet.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Resolve the material's translatable content (title, subtitle and the
     * specifications list) for the given locale, falling back to the stored
     * main-locale values when no translation exists.
     *
     * @return array{title: string, subtitle: string, specs: array<int, array<string, string>>}
     */
    protected function localizedMaterialContent(Material $material, string $locale): array
    {
        $title = (string) $material->title;
        $subtitle = (string) $material->subtitle;
        $specs = $material->specifications['material_specs'] ?? [];

        if ($locale !== ApiLocale::main()) {
            $translation = Translation::findByModel($material, $locale);
            $fields = is_array($translation?->fields) ? $translation->fields : [];

            $title = (string) ($fields['title'] ?? $fields['name'] ?? $title);
            $subtitle = (string) ($fields['subtitle'] ?? $subtitle);

            if (is_array($fields['specifications']['material_specs'] ?? null)) {
                $specs = $fields['specifications']['material_specs'];
            }
        }

        return ['title' => $title, 'subtitle' => $subtitle, 'specs' => $specs];
    }
}

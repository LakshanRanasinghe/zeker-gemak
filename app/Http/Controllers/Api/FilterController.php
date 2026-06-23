<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CategoryGroupResource;
use App\Services\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Vanilo\Foundation\Models\Taxonomy;

class FilterController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $categories = Taxonomy::query()
            ->with([
                'taxons' => fn ($query) => $query
                    ->whereNull('parent_id')
                    ->with([
                        'children' => fn ($childQuery) => $childQuery
                            ->with('children')
                            ->orderBy('priority')
                            ->orderBy('name'),
                    ])
                    ->orderBy('priority')
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        $request->attributes->set('category_counts', $this->catalog->categoryCounts());

        return response()->json([
            'data' => [
                'types' => $this->catalog->typeOptions(),
                'sort' => $this->catalog->sortOptions(),
                'categories' => CategoryGroupResource::collection($categories)->resolve($request),
                'filters' => $this->catalog->productFilters(),
                'meta' => $this->catalog->metaFilterOptions(),
            ],
        ]);
    }
}

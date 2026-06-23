<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CategoryGroupResource;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\Taxon;
use App\Services\ProductCatalogService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Vanilo\Foundation\Models\Taxonomy;

class CategoryController extends Controller
{
    public function __construct(
        protected ProductCatalogService $catalog,
    ) {}

    public function index(Request $request)
    {
        $categories = Taxonomy::query()
            ->with([
                'taxons' => fn ($query) => $query
                    ->whereNull('parent_id')
                    ->with('children')
                    ->orderBy('priority')
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        // Walk the taxon tree one level at a time, eager-loading `children`
        // until a level has none. This returns the full nested subcategory
        // hierarchy to any depth without a fixed nesting limit, while keeping
        // the query count bounded to the depth of the deepest branch.
        $level = EloquentCollection::make(
            $categories->pluck('taxons')->flatten(1)->all()
        );

        while ($level->isNotEmpty()) {
            $next = EloquentCollection::make(
                $level->pluck('children')->flatten(1)->all()
            );

            if ($next->isEmpty()) {
                break;
            }

            $next->load('children');
            $level = $next;
        }

        // Each card on the category archive page shows the product count for
        // its taxon. `category_ids` in the product index already includes the
        // full ancestor chain (see Product::taxonIdsForSearch), so this
        // aggregation gives descendant-inclusive counts — clicking into a
        // parent shows all products from any nested subcategory.
        $request->attributes->set('category_counts', $this->catalog->categoryCounts());

        return CategoryGroupResource::collection($categories);
    }

    public function products(Request $request, string $slug)
    {
        $taxon = Taxon::where('slug', $slug)->firstOrFail();
        $perPage = (int) $request->query('per_page', 15);

        $simple = Product::query()
            ->with('activeWarrantyOptions')
            ->withCount('activeWarrantyOptions')
            ->whereHas('taxons', function ($q) use ($taxon) {
                $q->where('taxons.id', $taxon->id);
            })
            ->paginate($perPage);

        $master = MasterProduct::whereHas('taxons', function ($q) use ($taxon) {
            $q->where('taxons.id', $taxon->id);
        })->paginate($perPage);

        $mergedItems = $simple->getCollection()->merge($master->getCollection())->sortByDesc('created_at')->values();

        $paginated = new LengthAwarePaginator(
            $mergedItems,
            $simple->total() + $master->total(),
            $perPage * 2,
            $simple->currentPage(),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return ProductResource::collection($paginated)->additional([
            'category' => new CategoryResource($taxon),
        ]);
    }
}

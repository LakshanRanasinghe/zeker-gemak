<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\MaterialResource;
use App\Http\Resources\Api\PrinterResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\Material;
use App\Models\Post;
use App\Models\Product;
use App\Services\ProductCatalogService;
use App\Services\ProductPrinterMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const PRODUCT_TYPE_LABELS = 'labels';

    private const PRODUCT_TYPE_INK = 'ink';

    private const LABELS_TAXON_SLUGS = [
        'labels-en-tickets',
        'labels-en-tickets-en',
    ];

    private const INK_TAXON_SLUG = 'inkt-cartridges';

    public function __construct(protected ProductCatalogService $catalog) {}

    public function index(Request $request)
    {
        $language = (string) $request->query('lang', app()->getLocale());
        app()->setLocale($language);

        $result = $this->catalog->paginate($request->query());

        return ProductResource::collection($result['paginator'])
            ->additional([
                'meta' => [
                    'in_stock_count' => $result['in_stock_count'],
                ],
            ]);
    }

    public function show(string $type, int $id): ProductResource
    {
        $product = $this->catalog->findByTypeAndId($type, $id);

        return new ProductResource($product);
    }

    public function showBySlug(Request $request, string $type, string $slug): ProductResource
    {
        $product = $this->catalog->findByTypeAndSlug($type, $slug);

        return new ProductResource($product);
    }

    public function getMaterialProducts(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'product_type' => 'nullable|string|in:labels,ink',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $material = Material::with('category')->find($validated['material_id']);

        if (! $material) {
            return response()->json([
                'message' => 'Material not found',
            ], 404);
        }

        $productType = $validated['product_type'] ?? null;
        $perPage = $validated['per_page'] ?? 15;

        try {
            // Get products for this material
            $query = Product::query()
                ->with('activeWarrantyOptions')
                ->withCount('activeWarrantyOptions')
                ->where('material_id', $material->id);

            // Apply product type filter (taxon-based)
            $taxonSlugs = $this->getTaxonSlugsForProductType($productType);
            if (! empty($taxonSlugs)) {
                $query->whereHas('taxons', function ($q) use ($taxonSlugs) {
                    // Match products assigned to the taxon OR any of its ancestors
                    $q->where(function ($query) use ($taxonSlugs) {
                        // Direct match on the taxon slug
                        $query->whereIn('slug', $taxonSlugs);
                    })->orWhereHas('parent', function ($parentQuery) use ($taxonSlugs) {
                        // Or the taxon's parent matches
                        $parentQuery->whereIn('slug', $taxonSlugs);
                    })->orWhereHas('parent.parent', function ($grandParentQuery) use ($taxonSlugs) {
                        // Or the taxon's grandparent matches (supports 3 levels deep)
                        $grandParentQuery->whereIn('slug', $taxonSlugs);
                    });
                });
            }

            $products = $query->paginate($perPage);

            return response()->json([
                'material' => new MaterialResource($material),
                'products' => [
                    'data' => ProductResource::collection($products),
                    'meta' => [
                        'current_page' => $products->currentPage(),
                        'from' => $products->firstItem(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'to' => $products->lastItem(),
                        'total' => $products->total(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching products',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    public function getPrinterProducts(Request $request)
    {
        $validated = $request->validate([
            'printer_id' => 'required|exists:posts,id',
            'product_type' => 'nullable|string|in:labels,ink',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $printer = Post::with('propertyValues.property')->find($validated['printer_id']);

        if (! $printer || $printer->post_type !== 'printer') {
            return response()->json([
                'message' => 'Printer not found',
            ], 404);
        }

        $productType = $validated['product_type'] ?? null;
        $perPage = $validated['per_page'] ?? 15;

        try {
            $query = $printer->products()
                ->with('propertyValues.property')
                ->with('activeWarrantyOptions')
                ->withCount('activeWarrantyOptions');

            // Apply product type filter (taxon-based) - same logic as SearchController
            $taxonSlugs = $this->getTaxonSlugsForProductType($productType);
            if (! empty($taxonSlugs)) {
                $query->whereHas('taxons', function ($q) use ($taxonSlugs) {
                    // Match products assigned to the taxon OR any of its ancestors
                    $q->where(function ($query) use ($taxonSlugs) {
                        // Direct match on the taxon slug
                        $query->whereIn('slug', $taxonSlugs);
                    })->orWhereHas('parent', function ($parentQuery) use ($taxonSlugs) {
                        // Or the taxon's parent matches
                        $parentQuery->whereIn('slug', $taxonSlugs);
                    })->orWhereHas('parent.parent', function ($grandParentQuery) use ($taxonSlugs) {
                        // Or the taxon's grandparent matches (supports 3 levels deep)
                        $grandParentQuery->whereIn('slug', $taxonSlugs);
                    });
                });
            }

            $products = $query->paginate($perPage);

            return response()->json([
                'printer' => new PrinterResource($printer),
                'products' => [
                    'data' => ProductResource::collection($products),
                    'meta' => [
                        'current_page' => $products->currentPage(),
                        'from' => $products->firstItem(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'to' => $products->lastItem(),
                        'total' => $products->total(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error matching products',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    private function getTaxonSlugsForProductType(?string $productType): array
    {
        return match ($productType) {
            self::PRODUCT_TYPE_LABELS => self::LABELS_TAXON_SLUGS,
            self::PRODUCT_TYPE_INK => [self::INK_TAXON_SLUG],
            default => [],
        };
    }

    public function getCompatibility(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'printer_id' => 'required|exists:posts,id',
        ]);

        $product = Product::query()->find($validated['product_id']);
        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        $printer = Post::printer()
            ->with('propertyValues.property')
            ->where('id', $validated['printer_id'])
            ->first();

        if (! $printer) {
            return response()->json([
                'message' => 'Printer not found',
            ], 404);
        }

        $isCompatible = $printer->products()
            ->whereKey($product->id)
            ->exists();

        return response()->json([
            'compatibility' => $isCompatible,
        ]);
    }

    /**
     * Get printers compatible with a specific product.
     *
     * This is the reverse of getPrinterProducts - it finds printers
     * that can use a specific product/label.
     *
     * @return JsonResponse
     */
    public function getProductPrinters(Request $request, ProductPrinterMatcher $matcher)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:published,draft',
        ]);

        $product = Product::with('propertyValues.property')->find($validated['product_id']);

        if (! $product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        $perPage = $validated['per_page'] ?? 15;
        $status = $validated['status'] ?? 'published';

        try {
            $query = $matcher->getMatchingPrinters($product)
                ->with(['translations', 'propertyValues.property'])
                ->where('status', $status)
                ->orderBy('title');

            $printers = $query->paginate($perPage);

            return response()->json([
                'product' => new ProductResource($product),
                'printers' => [
                    'data' => PrinterResource::collection($printers),
                    'meta' => [
                        'current_page' => $printers->currentPage(),
                        'from' => $printers->firstItem(),
                        'last_page' => $printers->lastPage(),
                        'per_page' => $printers->perPage(),
                        'to' => $printers->lastItem(),
                        'total' => $printers->total(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error matching printers',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}

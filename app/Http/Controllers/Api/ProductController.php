<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProductResource;
use App\Services\ProductCatalogService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
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
}

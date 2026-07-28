<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProductResource;
use App\Models\FavoriteProduct;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()->favoriteProducts()
            ->latest()
            ->get();

        $products = $favorites->map(function (FavoriteProduct $favorite) {
            $model = Product::with(['translations', 'taxons', 'metas'])->find($favorite->product_id);

            return $model;
        })->filter();

        return ProductResource::collection($products);
    }

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $this->resolveProduct($type, $id);

        $request->user()->favoriteProducts()->firstOrCreate([
            'product_id' => $id,
            'product_type' => $type,
        ]);

        return response()->json(['message' => 'Product added to favorites.'], 201);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $request->user()->favoriteProducts()
            ->where('product_id', $id)
            ->where('product_type', $type)
            ->delete();

        return response()->json(['message' => 'Product removed from favorites.']);
    }

    public function check(Request $request, string $type, int $id): JsonResponse
    {
        $exists = $request->user()->favoriteProducts()
            ->where('product_id', $id)
            ->where('product_type', $type)
            ->exists();

        return response()->json(['is_favorite' => $exists]);
    }

    protected function resolveProduct(string $type, int $id): Product
    {
        return Product::findOrFail($id);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProductResource;
use App\Models\PopularProduct;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PopularProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            PopularProduct::query()
                ->with(['product.metas', 'product.media', 'product.taxons', 'product.propertyValues.property'])
                ->orderBy('sort_order')
                ->get()
                ->pluck('product')
                ->filter()
        );
    }
}

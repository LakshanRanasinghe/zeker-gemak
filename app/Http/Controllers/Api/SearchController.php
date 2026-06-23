<?php

namespace App\Http\Controllers\Api;

use App\Explorer\CustomMultiMatch;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\GroupProductResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\GroupProduct;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
        ]);

        $query = $validated['query'];

        $products = Product::search('')
            ->must(new CustomMultiMatch($query, (new Product)->getSearchableFields()))
            ->query(fn ($builder) => $builder->with('activeWarrantyOptions')->withCount('activeWarrantyOptions'))
            ->take(15)
            ->get();

        $groupProducts = GroupProduct::search('')
            ->must(new CustomMultiMatch($query, (new GroupProduct)->getSearchableFields()))
            ->take(15)
            ->get();

        $data = $products->map(fn ($p) => new ProductResource($p))
            ->concat($groupProducts->map(fn ($gp) => new GroupProductResource($gp)))
            ->values();

        return response()->json(['data' => $data]);
    }
}

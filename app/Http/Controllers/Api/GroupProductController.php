<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\GroupProductResource;
use App\Models\GroupProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroupProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 15);

        $groupProducts = GroupProduct::query()
            ->with(['taxons', 'material.category'])
            ->withCount('items')
            ->paginate($perPage);

        return GroupProductResource::collection($groupProducts);
    }

    public function show(int $id): GroupProductResource
    {
        $groupProduct = GroupProduct::query()
            ->with($this->detailRelations())
            ->findOrFail($id);

        return new GroupProductResource($groupProduct);
    }

    public function showBySlug(string $slug): GroupProductResource
    {
        $groupProduct = GroupProduct::query()
            ->with($this->detailRelations())
            ->where('slug', $slug)
            ->firstOrFail();

        return new GroupProductResource($groupProduct);
    }

    protected function detailRelations(): array
    {
        return [
            'taxons',
            'material.category',
            'items.product' => function ($query) {
                $query->with(['translations', 'media']);
            },
            'translations',
            'media',
        ];
    }
}

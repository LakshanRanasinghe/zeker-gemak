<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TaxCategoryResource;
use App\Http\Resources\Api\TaxRateResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Taxes\Models\TaxRate;

class TaxController extends Controller
{
    public function categories(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'active_only' => ['nullable', 'boolean'],
        ]);

        $activeOnly = (bool) ($validated['active_only'] ?? true);

        $taxCategories = TaxCategory::query()
            ->when($activeOnly, fn ($query) => $query->actives())
            ->orderBy('name')
            ->get();

        return TaxCategoryResource::collection($taxCategories);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'active_only' => ['nullable', 'boolean'],
            'current_only' => ['nullable', 'boolean'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'tax_category_id' => ['nullable', 'integer', 'exists:tax_categories,id'],
        ]);

        $activeOnly = (bool) ($validated['active_only'] ?? true);
        $currentOnly = (bool) ($validated['current_only'] ?? true);

        $taxRates = TaxRate::query()
            ->with(['taxCategory', 'zone'])
            ->when($activeOnly, fn ($query) => $query->actives())
            ->when($activeOnly, fn ($query) => $query->whereHas('taxCategory', fn ($taxCategoryQuery) => $taxCategoryQuery->actives()))
            ->when(
                $validated['zone_id'] ?? null,
                fn ($query, int $zoneId) => $query->where(fn ($query) => $query
                    ->whereNull('zone_id')
                    ->orWhere('zone_id', $zoneId))
            )
            ->when(
                $validated['tax_category_id'] ?? null,
                fn ($query, int $taxCategoryId) => $query->where('tax_category_id', $taxCategoryId)
            )
            ->when($currentOnly, fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereNull('valid_from')
                    ->orWhereDate('valid_from', '<=', now()))
                ->where(fn ($query) => $query
                    ->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', now())))
            ->orderBy('name')
            ->get();

        return TaxRateResource::collection($taxRates);
    }
}

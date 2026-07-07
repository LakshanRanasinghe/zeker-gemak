<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ShippingMethodResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Vanilo\Shipment\Models\ShippingMethod;

class ShippingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'active_only' => ['nullable', 'boolean'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $activeOnly = (bool) ($validated['active_only'] ?? true);

        $shippingMethods = ShippingMethod::query()
            ->with(['carrier', 'zone'])
            ->when($activeOnly, fn ($query) => $query->actives())
            ->when(
                $validated['zone_id'] ?? null,
                fn ($query, int $zoneId) => $query->where(fn ($query) => $query
                    ->whereNull('zone_id')
                    ->orWhere('zone_id', $zoneId))
            )
            ->when($activeOnly, fn ($query) => $query->where(function ($query) {
                $query
                    ->whereNull('carrier_id')
                    ->orWhereHas('carrier', fn ($carrierQuery) => $carrierQuery->actives());
            }))
            ->orderBy('name')
            ->get();

        return ShippingMethodResource::collection($shippingMethods);
    }
}

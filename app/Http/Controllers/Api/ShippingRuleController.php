<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShippingRuleRequest;
use App\Http\Resources\Api\ShippingRuleResource;
use App\Models\CountryShippingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShippingRuleController extends Controller
{
    public function active(): AnonymousResourceCollection
    {
        return ShippingRuleResource::collection(
            CountryShippingRule::query()->active()->orderBy('country_name')->get()
        );
    }

    public function index(): AnonymousResourceCollection
    {
        return ShippingRuleResource::collection(
            CountryShippingRule::query()->orderBy('country_name')->get()
        );
    }

    public function store(ShippingRuleRequest $request): ShippingRuleResource
    {
        return new ShippingRuleResource(CountryShippingRule::query()->create($request->validated()));
    }

    public function show(CountryShippingRule $shippingRule): ShippingRuleResource
    {
        return new ShippingRuleResource($shippingRule);
    }

    public function update(ShippingRuleRequest $request, CountryShippingRule $shippingRule): ShippingRuleResource
    {
        $shippingRule->update($request->validated());

        return new ShippingRuleResource($shippingRule);
    }

    public function destroy(CountryShippingRule $shippingRule): JsonResponse
    {
        $shippingRule->delete();

        return response()->json(null, 204);
    }
}

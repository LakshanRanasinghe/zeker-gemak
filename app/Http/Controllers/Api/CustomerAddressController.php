<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerAddressRequest;
use App\Http\Requests\UpdateCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\User;
use Illuminate\Http\Request;
use Konekt\Address\Models\Address;

class CustomerAddressController extends Controller
{
    /**
     * List all addresses for the authenticated user.
     * GET /user/addresses?type=shipping
     */
    public function myAddresses(Request $request)
    {
        $query = $request->user()->addresses();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return CustomerAddressResource::collection($query->get());
    }

    /**
     * Create or update an address for the authenticated user.
     * POST /user/addresses
     */
    public function storeOrUpdateMyAddress(StoreCustomerAddressRequest $request)
    {
        $user = $request->user();
        $type = $request->type;
        $validated = $request->validated();

        $address = $user->addresses()->updateOrCreate(
            ['type' => $type],
            $validated
        );

        return new CustomerAddressResource($address);
    }

    /**
     * List all addresses for a customer, optionally filtered by type.
     * GET /customers/{id}/addresses?type=shipping
     */
    public function index($customerId)
    {
        $customer = User::findOrFail($customerId);

        $query = $customer->addresses();

        if (request()->has('type')) {
            $query->where('type', request('type'));
        }

        return CustomerAddressResource::collection($query->get());
    }

    /**
     * Add a new address to a customer.
     * Enforces max 1 billing address per customer.
     */
    public function store(StoreCustomerAddressRequest $request, $customerId)
    {
        $customer = User::findOrFail($customerId);

        if ($request->type === 'billing' && $customer->addresses()->where('type', 'billing')->exists()) {
            return response()->json([
                'message' => 'Customer already has a billing address. Please update the existing one.',
            ], 422);
        }

        $address = $customer->addresses()->create($request->validated());

        return new CustomerAddressResource($address);
    }

    /**
     * Update an address belonging to a customer.
     */
    public function update(UpdateCustomerAddressRequest $request, $customerId, $addressId)
    {
        $customer = User::findOrFail($customerId);

        $address = $customer->addresses()->findOrFail($addressId);

        $address->update($request->validated());

        return new CustomerAddressResource($address);
    }

    /**
     * Delete an address belonging to a customer.
     */
    public function destroy($customerId, $addressId)
    {
        $customer = User::findOrFail($customerId);

        $address = $customer->addresses()->findOrFail($addressId);

        $address->delete();

        return response()->json(null, 204);
    }
}

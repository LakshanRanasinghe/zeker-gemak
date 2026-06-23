<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    /**
     * Display a paginated listing of customers.
     */
    public function index(Request $request)
    {
        $query = User::with('addresses')->latest();

        // Optional search by name or email
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Optional filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $customers = $query->paginate($request->query('per_page', 20));

        return CustomerResource::collection($customers);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request)
    {
        $validated = $request->validated();

        $customer = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'type' => $validated['type'] ?? null,
        ]);

        return new CustomerResource($customer->load('addresses'));
    }

    /**
     * Display the specified customer.
     */
    public function show($customerId)
    {
        $customer = User::with('addresses')->findOrFail($customerId);

        return new CustomerResource($customer);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, $customerId)
    {
        $customer = User::findOrFail($customerId);

        $validated = $request->validated();

        $data = array_filter([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? null,
            'type' => $validated['type'] ?? null,
        ], fn ($v) => ! is_null($v));

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $customer->update($data);

        return new CustomerResource($customer->load('addresses'));
    }

    /**
     * Remove the specified customer.
     */
    public function destroy($customerId)
    {
        $customer = User::findOrFail($customerId);

        // Delete associated addresses first
        $customer->addresses()->delete();

        $customer->delete();

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Konekt\Address\Models\Address;
use Konekt\Address\Models\Country;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        $user->assignRole('customer');

        $billingAddress = [
            'type' => 'billing',
            'name' => ($validated['company'] ?? null) ?: $validated['name'],
            'company_name' => $validated['company'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['billing_email'] ?? $validated['email'],
            'country_id' => $validated['country_id'],
            'address' => $validated['street_address'],
            'postalcode' => $validated['postcode'],
            'city' => $validated['city'],
            'province_id' => $validated['province_id'] ?? $validated['state_id'] ?? null,
            'tax_nr' => $validated['vat_number'] ?? null,
            'registration_nr' => $validated['kvk_number'] ?? null,
            'model_type' => User::class,
            'model_id' => $user->id,
        ];

        Address::create($billingAddress);

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ],
            'access_token' => $token,
        ], 201);
    }

    public function registerData(Request $request)
    {
        return response()->json([
            'countries' => Country::with('provinces:id,name,country_id')->get(['id', 'name']),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::ResetLinkSent
            ? back()->with(['status' => __($status)])
            : back()->withErrors(['email' => __($status)]);
    }

    public function login(Request $request)
    {

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'nullable|boolean',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user,
            'access_token' => $token,
            'message' => 'Login successful',
        ], Response::HTTP_OK);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ], Response::HTTP_OK);
    }

    public function getOrders(Request $request)
    {
        return $request->user()->orders()->latest()->get();
    }

    public function getAddresses(Request $request)
    {
        return $request->user()->addresses()->get();
    }
}

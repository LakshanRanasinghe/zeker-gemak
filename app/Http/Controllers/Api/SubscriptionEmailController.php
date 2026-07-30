<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubscriptionEmailController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower((string) $request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('subscription_emails', 'email'),
            ],
        ]);

        $subscriptionEmail = SubscriptionEmail::create([
            'email' => $validated['email'],
        ]);

        return response()->json([
            'message' => 'Subscription email saved successfully.',
            'data' => [
                'id' => $subscriptionEmail->id,
                'email' => $subscriptionEmail->email,
            ],
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Look up a coupon by code and validate it.
     *
     * Optional query params for context-aware validation:
     *   ?cart_total=150.00  — validates minimum_spend / maximum_spend
     *   ?email=user@x.com   — validates allowed_emails
     */
    public function show(Request $request, string $code)
    {
        $request->validate([
            'cart_total' => 'nullable|numeric|min:0',
            'email' => 'nullable|email',
        ]);

        $coupon = Coupon::where('code', strtoupper($code))->firstOrFail();

        if ($coupon->isExpired()) {
            return response()->json(['message' => 'This coupon has expired.'], 422);
        }

        if ($coupon->isDepleted()) {
            return response()->json(['message' => 'This coupon has reached its usage limit.'], 422);
        }

        if ($request->filled('cart_total')) {
            $cartTotal = (float) $request->cart_total;

            if ($coupon->isBelowMinimumSpend($cartTotal)) {
                return response()->json([
                    'message' => "A minimum spend of {$coupon->minimum_spend} is required to use this coupon.",
                ], 422);
            }

            if ($coupon->isAboveMaximumSpend($cartTotal)) {
                return response()->json([
                    'message' => "A maximum spend of {$coupon->maximum_spend} is allowed for this coupon.",
                ], 422);
            }
        }

        if ($request->filled('email') && ! $coupon->isEmailAllowed($request->email)) {
            return response()->json(['message' => 'This coupon is not valid for your email address.'], 422);
        }

        return new CouponResource($coupon);
    }
}

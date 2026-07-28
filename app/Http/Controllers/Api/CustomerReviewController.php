<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerReviewRequest;
use App\Http\Resources\Api\CustomerReviewResource;
use App\Models\CustomerReview;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerReviewController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = CustomerReview::approved()->latest('reviewed_at');

        if ($request->filled('product_id')) {
            $type = $request->query('product_type', 'simple');
            $query->forProduct($type, (int) $request->query('product_id'));
        } elseif ($request->boolean('site_wide')) {
            $query->whereNull('product_id');
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (int) $request->query('min_rating'));
        }

        return CustomerReviewResource::collection($query->paginate($perPage));
    }

    public function store(StoreCustomerReviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['product_id'])) {
            $type = 'simple';
            $exists = Product::whereKey($data['product_id'])->exists();

            if (! $exists) {
                return response()->json([
                    'message' => 'Selected product was not found.',
                    'errors' => ['product_id' => ['Selected product was not found.']],
                ], 422);
            }

            $data['product_type'] = $type;
        } else {
            $data['product_id'] = null;
            $data['product_type'] = null;
        }

        $userId = $request->user()?->id;
        $ip = $request->ip();

        if ($this->isDuplicate($data['product_id'], $data['product_type'], $data['email'] ?? null, $userId, $ip)) {
            return response()->json([
                'message' => 'You have already submitted a review for this recently. Please wait before submitting another.',
                'errors' => ['comment' => ['Duplicate review detected within the last 24 hours.']],
            ], 422);
        }

        $data['user_id'] = $userId;
        $data['ip_address'] = $ip;
        $data['source'] = 'api';
        $data['status'] = 'pending';

        CustomerReview::create($data);

        return response()->json([
            'message' => 'Thank you. Your review has been submitted and is awaiting approval.',
        ], 201);
    }

    protected function isDuplicate(?int $productId, ?string $productType, ?string $email, ?int $userId, ?string $ip): bool
    {
        if (! $email && ! $userId && ! $ip) {
            return false;
        }

        $query = CustomerReview::query()
            ->where('created_at', '>=', now()->subDay())
            ->when($productId, fn ($q) => $q->where('product_id', $productId)->where('product_type', $productType),
                fn ($q) => $q->whereNull('product_id'));

        $query->where(function ($q) use ($email, $userId, $ip) {
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($ip) {
                $q->orWhere('ip_address', $ip);
            }
        });

        return $query->exists();
    }
}

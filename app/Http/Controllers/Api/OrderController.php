<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\GroupProduct;
use App\Models\Product;
use App\Models\ProductWarrantyOption;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mollie\Laravel\Facades\Mollie;
use Vanilo\Adjustments\Models\Adjustment;
use Vanilo\Adjustments\Models\AdjustmentType;
use Vanilo\Foundation\Models\Customer;
use Vanilo\Order\Contracts\OrderFactory;
use Vanilo\Order\Models\Order;
use Vanilo\Order\Models\OrderProxy;

class OrderController extends Controller
{
    /**
     * Display a listing of the orders.
     */
    public function index(Request $request)
    {
        return OrderResource::collection(
            $request->user()->orders()->with(['items', 'billpayer.address', 'shippingAddress'])->latest()->get()
        );
    }

    /**
     * Display orders for a specific user (Admin/System use).
     */
    public function userOrders($userId)
    {
        $orders = OrderProxy::where('user_id', $userId)
            ->with(['items', 'billpayer.address', 'shippingAddress'])
            ->latest()
            ->get();

        return OrderResource::collection($orders);
    }

    /**
     * Store a newly created order in storage.
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            $user = $request->user();
            Log::info('Order creation started', [
                'user_id' => $user?->id,
                'payload' => $request->all(),
            ]);

            $validated = $request->validated();

            // Safely identify the user ID for guest checkouts
            $userId = $user ? $user->id : ($validated['user_id'] ?? null);

            if ($userId === null && ! empty($validated['billing_email'])) {
                $user = User::firstOrCreate(
                    ['email' => $validated['billing_email']],
                    [
                        'phone' => $validated['billing_phone'] ?? null,
                        'password' => Hash::make(Str::random(12)),
                        'name' => trim(($validated['billing_firstname'] ?? '').' '.($validated['billing_lastname'] ?? '')),
                    ]
                );
                $userId = $user->id;
            }

            $billpayer = $this->buildBillpayerData($validated, $user);

            // If no shipping provided at all, copy billing address to shipping
            $hasShipping = ! empty($validated['shipping_address_id']) || ! empty($validated['shipping_address']);
            $shipping = $hasShipping ? $this->buildShippingAddressData($validated, $user) : $this->copyBillingToShipping($billpayer);

            $data = [
                'status' => $validated['status'],
                'language' => $validated['lang'] ?? app()->getLocale(),
                'notes' => $validated['notes'] ?? null,
                'user_id' => $userId,
                'billpayer' => $billpayer,
                'shippingAddress' => $shipping,
                'original_checkout_payload' => $validated,
            ];

            $items = [];
            $groupDiscounts = [];

            foreach ($validated['order_items'] as $item) {
                $isGroupProduct = (bool) ($item['is_group_product'] ?? false);

                if ($isGroupProduct) {
                    $groupProduct = GroupProduct::with('products')->find($item['product_id']);
                    if ($groupProduct) {
                        $childProducts = $groupProduct->products;
                        $groupDiscountPercentage = (float) ($groupProduct->discount ?? 0);
                        $groupQuantity = (int) ($item['quantity'] ?? 1);

                        foreach ($childProducts as $childProduct) {
                            $childQtyInGroup = max(1, $childProduct->pivot->quantity);

                            $items[] = [
                                'product_type' => 'product',
                                'product_id' => $childProduct->id,
                                'price' => (float) ($childProduct->price ?? 0),
                                'name' => $childProduct->name,
                                'quantity' => $groupQuantity * $childQtyInGroup,
                                'configuration' => $item['configuration'] ?? null,
                                'source_group_product_id' => $groupProduct->id,
                                'source_group_product_name' => $groupProduct->name ?: $groupProduct->title,
                                'source_group_product_sku' => $groupProduct->sku,
                            ];
                        }

                        if ($groupDiscountPercentage > 0) {
                            $groupBasePrice = (float) $groupProduct->base_price;
                            $discountAmountPerGroup = $groupBasePrice * ($groupDiscountPercentage / 100);
                            $totalDiscountForThisGroup = $discountAmountPerGroup * $groupQuantity;

                            $groupDiscounts[] = [
                                'title' => __(':name discount (:percentage%)', [
                                    'name' => $groupProduct->name,
                                    'percentage' => $groupDiscountPercentage,
                                ]),
                                'amount' => -$totalDiscountForThisGroup,
                            ];
                        }
                    }
                } else {
                    $product = Product::query()->find($item['product_id']);

                    $items[] = [
                        'product_type' => 'product',
                        'product_id' => $item['product_id'],
                        'price' => $item['price'],
                        'name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'configuration' => $item['configuration'] ?? null,
                    ];

                    if ($extendedWarrantyItem = $this->buildExtendedWarrantyItem($item, $product)) {
                        $items[] = $extendedWarrantyItem;
                    }
                }
            }

            DB::beginTransaction();

            $order = app(OrderFactory::class)->createFromDataArray($data, $items);

            if (! $order) {
                throw new \Exception('Order factory failed to create the order object.');
            }

            // Apply group discounts as adjustments
            foreach ($groupDiscounts as $discount) {
                Adjustment::create([
                    'type' => AdjustmentType::PROMOTION,
                    'adjustable_type' => $order->getMorphClass(),
                    'adjustable_id' => $order->id,
                    'title' => $discount['title'],
                    'amount' => (float) $discount['amount'],
                    'adjuster' => 'manual',
                ]);
            }

            if (! empty($validated['shipping_amount'])) {
                Adjustment::create([
                    'type' => AdjustmentType::SHIPPING,
                    'adjustable_type' => $order->getMorphClass(),
                    'adjustable_id' => $order->id,
                    'title' => __('Shipping'),
                    'amount' => (float) $validated['shipping_amount'],
                    'adjuster' => 'manual',
                ]);
            }

            if (! empty($validated['tax_amount'])) {
                Adjustment::create([
                    'type' => AdjustmentType::TAX,
                    'adjustable_type' => $order->getMorphClass(),
                    'adjustable_id' => $order->id,
                    'title' => __('Tax'),
                    'amount' => (float) $validated['tax_amount'],
                    'adjuster' => 'manual',
                ]);
            }

            if (! empty($validated['payment_fee'])) {
                Adjustment::create([
                    'type' => AdjustmentType::MISC,
                    'adjustable_type' => $order->getMorphClass(),
                    'adjustable_id' => $order->id,
                    'title' => __('Payment Fee'),
                    'amount' => (float) $validated['payment_fee'],
                    'adjuster' => 'manual',
                ]);
            }

            // Auto-save phone and addresses to profile if the user is logged in
            if ($user) {
                // Update user phone if it's not set
                if (empty($user->phone) && ! empty($billpayer['phone'])) {
                    $user->update(['phone' => $billpayer['phone']]);
                }

                // Save addresses to address book if they don't exist yet
                $this->autoSaveAddresses($user, $billpayer, $shipping);
            }

            // Use the frontend-calculated total (includes shipping + tax + payment fee adjustments)
            // Fallback to order->total() if not provided
            $mollieTotal = ! empty($validated['total'])
                ? number_format((float) $validated['total'], 2, '.', '')
                : number_format($order->total(), 2, '.', '');

            // Mollie requires at least €0.01
            if ((float) $mollieTotal < 0.01) {
                throw new \Exception('Order total is too low for payment processing (minimum €0.01).');
            }

            $paymentData = [
                'amount' => [
                    'currency' => 'EUR',
                    'value' => $mollieTotal,
                ],
                'description' => 'Order '.$order->number,
                'redirectUrl' => env('FRONTEND_URL', 'http://localhost:3000').'/thank-you?order_number='.$order->number,
                'metadata' => [
                    'order_id' => $order->id,
                ],
            ];

            if (! empty($validated['payment_method'])) {
                $paymentData['method'] = $validated['payment_method'];
            }

            $appUrl = config('app.url');
            if ($appUrl && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '.test') && ! str_contains($appUrl, '127.0.0.1')) {
                $paymentData['webhookUrl'] = $appUrl.'/api/webhooks/mollie';
            }

            if ($validated['payment_method'] === 'banktransfer') {
                $existingNotes = $order->notes ? $order->notes."\n" : '';
                $order->update(['notes' => $existingNotes.'Payment Method: Invoice']);

                DB::commit();

                return (new OrderResource(
                    OrderProxy::with(['items', 'billpayer.address', 'shippingAddress'])->findOrFail($order->id)
                ))->additional(['payment_url' => env('FRONTEND_URL', 'http://localhost:3000').'/thank-you?order_number='.$order->number]);
            }

            $payment = Mollie::api()->payments->create($paymentData);
            if (! $payment) {
                throw new \Exception('Mollie payment creation failed.');
            }

            $paymentUrl = $payment->getCheckoutUrl();

            // Save the Mollie payment ID in the order notes (appending if notes already exist)
            $existingNotes = $order->notes ? $order->notes."\n" : '';
            $order->update(['notes' => $existingNotes.'Mollie ID: '.($payment->id ?? 'unknown')]);

            DB::commit();

            return (new OrderResource(
                OrderProxy::with(['items', 'billpayer.address', 'shippingAddress'])->findOrFail($order->id)
            ))->additional(['payment_url' => $paymentUrl]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Order creation/payment failed: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create order or initialize payment.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified order by number.
     */
    public function showByNumber($number)
    {
        $order = OrderProxy::where('number', $number)->with(['items', 'billpayer.address', 'shippingAddress'])->firstOrFail();

        // If the order is still pending, check the actual status with Mollie
        // This is crucial for local development where webhooks don't work!
        if ($order->status->value() === 'pending' && ! empty($order->notes)) {
            try {
                // Extract Mollie ID from notes if it exists
                $mollieId = null;
                if (preg_match('/Mollie ID: (tr_[a-zA-Z0-9]+)/', $order->notes, $matches)) {
                    $mollieId = $matches[1];
                }

                if ($mollieId) {
                    $payment = Mollie::api()->payments->get($mollieId);
                    if ($payment->isPaid()) {
                        $order->update(['status' => 'processing']);
                        $order->refresh();
                    } elseif ($payment->isCanceled() || $payment->isFailed() || $payment->isExpired()) {
                        $order->update(['status' => 'cancelled']);
                        $order->refresh();
                    }
                }
            } catch (\Exception $e) {
                Log::error('Mollie status check failed for order '.$number.': '.$e->getMessage());
            }
        }

        return new OrderResource($order);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, $orderId)
    {
        $order = $request->user()->orders()->with(['items', 'billpayer.address', 'shippingAddress'])->findOrFail($orderId);

        return new OrderResource($order);
    }

    /**
     * Update the specified order in storage.
     */
    public function update(UpdateOrderRequest $request, $orderId)
    {
        $order = $request->user()->orders()->findOrFail($orderId);
        $validated = $request->validated();

        $order->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $order->notes,
            'user_id' => $validated['user_id'] ?? $order->user_id,
        ]);

        if ($order->billpayer && isset($validated['billing_address'])) {
            $order->billpayer->update([
                'is_organization' => $validated['billpayer_is_organization'] ?? $order->billpayer->is_organization,
                'company_name' => $validated['billing_company_name'] ?? $order->billpayer->company_name,
                'firstname' => $validated['billing_firstname'] ?? $order->billpayer->firstname,
                'lastname' => $validated['billing_lastname'] ?? $order->billpayer->lastname,
                'email' => $validated['billing_email'] ?? $order->billpayer->email,
                'phone' => $validated['billing_phone'] ?? $order->billpayer->phone,
                'tax_nr' => $validated['billing_tax_nr'] ?? $order->billpayer->tax_nr,
            ]);

            if ($order->billpayer->address) {
                $order->billpayer->address->update([
                    'address' => $validated['billing_address'],
                    'address2' => $validated['billing_address2'] ?? $order->billpayer->address->address2,
                    'city' => $validated['billing_city'] ?? $order->billpayer->address->city,
                    'postalcode' => $validated['billing_postalcode'] ?? $order->billpayer->address->postalcode,
                    'country_id' => $validated['billing_country_id'] ?? $order->billpayer->address->country_id,
                    'province_id' => $validated['billing_province_id'] ?? $order->billpayer->address->province_id,
                ]);
            }
        }

        if ($order->shippingAddress && isset($validated['shipping_address'])) {
            $order->shippingAddress->update([
                'name' => $validated['shipping_name'] ?? $order->shippingAddress->name,
                'firstname' => $validated['shipping_firstname'] ?? $order->shippingAddress->firstname,
                'lastname' => $validated['shipping_lastname'] ?? $order->shippingAddress->lastname,
                'address' => $validated['shipping_address'],
                'address2' => $validated['shipping_address2'] ?? $order->shippingAddress->address2,
                'city' => $validated['shipping_city'] ?? $order->shippingAddress->city,
                'postalcode' => $validated['shipping_postalcode'] ?? $order->shippingAddress->postalcode,
                'country_id' => $validated['shipping_country_id'] ?? $order->shippingAddress->country_id,
                'province_id' => $validated['shipping_province_id'] ?? $order->shippingAddress->province_id,
            ]);
        }

        if (! empty($validated['order_items'])) {
            $order->items()->delete();
            foreach ($validated['order_items'] as $item) {
                $order->items()->create([
                    'product_type' => 'product',
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'configuration' => $item['configuration'] ?? null,
                    'source_group_product_id' => $item['source_group_product_id'] ?? null,
                    'source_group_product_name' => $item['source_group_product_name'] ?? null,
                    'source_group_product_sku' => $item['source_group_product_sku'] ?? null,
                ]);
            }
        }

        return new OrderResource(
            Order::with(['items', 'billpayer.address', 'shippingAddress'])->findOrFail($order->id)
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function buildExtendedWarrantyItem(array $item, ?Product $product): ?array
    {
        $warranty = data_get($item, 'configuration.extended_warranty');
        $optionId = data_get($item, 'configuration.warranty_option_id') ?? data_get($warranty, 'option_id');

        if (! $optionId) {
            return null;
        }

        $option = ProductWarrantyOption::query()->find($optionId);

        if (! $option) {
            return null;
        }

        $quantity = (int) (data_get($warranty, 'quantity') ?? 1);
        $durationMonths = (int) (data_get($warranty, 'duration_months') ?? $option->duration_months);
        $sku = data_get($warranty, 'sku') ?: $this->warrantyCartSku($product, (int) $option->id, $durationMonths);

        return [
            'product_type' => 'product',
            'product_id' => $item['product_id'],
            'price' => (float) (data_get($warranty, 'price') ?? $option->price),
            'name' => data_get($warranty, 'name') ?: $option->name,
            'quantity' => max(1, $quantity),
            'configuration' => [
                'type' => 'extended_warranty',
                'warranty_option_id' => (int) $option->id,
                'sku' => $sku,
                'duration_months' => $durationMonths,
                'parent_product_id' => $item['product_id'],
                'parent_name' => data_get($warranty, 'parent_name') ?: ($product?->name ?? $item['name']),
                'parent_sku' => data_get($warranty, 'parent_sku') ?: $product?->sku,
            ],
        ];
    }

    private function warrantyCartSku(?Product $product, int $optionId, int $durationMonths): string
    {
        $baseSku = (string) ($product?->sku ?: $product?->article_number ?: 'product-'.$product?->getKey());

        return sprintf('%s-WAR-%dM-%d', Str::upper(Str::slug($baseSku, '-')), $durationMonths, $optionId);
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(Request $request, $orderId)
    {
        $orderClass = OrderProxy::modelClass();
        $order = $orderClass::query()
            ->with([
                'billpayer',
                'shippingAddress',
                'payments.history',
                'shipments',
                'items.shipments',
            ])
            ->whereKey($orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        DB::transaction(function () use ($order) {
            $billpayer = $order->billpayer;
            $billpayerId = $billpayer?->id;
            $billingAddressId = $billpayer?->address_id;
            $shippingAddressId = $order->shipping_address_id;
            $shipments = $order->shipments
                ->merge($order->items->flatMap(fn ($item) => $item->shipments))
                ->unique('id')
                ->values();
            $shipmentAddressIds = collect();

            $order->payments->each(function ($payment) {
                $payment->history()->delete();
                $payment->delete();
            });

            $order->adjustments()->clear();

            $order->items->each(function ($item) {
                $item->adjustments()->clear();
                $item->shipments()->detach();
            });

            $order->shipments()->detach();

            if ($shipments->isNotEmpty()) {
                $shipmentIds = $shipments->pluck('id')->unique()->values();
                $remainingShipmentIds = DB::table('shippables')
                    ->whereIn('shipment_id', $shipmentIds)
                    ->pluck('shipment_id')
                    ->unique();

                $orphanShipmentIds = $shipmentIds->diff($remainingShipmentIds)->values();

                if ($orphanShipmentIds->isNotEmpty()) {
                    $shipmentAddressIds = $shipments
                        ->filter(fn ($shipment) => $orphanShipmentIds->contains($shipment->getKey()))
                        ->pluck('address_id')
                        ->filter()
                        ->unique()
                        ->values();

                    DB::table('shipments')
                        ->whereIn('id', $orphanShipmentIds)
                        ->delete();
                }
            }

            $order->delete();

            if ($billpayerId && ! DB::table('orders')->where('billpayer_id', $billpayerId)->exists()) {
                $billpayer?->delete();
            } else {
                $billingAddressId = null;
            }

            collect([$billingAddressId, $shippingAddressId])
                ->merge($shipmentAddressIds)
                ->filter()
                ->unique()
                ->each(fn ($addressId) => $this->deleteAddressIfUnused((int) $addressId));
        });

        return response()->json(null, 204);
    }

    private function deleteAddressIfUnused(int $addressId): void
    {
        $isStillUsedByOrder = DB::table('orders')->where('shipping_address_id', $addressId)->exists();
        $isStillUsedByBillpayer = DB::table('billpayers')->where('address_id', $addressId)->exists();
        $isStillUsedByShipment = DB::table('shipments')->where('address_id', $addressId)->exists();

        if ($isStillUsedByOrder || $isStillUsedByBillpayer || $isStillUsedByShipment) {
            return;
        }

        DB::table('addresses')->where('id', $addressId)->delete();
    }

    /**
     * Build billpayer data array for the OrderFactory.
     * Uses a saved customer address (billing_address_id) if provided,
     * otherwise falls back to raw billing fields.
     */
    private function buildBillpayerData(array $validated, $user): array
    {
        $billpayer = [
            'is_organization' => $validated['billpayer_is_organization'] ?? false,
            'company_name' => $validated['billing_company_name'] ?? null,
            'firstname' => $validated['billing_firstname'] ?? null,
            'lastname' => $validated['billing_lastname'] ?? null,
            'email' => $validated['billing_email'] ?? null,
            'phone' => $validated['billing_phone'] ?? null,
            'tax_nr' => $validated['billing_tax_nr'] ?? null,
        ];

        if ($user && ! empty($validated['billing_address_id'])) {
            $address = $user->addresses()->where('type', 'billing')->findOrFail($validated['billing_address_id']);
            $billpayer['firstname'] = $billpayer['firstname'] ?? $address->firstname;
            $billpayer['lastname'] = $billpayer['lastname'] ?? $address->lastname;
            $billpayer['email'] = $billpayer['email'] ?? $address->email;
            $billpayer['phone'] = $billpayer['phone'] ?? $address->phone;
            $billpayer['address'] = [
                'address' => $address->address,
                'address2' => $address->address2,
                'city' => $address->city,
                'postalcode' => $address->postalcode,
                'country_id' => $address->country_id,
                'province_id' => $address->province_id,
            ];
        } else {
            $billpayer['address'] = [
                'address' => $validated['billing_address'],
                'address2' => $validated['billing_address2'] ?? null,
                'city' => $validated['billing_city'],
                'postalcode' => $validated['billing_postalcode'] ?? null,
                'country_id' => $validated['billing_country_id'] ?? 'US',
                'province_id' => $validated['billing_province_id'] ?? null,
            ];
        }

        return $billpayer;
    }

    /**
     * Copy billing address fields into a shipping address array.
     * Used when the customer provides no shipping address at checkout.
     */
    private function copyBillingToShipping(array $billpayer): array
    {
        $addr = $billpayer['address'];

        return [
            'name' => trim(($billpayer['firstname'] ?? '').' '.($billpayer['lastname'] ?? '')),
            'firstname' => $billpayer['firstname'] ?? null,
            'lastname' => $billpayer['lastname'] ?? null,
            'address' => $addr['address'],
            'address2' => $addr['address2'] ?? null,
            'city' => $addr['city'],
            'postalcode' => $addr['postalcode'] ?? null,
            'country_id' => $addr['country_id'],
            'province_id' => $addr['province_id'] ?? null,
        ];
    }

    /**
     * Save billing and shipping addresses to the customer's address book
     * when they have no saved addresses yet.
     */
    private function autoSaveAddresses($user, array $billpayer, array $shipping): void
    {
        // Check if billing address already exists
        $billingExists = $user->addresses()
            ->where('type', 'billing')
            ->where('address', $billpayer['address']['address'])
            ->where('city', $billpayer['address']['city'])
            ->exists();

        if (! $billingExists) {
            $user->addresses()->create([
                'type' => 'billing',
                'name' => trim(($billpayer['firstname'] ?? '').' '.($billpayer['lastname'] ?? '')),
                'firstname' => $billpayer['firstname'] ?? null,
                'lastname' => $billpayer['lastname'] ?? null,
                'company_name' => $billpayer['company_name'] ?? null,
                'email' => $billpayer['email'] ?? null,
                'phone' => $billpayer['phone'] ?? null,
                'address' => $billpayer['address']['address'],
                'address2' => $billpayer['address']['address2'] ?? null,
                'city' => $billpayer['address']['city'],
                'postalcode' => $billpayer['address']['postalcode'] ?? null,
                'country_id' => $billpayer['address']['country_id'],
                'province_id' => $billpayer['address']['province_id'] ?? null,
            ]);
        }

        // Check if shipping address already exists
        $shippingExists = $user->addresses()
            ->where('type', 'shipping')
            ->where('address', $shipping['address'])
            ->where('city', $shipping['city'])
            ->exists();

        if (! $shippingExists) {
            $user->addresses()->create([
                'type' => 'shipping',
                'name' => $shipping['name'] ?? trim(($shipping['firstname'] ?? '').' '.($shipping['lastname'] ?? '')),
                'firstname' => $shipping['firstname'] ?? null,
                'lastname' => $shipping['lastname'] ?? null,
                'address' => $shipping['address'],
                'address2' => $shipping['address2'] ?? null,
                'city' => $shipping['city'],
                'postalcode' => $shipping['postalcode'] ?? null,
                'country_id' => $shipping['country_id'],
                'province_id' => $shipping['province_id'] ?? null,
            ]);
        }
    }

    /**
     * Build shipping address data array for the OrderFactory.
     * Uses a saved customer address (shipping_address_id) if provided,
     * otherwise falls back to raw shipping fields.
     */
    private function buildShippingAddressData(array $validated, $user): array
    {
        if ($user && ! empty($validated['shipping_address_id'])) {
            $address = $user->addresses()->where('type', 'shipping')->findOrFail($validated['shipping_address_id']);

            return [
                'name' => $address->name ?? trim(($address->firstname ?? '').' '.($address->lastname ?? '')),
                'firstname' => $address->firstname,
                'lastname' => $address->lastname,
                'address' => $address->address,
                'address2' => $address->address2,
                'city' => $address->city,
                'postalcode' => $address->postalcode,
                'country_id' => $address->country_id,
                'province_id' => $address->province_id,
            ];
        }

        return [
            'name' => $validated['shipping_name'],
            'firstname' => $validated['shipping_firstname'] ?? null,
            'lastname' => $validated['shipping_lastname'] ?? null,
            'address' => $validated['shipping_address'],
            'address2' => $validated['shipping_address2'] ?? null,
            'city' => $validated['shipping_city'],
            'postalcode' => $validated['shipping_postalcode'] ?? null,
            'country_id' => $validated['shipping_country_id'] ?? 'US',
            'province_id' => $validated['shipping_province_id'] ?? null,
        ];
    }

    /**
     * Handle Mollie payment webhook.
     */
    public function webhook(Request $request)
    {
        if (! $request->has('id')) {
            return response()->json(['message' => 'No payment ID provided'], 400);
        }

        try {
            $payment = Mollie::api()->payments->get($request->id);
            $orderId = $payment->metadata->order_id ?? null;

            if ($orderId) {
                $order = OrderProxy::find($orderId);
                if ($order) {
                    if ($payment->isPaid() && ! $payment->hasRefunds() && ! $payment->hasChargebacks()) {
                        $order->update(['status' => 'processing']);
                    } elseif ($payment->isCanceled()) {
                        $order->update(['status' => 'cancelled']);
                    } elseif ($payment->isFailed()) {
                        $order->update(['status' => 'cancelled']);
                    } elseif ($payment->isExpired()) {
                        $order->update(['status' => 'cancelled']);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Mollie webhook failed: '.$e->getMessage());
        }

        return response()->json(null, 204);
    }
}

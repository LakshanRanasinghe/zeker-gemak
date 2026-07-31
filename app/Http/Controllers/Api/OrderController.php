<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteCheckoutRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWarrantyOption;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mollie\Laravel\Facades\Mollie;
use Vanilo\Foundation\Models\Customer;
use Vanilo\Order\Contracts\OrderFactory;
use Vanilo\Order\Models\OrderProxy;

class OrderController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

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
    public function quote(QuoteCheckoutRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->checkout->calculate($request->validated()),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['shipping_country_id'] = $payload['shipping_country_id'] ?? $payload['billing_country_id'] ?? 'NL';
        $payload['_user_id'] = $request->user()?->id;
        $calculatedAmounts = $this->checkout->calculate($payload);

        if ((float) $calculatedAmounts['grand_total'] < 0.01) {
            throw ValidationException::withMessages([
                'order_items' => [__('The order total must be at least €0.01.')],
            ]);
        }

        $checkoutSession = CheckoutSession::query()->create([
            'reference' => (string) Str::uuid(),
            'payload' => $payload,
            'calculated_amounts' => $calculatedAmounts,
        ]);

        try {
            $frontendUrl = rtrim((string) (config('app.frontend_url') ?: 'http://localhost:3000'), '/');
            $paymentData = [
                'amount' => [
                    'currency' => 'EUR',
                    'value' => $calculatedAmounts['grand_total'],
                ],
                'description' => __('Zeker Gemak checkout :reference', ['reference' => $checkoutSession->reference]),
                'redirectUrl' => $frontendUrl.'/thank-you?checkout_reference='.$checkoutSession->reference,
                'metadata' => ['checkout_reference' => $checkoutSession->reference],
                'method' => $payload['payment_method'],
            ];
            $appUrl = rtrim((string) config('app.url'), '/');

            if ($appUrl !== '' && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '.test') && ! str_contains($appUrl, '127.0.0.1')) {
                $paymentData['webhookUrl'] = $appUrl.'/api/webhooks/mollie';
            }

            $payment = Mollie::api()->payments->create($paymentData);
            $checkoutSession->update([
                'mollie_payment_id' => $payment->id,
                'payment_status' => $payment->status ?? 'open',
            ]);

            return response()->json([
                'checkout_reference' => $checkoutSession->reference,
                'status' => $checkoutSession->payment_status,
                'payment_url' => $payment->getCheckoutUrl(),
                'calculated_amounts' => $calculatedAmounts,
            ]);
        } catch (\Throwable $exception) {
            $checkoutSession->delete();
            report($exception);

            return response()->json(['message' => __('Payment could not be initialized.')], 422);
        }
    }

    /**
     * Display the specified order by number.
     */
    public function showByNumber(string $number): JsonResponse|OrderResource
    {
        $checkoutSession = CheckoutSession::query()->where('reference', $number)->first();

        if (! $checkoutSession) {
            return new OrderResource(
                OrderProxy::query()
                    ->where('number', $number)
                    ->with(['items.product.media', 'billpayer.address', 'shippingAddress'])
                    ->firstOrFail()
            );
        }

        if ($checkoutSession->mollie_payment_id) {
            $payment = Mollie::api()->payments->get($checkoutSession->mollie_payment_id);
            $checkoutSession = $this->processPayment($checkoutSession, $payment);
        }

        if ($checkoutSession->order) {
            $checkoutSession->order->loadMissing(['items.product.media', 'billpayer.address', 'shippingAddress']);
        }

        return response()->json([
            'status' => $checkoutSession->payment_status,
            'checkout_reference' => $checkoutSession->reference,
            'calculated_amounts' => $checkoutSession->calculated_amounts,
            'data' => $checkoutSession->order
                ? (new OrderResource($checkoutSession->order))->resolve()
                : null,
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, $orderId)
    {
        $order = $request->user()->orders()->with(['items.product.media', 'billpayer.address', 'shippingAddress'])->findOrFail($orderId);

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
    public function webhook(Request $request): JsonResponse
    {
        if (! $request->has('id')) {
            return response()->json(['message' => 'No payment ID provided'], 400);
        }

        try {
            $payment = Mollie::api()->payments->get($request->id);
            $reference = data_get($payment, 'metadata.checkout_reference');
            $checkoutSession = CheckoutSession::query()
                ->when($reference, fn ($query) => $query->where('reference', $reference))
                ->when(! $reference, fn ($query) => $query->where('mollie_payment_id', $request->id))
                ->firstOrFail();

            if ($checkoutSession->mollie_payment_id !== $payment->id) {
                return response()->json(['message' => 'Payment does not match checkout session.'], 422);
            }

            $this->processPayment($checkoutSession, $payment);
        } catch (\Exception $e) {
            Log::error('Mollie webhook failed: '.$e->getMessage());

            return response()->json(['message' => 'Payment status could not be processed.'], 422);
        }

        return response()->json(null, 204);
    }

    private function processPayment(CheckoutSession $checkoutSession, object $payment): CheckoutSession
    {
        $paymentStatus = match (true) {
            $payment->isPaid() && ! $payment->hasRefunds() && ! $payment->hasChargebacks() => 'paid',
            $payment->isCanceled() => 'canceled',
            $payment->isFailed() => 'failed',
            $payment->isExpired() => 'expired',
            default => in_array($payment->status ?? null, ['open', 'pending', 'authorized'], true)
                ? $payment->status
                : 'pending',
        };

        return DB::transaction(function () use ($checkoutSession, $paymentStatus): CheckoutSession {
            $lockedSession = CheckoutSession::query()->lockForUpdate()->findOrFail($checkoutSession->id);

            if ($paymentStatus !== 'paid' || $lockedSession->order_id) {
                $lockedSession->update(['payment_status' => $paymentStatus]);

                return $lockedSession->fresh('order');
            }

            $lockedSession->update(['payment_status' => 'paid']);
            $order = $this->checkout->createOrder($lockedSession);
            $lockedSession->update([
                'order_id' => $order->id,
                'processed_at' => now(),
            ]);

            return $lockedSession->fresh('order');
        });
    }
}

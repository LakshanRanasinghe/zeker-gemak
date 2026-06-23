<?php

use App\Models\GroupProduct;
use App\Models\User;
use Flux\Flux;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Konekt\Address\Models\Country;
use Livewire\Component;
use Vanilo\Foundation\Models\Product;
use Vanilo\Order\Contracts\OrderFactory;
use Vanilo\Order\Models\OrderProxy as Order;
use Vanilo\Order\Models\OrderStatusProxy as OrderStatus;

new class extends Component
{
    public ?int $order_id = null;

    // Core
    public $user_id = null;

    public $status = 'pending';

    public $notes = '';

    public $shipping_amount = 0;

    public $tax_amount = 0;

    public $discount_amount = 0;

    public $discount_title = '';

    public $debitor_no = '';

    // Billpayer
    public $billing_company_name = '';

    public $billing_firstname = '';

    public $billing_lastname = '';

    public $billing_email = '';

    public $billing_phone = '';

    public $billing_address = '';

    public $billing_city = '';

    public $billing_postalcode = '';

    public $billing_country_id = 'US';

    // Shipping
    public $shipping_name = '';

    public $shipping_address = '';

    public $shipping_city = '';

    public $shipping_postalcode = '';

    public $shipping_country_id = 'US';

    // Items
    public $order_items = [];

    // Search
    public $product_search = '';

    public $search_results = [];

    // Edit states
    public $editing_billing_address = true;

    public $editing_shipping_address = true;

    public function mount(Request $request, $order = null)
    {
        if ($order) {
            $this->editing_billing_address = false;
            $this->editing_shipping_address = false;

            $this->order_id = is_numeric($order) ? (int) $order : $order->id;
            $model = is_numeric($order) ? Order::with(['items.sourceGroupProduct', 'billpayer', 'shippingAddress'])->findOrFail($order) : clone clone $order;
            $model->loadMissing(['items.sourceGroupProduct', 'billpayer', 'shippingAddress']);

            $this->user_id = $model->user_id;
            $this->status = $model->status->value();
            $this->notes = $model->notes;
            $this->shipping_amount = (float) $model->adjustmentsRelation()->where('type', 'shipping')->sum('amount');
            $this->tax_amount = (float) $model->adjustmentsRelation()->where('type', 'tax')->sum('amount');
            $this->discount_amount = (float) $model->adjustmentsRelation()->where('type', 'promotion')->sum('amount');
            $this->discount_title = $model->adjustmentsRelation()->where('type', 'promotion')->first()?->title ?? __('Discount');

            $this->debitor_no = $model->user?->debitor_no ?? '';

            if ($model->billpayer) {
                $this->billing_company_name = $model->billpayer->company_name;
                $this->billing_firstname = $model->billpayer->firstname;
                $this->billing_lastname = $model->billpayer->lastname;
                $this->billing_email = $model->billpayer->email;
                $this->billing_phone = $model->billpayer->phone;

                $billingAddress = $model->billpayer->address;
                if ($billingAddress) {
                    $this->billing_address = $billingAddress->address;
                    $this->billing_city = $billingAddress->city;
                    $this->billing_postalcode = $billingAddress->postalcode;
                    $this->billing_country_id = $billingAddress->country_id;
                }
            }

            if ($model->shippingAddress) {
                $this->shipping_name = $model->shippingAddress->name;
                $this->shipping_address = $model->shippingAddress->address;
                $this->shipping_city = $model->shippingAddress->city;
                $this->shipping_postalcode = $model->shippingAddress->postalcode;
                $this->shipping_country_id = $model->shippingAddress->country_id;
            }

            foreach ($model->items as $item) {
                $sourceGroupProductDisplayName = $item->source_group_product_name;
                if (! $sourceGroupProductDisplayName && $item->source_group_product_id) {
                    $sourceGroupProduct = GroupProduct::query()->find($item->source_group_product_id);
                    $sourceGroupProductDisplayName = $sourceGroupProduct?->name ?: $sourceGroupProduct?->title;
                }

                $this->order_items[] = [
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'name' => $item->name,
                    'source_group_product_id' => $item->source_group_product_id,
                    'source_group_product_name' => $item->source_group_product_name,
                    'source_group_product_display_name' => $sourceGroupProductDisplayName,
                    'source_group_product_sku' => $item->source_group_product_sku,
                ];
            }
        }
    }

    public function updatedProductSearch()
    {
        if (strlen($this->product_search) > 1) {
            $products = Product::where('name', 'like', '%'.$this->product_search.'%')
                ->orWhere('sku', 'like', '%'.$this->product_search.'%')
                ->take(5)
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'price' => (float) $p->price,
                        'is_group' => false,
                    ];
                });

            $groups = GroupProduct::where('name', 'like', '%'.$this->product_search.'%')
                ->orWhere('sku', 'like', '%'.$this->product_search.'%')
                ->take(5)
                ->get()
                ->map(function ($g) {
                    return [
                        'id' => $g->id,
                        'name' => '[Group] '.$g->name,
                        'sku' => $g->sku,
                        'price' => (float) $g->price,
                        'is_group' => true,
                    ];
                });

            $this->search_results = $products->concat($groups)->take(10)->toArray();
        } else {
            $this->search_results = [];
        }
    }

    public function addProduct($productId)
    {
        $productData = collect($this->search_results)->firstWhere('id', $productId);
        if (! $productData) {
            return;
        }

        if ($productData['is_group']) {
            $groupProduct = GroupProduct::with('products')->find($productId);
            if ($groupProduct) {
                foreach ($groupProduct->products as $child) {
                    $qtyInGroup = max(1, $child->pivot->quantity);

                    $this->order_items[] = [
                        'product_id' => $child->id,
                        'quantity' => $qtyInGroup,
                        'price' => (float) $child->price,
                        'name' => $child->name,
                        'source_group_product_id' => $groupProduct->id,
                        'source_group_product_name' => $groupProduct->name ?: $groupProduct->title,
                        'source_group_product_display_name' => $groupProduct->name ?: $groupProduct->title,
                        'source_group_product_sku' => $groupProduct->sku,
                    ];
                }

                $discount = (float) ($groupProduct->final_price - $groupProduct->base_price);
                $this->discount_amount += $discount;
                $this->discount_title = $groupProduct->name.' '.__('discount');
            }
        } else {
            $this->order_items[] = [
                'product_id' => $productData['id'],
                'quantity' => 1,
                'price' => $productData['price'],
                'name' => $productData['name'],
                'source_group_product_id' => null,
                'source_group_product_name' => null,
                'source_group_product_display_name' => null,
                'source_group_product_sku' => null,
            ];
        }

        $this->product_search = '';
        $this->search_results = [];
    }

    public function removeProduct($index)
    {
        unset($this->order_items[$index]);
        $this->order_items = array_values($this->order_items);
    }

    public function updatedUserId($value)
    {
        if (! $value) {
            $this->debitor_no = '';

            return;
        }

        $user = User::with('addresses')->find($value);
        if (! $user) {
            return;
        }

        $this->debitor_no = $user->debitor_no ?? '';

        // Populate Billing Info
        $billingAddress = $user->addresses->where('type', 'billing')->first()
            ?: $user->addresses->where('type', 'business')->first()
            ?: $user->addresses->first();

        if ($billingAddress) {
            $this->billing_firstname = $billingAddress->firstname;
            $this->billing_lastname = $billingAddress->lastname;
            $this->billing_company_name = $billingAddress->company_name;
            $this->billing_email = $billingAddress->email ?: $user->email;
            $this->billing_phone = $billingAddress->phone ?: $user->phone;
            $this->billing_address = $billingAddress->address;
            $this->billing_city = $billingAddress->city;
            $this->billing_postalcode = $billingAddress->postalcode;
            $this->billing_country_id = $billingAddress->country_id;
        } else {
            $nameParts = explode(' ', $user->name, 2);
            $this->billing_firstname = $nameParts[0] ?? '';
            $this->billing_lastname = $nameParts[1] ?? '';
            $this->billing_email = $user->email;
            $this->billing_phone = $user->phone;
        }

        // Populate Shipping Info
        $shippingAddress = $user->addresses->where('type', 'shipping')->first()
            ?: $user->addresses->where('type', 'residential')->first()
            ?: $user->addresses->first();

        if ($shippingAddress) {
            $this->shipping_name = $shippingAddress->name ?: $shippingAddress->company_name;
            if (empty($this->shipping_name)) {
                $this->shipping_name = ($shippingAddress->firstname ?? '').' '.($shippingAddress->lastname ?? '');
            }
            $this->shipping_address = $shippingAddress->address;
            $this->shipping_city = $shippingAddress->city;
            $this->shipping_postalcode = $shippingAddress->postalcode;
            $this->shipping_country_id = $shippingAddress->country_id;
        } else {
            $this->shipping_name = $user->name;
        }

        // Close editing mode to show the summary view if addresses were found
        $this->editing_billing_address = false;
        $this->editing_shipping_address = false;
    }

    public function getUserOptionsProperty()
    {
        return User::select('id', 'name', 'email')->get()->map(fn ($u) => ['value' => $u->id, 'label' => $u->name.' ('.$u->email.')'])->toArray();
    }

    public function getCountryOptionsProperty()
    {
        return Country::all()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->toArray();
    }

    public function getOrderStatusOptionsProperty()
    {
        return collect(OrderStatus::choices())->map(fn ($label, $value) => ['value' => $value, 'label' => __($label)])->toArray();
    }

    public function save()
    {
        try {
            $this->validate([
                'status' => 'required',
                'order_items' => 'required|array|min:1',
                'order_items.*.quantity' => 'required|integer|min:1',
                'order_items.*.price' => 'required|numeric|min:0',
                'shipping_amount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'discount_amount' => 'nullable|numeric',
                'billing_firstname' => 'required_without:billing_company_name',
                'billing_email' => 'required|email',
                'billing_phone' => 'nullable|string|max:20',
                'billing_address' => 'required',
                'billing_postalcode' => 'nullable|string|max:12',
                'billing_city' => 'required',
                'shipping_name' => 'required',
                'shipping_address' => 'required',
                'shipping_postalcode' => 'nullable|string|max:12',
                'shipping_city' => 'required',
                'debitor_no' => 'nullable',
            ], [
                'required' => __('The :attribute field is required.'),
                'array' => __('The :attribute field must be an array.'),
                'integer' => __('The :attribute field must be an integer.'),
                'numeric' => __('The :attribute field must be a number.'),
                'min.array' => __('The :attribute field must have at least :min items.'),
                'min.numeric' => __('The :attribute field must be at least :min.'),
                'max.string' => __('The :attribute field must not be greater than :max characters.'),
                'billing_firstname.required_without' => __('Required.'),
                'billing_email.required' => __('Required.'),
                'billing_email.email' => __('Invalid email.'),
                'billing_address.required' => __('Required.'),
                'billing_city.required' => __('Required.'),
                'billing_postalcode.max' => __('Max 12 chars.'),
                'shipping_name.required' => __('Required.'),
                'shipping_address.required' => __('Required.'),
                'shipping_city.required' => __('Required.'),
                'shipping_postalcode.max' => __('Max 12 chars.'),
            ], [
                'status' => __('Status'),
                'order_items' => __('Order Items'),
                'order_items.*.quantity' => __('Qty'),
                'order_items.*.price' => __('Price'),
                'billing_firstname' => __('First Name'),
                'billing_email' => __('Email'),
                'billing_phone' => __('Phone'),
                'billing_address' => __('Street Address'),
                'billing_city' => __('City'),
                'billing_postalcode' => __('Postal Code'),
                'shipping_name' => __('Recipient Name'),
                'shipping_address' => __('Street Address'),
                'shipping_city' => __('City'),
                'shipping_postalcode' => __('Postal Code'),
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('scroll-to-error');
            throw $e;
        }

        if ($this->order_id) {
            $order = Order::findOrFail($this->order_id);
            $order->update([
                'status' => $this->status,
                'notes' => $this->notes,
                'user_id' => $this->user_id ?: null,
            ]);

            if (! empty($this->debitor_no)) {
                $orderUser = User::find($this->user_id);
                $orderUser?->update(['debitor_no' => $this->debitor_no]);
            }

            if ($order->billpayer) {
                $order->billpayer->update([
                    'company_name' => $this->billing_company_name,
                    'firstname' => $this->billing_firstname,
                    'lastname' => $this->billing_lastname,
                    'email' => $this->billing_email,
                    'phone' => $this->billing_phone,
                ]);
                if ($order->billpayer->address) {
                    $order->billpayer->address->update([
                        'address' => $this->billing_address,
                        'city' => $this->billing_city,
                        'postalcode' => $this->billing_postalcode,
                        'country_id' => $this->billing_country_id,
                    ]);
                }
            }

            if ($order->shippingAddress) {
                $order->shippingAddress->update([
                    'name' => $this->shipping_name,
                    'address' => $this->shipping_address,
                    'city' => $this->shipping_city,
                    'postalcode' => $this->shipping_postalcode,
                    'country_id' => $this->shipping_country_id,
                ]);
            }

            $order->items()->delete();
            foreach ($this->order_items as $item) {
                $order->items()->create([
                    'product_type' => 'product',
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'source_group_product_id' => $item['source_group_product_id'] ?? null,
                    'source_group_product_name' => $item['source_group_product_name'] ?? null,
                    'source_group_product_sku' => $item['source_group_product_sku'] ?? null,
                ]);
            }

            // Update Adjustments
            $order->adjustmentsRelation()->whereIn('type', ['shipping', 'tax', 'promotion'])->delete();
            if ($this->shipping_amount > 0) {
                $order->adjustmentsRelation()->create([
                    'type' => 'shipping',
                    'amount' => (float) $this->shipping_amount,
                    'title' => __('Shipping'),
                    'adjuster' => 'manual',
                ]);
            }
            if ($this->tax_amount > 0) {
                $order->adjustmentsRelation()->create([
                    'type' => 'tax',
                    'amount' => (float) $this->tax_amount,
                    'title' => __('Tax'),
                    'adjuster' => 'manual',
                ]);
            }
            if ($this->discount_amount != 0) {
                $order->adjustmentsRelation()->create([
                    'type' => 'promotion',
                    'amount' => (float) $this->discount_amount,
                    'title' => $this->discount_title ?: __('Discount'),
                    'adjuster' => 'manual',
                ]);
            }

            Flux::toast(__('Order updated successfully.'), variant: 'success');
        } else {
            $factory = app(OrderFactory::class);

            $data = [
                'status' => $this->status,
                'notes' => $this->notes,
                'user_id' => $this->user_id ?: null,
                'billpayer' => [
                    'company_name' => $this->billing_company_name,
                    'firstname' => $this->billing_firstname,
                    'lastname' => $this->billing_lastname,
                    'email' => $this->billing_email,
                    'phone' => $this->billing_phone,
                    'address' => [
                        'address' => $this->billing_address,
                        'city' => $this->billing_city,
                        'postalcode' => $this->billing_postalcode,
                        'country_id' => $this->billing_country_id,
                    ],
                ],
                'shippingAddress' => [
                    'name' => $this->shipping_name,
                    'address' => $this->shipping_address,
                    'city' => $this->shipping_city,
                    'postalcode' => $this->shipping_postalcode,
                    'country_id' => $this->shipping_country_id,
                ],
            ];

            $items = [];
            foreach ($this->order_items as $item) {
                $items[] = [
                    'product_type' => 'product',
                    'product_id' => $item['product_id'],
                    'price' => $item['price'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'source_group_product_id' => $item['source_group_product_id'] ?? null,
                    'source_group_product_name' => $item['source_group_product_name'] ?? null,
                    'source_group_product_sku' => $item['source_group_product_sku'] ?? null,
                ];
            }

            $order = $factory->createFromDataArray($data, $items);

            if (! empty($this->debitor_no)) {
                $orderUser = $this->user_id ? User::find($this->user_id) : User::where('email', $this->billing_email)->first();
                $orderUser?->update(['debitor_no' => $this->debitor_no]);
            }

            if ($this->shipping_amount > 0) {
                $order->adjustmentsRelation()->create([
                    'type' => 'shipping',
                    'amount' => (float) $this->shipping_amount,
                    'title' => __('Shipping'),
                    'adjuster' => 'manual',
                ]);
            }
            if ($this->tax_amount > 0) {
                $order->adjustmentsRelation()->create([
                    'type' => 'tax',
                    'amount' => (float) $this->tax_amount,
                    'title' => __('Tax'),
                    'adjuster' => 'manual',
                ]);
            }
            if ($this->discount_amount != 0) {
                $order->adjustmentsRelation()->create([
                    'type' => 'promotion',
                    'amount' => (float) $this->discount_amount,
                    'title' => $this->discount_title ?: __('Discount'),
                    'adjuster' => 'manual',
                ]);
            }

            Flux::toast(__('Order created successfully.'), variant: 'success');
        }

        $this->redirect(route('orders.index'), navigate: true);
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $order_id ? __('Edit Order') : __('Create Order') }}</flux:heading>
                <flux:subheading>{{ __('Manage order details, customer addresses, and line items.') }}</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('orders.index') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Order') }}</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">
            <div class="md:col-span-2 space-y-6">
                <!-- Addresses (Side by side) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Billing Address -->
                    <flux:card class="space-y-6">
                        <div class="flex items-center justify-between">
                            <flux:heading size="lg">{{ __('Billing Address') }}</flux:heading>
                            @if(!$editing_billing_address)
                                <flux:button size="sm" variant="subtle" icon="pencil"
                                    wire:click="$set('editing_billing_address', true)" />
                            @endif
                        </div>

                        @if($editing_billing_address)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                                <flux:input wire:model="billing_firstname" label="{{ __('First Name') }}"
                                    placeholder="{{ __('John') }}" />
                                <flux:input wire:model="billing_lastname" label="{{ __('Last Name') }}"
                                    placeholder="{{ __('Doe') }}" />
                            </div>

                            <div class="">
                                <flux:input wire:model="billing_company_name" label="{{ __('Company Name') }}"
                                    placeholder="{{ __('Acme Corp') }}" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                                <flux:input wire:model="billing_email" label="{{ __('Email') }}" type="email"
                                    placeholder="{{ __('john@example.com') }}" />
                                <flux:input wire:model="billing_phone" label="{{ __('Phone') }}" type="tel"
                                    placeholder="{{ __('0612345678') }}" />
                            </div>

                            <flux:input wire:model="billing_address" label="{{ __('Street Address') }}"
                                placeholder="{{ __('123 Main St') }}" />

                            <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_11rem] gap-6 md:items-start">
                                <flux:input wire:model="billing_city" label="{{ __('City') }}"
                                    placeholder="{{ __('Metropolis') }}" />
                                <div class="w-full md:justify-self-end">
                                    <flux:input wire:model="billing_postalcode" label="{{ __('Postal Code') }}"
                                        placeholder="10001" maxlength="12" />
                                </div>
                            </div>
                            <flux:select wire:model="billing_country_id" label="{{ __('Country') }}" variant="listbox"
                                searchable>
                                @foreach($this->countryOptions as $option)
                                    <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="debitor_no" type="text" label="{{ __('Debitor Number') }}" />

                        @else
                            <div class="text-sm space-y-1 text-zinc-600 dark:text-zinc-300">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $billing_company_name }}</div>
                                <div>{{ $billing_firstname }} {{ $billing_lastname }}</div>
                                @if($billing_email)
                                <div>{{ $billing_email }}</div>@endif
                                @if($billing_phone)
                                <div>{{ $billing_phone }}</div>@endif
                                <div>{{ $billing_address }}</div>
                                <div>{{ $billing_city }}, {{ $billing_postalcode }}</div>
                                <div>
                                    {{ collect($this->countryOptions)->firstWhere('value', $billing_country_id)['label'] ?? $billing_country_id }}
                                </div>
                                <div>{{ $this->debitor_no }}</div>
                            </div>
                        @endif

                    </flux:card>

                    <!-- Shipping Address -->
                    <flux:card class="space-y-6">
                        <div class="flex items-center justify-between">
                            <flux:heading size="lg">{{ __('Shipping Address') }}</flux:heading>
                            @if(!$editing_shipping_address)
                                <flux:button size="sm" variant="subtle" icon="pencil"
                                    wire:click="$set('editing_shipping_address', true)" />
                            @endif
                        </div>

                        @if($editing_shipping_address)
                            <flux:input wire:model="shipping_name" label="{{ __('Company Name') }}"
                                placeholder="{{ __('Acme Corp') }}" />
                            <flux:input wire:model="shipping_address" label="{{ __('Street Address') }}"
                                placeholder="{{ __('123 Main St') }}" />

                            <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_11rem] gap-6 md:items-start">
                                <flux:input wire:model="shipping_city" label="{{ __('City') }}"
                                    placeholder="{{ __('Metropolis') }}" />
                                <div class="w-full md:justify-self-end">
                                    <flux:input wire:model="shipping_postalcode" label="{{ __('Postal Code') }}"
                                        placeholder="10001" maxlength="12" />
                                </div>
                            </div>
                            <flux:select wire:model="shipping_country_id" label="{{ __('Country') }}" variant="listbox"
                                searchable>
                                @foreach($this->countryOptions as $option)
                                    <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @else
                            <div class="text-sm space-y-1 text-zinc-600 dark:text-zinc-300">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $shipping_name }}</div>
                                <div>{{ $shipping_address }}</div>
                                <div>{{ $shipping_city }}, {{ $shipping_postalcode }}</div>
                                <div>
                                    {{ collect($this->countryOptions)->firstWhere('value', $shipping_country_id)['label'] ?? $shipping_country_id }}
                                </div>
                            </div>
                        @endif
                    </flux:card>
                </div>

                <!-- Line Items Section -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Order Items') }}</flux:heading>
                        <flux:subheading>{{ __('Search products and add them to this order.') }}</flux:subheading>
                    </div>

                    <flux:error name="order_items" />

                    <div class="relative">
                        <flux:input wire:model.live.debounce.300ms="product_search"
                            placeholder="{{ __('Search products by name or SKU...') }}" icon="magnifying-glass"
                            clearable />

                        @if(!empty($search_results))
                            <div
                                class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                @foreach($search_results as $product)
                                    <div class="px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer flex justify-between items-center"
                                        wire:click="addProduct({{ $product['id'] }})">
                                        <div>
                                            <div class="font-medium text-sm text-zinc-900 dark:text-white">
                                                {{ $product['name'] }}
                                            </div>
                                            <div class="text-xs text-zinc-500">{{ $product['sku'] }}</div>
                                        </div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-white">
                                            {{ config('app.currency_symbol') }}{{ number_format($product['price'], 2) }}
                                        </div>
                                        <flux:button size="sm" variant="subtle" icon="plus" class="ml-2" />
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if(!empty($order_items))
                        <div class="mt-4 border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                            <table class="w-full text-sm text-left">
                                <thead
                                    class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                                    <tr>
                                        <th class="px-4 py-3">{{ __('Product') }}</th>
                                        <th class="px-4 py-3">{{ __('Price') }}</th>
                                        <th class="px-4 py-3">{{ __('Qty') }}</th>
                                        <th class="px-4 py-3">{{ __('Total') }}</th>
                                        <th class="px-4 py-3 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order_items as $index => $item)
                                        <tr class="border-b border-zinc-100 dark:border-zinc-800 last:border-0"
                                            wire:key="line-item-{{ $index }}">
                                    <td class="px-4 py-3 font-medium">
                                        {{ $item['name'] }}
                                        @if(!empty($item['source_group_product_display_name'] ?? $item['source_group_product_name'] ?? null))
                                            ({{ $item['source_group_product_display_name'] ?? $item['source_group_product_name'] }})
                                        @endif
                                    </td>
                                            <td class="px-4 py-3">
                                                <flux:input wire:model="order_items.{{ $index }}.price" type="number"
                                                    step="0.01" min="0" class="w-24" />
                                            </td>
                                            <td class="px-4 py-3">
                                                <flux:input wire:model.live="order_items.{{ $index }}.quantity" type="number"
                                                    min="1" class="w-20" />
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ config('app.currency_symbol') }}{{ number_format($item['price'] * $item['quantity'], 2) }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <flux:button variant="ghost" size="sm" icon="trash"
                                                    wire:click="removeProduct({{ $index }})"
                                                    class="text-zinc-400 hover:text-red-500!" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">
                <!-- Status & Customer -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Order Info') }}</flux:heading>
                    </div>

                    <flux:select wire:model="status" label="{{ __('Status') }}" variant="listbox" searchable>
                        @foreach($this->orderStatusOptions as $option)
                            <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="user_id" label="{{ __('Customer User (Optional)') }}" variant="listbox" searchable>
                        <flux:select.option value="">{{ __('Guest (None)') }}</flux:select.option>
                        @foreach($this->userOptions as $option)
                            <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="notes" label="{{ __('Order Notes') }}" rows="4"
                        placeholder="{{ __('Optional notes for this order...') }}" />
                </flux:card>

                <!-- Order Totals -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Summary') }}</flux:heading>
                    </div>

                    @php
                        $subtotal = collect($order_items)->sum(fn($item) => (float) $item['price'] * (int) $item['quantity']);
                        $total = $subtotal + (float) $shipping_amount + (float) $tax_amount + (float) $discount_amount;
                    @endphp

                    <div class="space-y-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-zinc-500">{{ __('Subtotal') }}</span>
                            <span
                                class="font-medium">{{ config('app.currency_symbol') }}{{ number_format($subtotal, 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center text-sm">
                            <span class="text-zinc-500">{{ __('Shipping') }}</span>
                            <span
                                class="font-medium">{{ config('app.currency_symbol') }}{{ number_format($shipping_amount, 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center text-sm">
                            <span class="text-zinc-500">{{ __('Tax') }}</span>
                            <span
                                class="font-medium">{{ config('app.currency_symbol') }}{{ number_format($tax_amount, 2) }}</span>
                        </div>

                        @if($discount_amount != 0)
                            <div class="flex justify-between items-center text-sm text-red-600">
                                <span class="text-zinc-500">{{ $discount_title ?: __('Discount') }}</span>
                                <span
                                    class="font-medium">{{ config('app.currency_symbol') }}{{ number_format($discount_amount, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <div
                        class="border-t border-zinc-200 dark:border-zinc-700 pt-4 flex justify-between items-center text-lg font-bold">
                        <span>{{ __('Total') }}</span>
                        <span>{{ config('app.currency_symbol') }}{{ number_format($total, 2) }}</span>
                    </div>
                </flux:card>
            </div>
        </div>
    </form>

    <x-scroll-to-error />
</div>

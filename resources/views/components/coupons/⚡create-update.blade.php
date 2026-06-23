<?php

use Livewire\Component;
use App\Models\Coupon;
use Flux\Flux;
use Illuminate\Support\Str;

new class extends Component {
    public ?Coupon $coupon = null;

    // General
    public $code = '';
    public $description = '';
    public $discount_type = 'percentage';
    public $amount = 0;
    public $allow_free_shipping = false;
    public $expiry_date = null;

    // Usage Restriction
    public $minimum_spend = null;
    public $maximum_spend = null;
    public $individual_use = false;
    public $exclude_sale_items = false;
    public $product_ids = [];
    public $exclude_product_ids = [];
    public $category_ids = [];
    public $exclude_category_ids = [];
    public $allowed_emails = '';

    // Usage Limits
    public $usage_limit_per_coupon = null;
    public $limit_usage_to_x_items = null;
    public $usage_limit_per_user = null;

    // UI State
    public $activeTab = 'general';
    public $product_search = '';
    public $product_search_results = [];
    public $exclude_product_search = '';
    public $exclude_product_search_results = [];
    public $category_search = '';
    public $category_search_results = [];
    public $exclude_category_search = '';
    public $exclude_category_search_results = [];

    // Preloaded options
    public $all_products = [];
    public $all_categories = [];

    // Selected items display
    public $selected_products = [];
    public $selected_exclude_products = [];
    public $selected_categories = [];
    public $selected_exclude_categories = [];

    public function mount(?Coupon $shopCoupon = null)
    {
        $this->coupon = $shopCoupon;

        $this->all_products = \App\Models\Product::orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])
            ->toArray();

        $this->all_categories = \Vanilo\Foundation\Models\Taxon::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            ->toArray();

        if ($this->coupon && $this->coupon->exists) {
            $this->code = $this->coupon->code;
            $this->description = $this->coupon->description ?? '';
            $this->discount_type = $this->coupon->discount_type;
            $this->amount = $this->coupon->amount;
            $this->allow_free_shipping = $this->coupon->allow_free_shipping;
            $this->expiry_date = $this->coupon->expiry_date?->format('Y-m-d');
            $this->minimum_spend = $this->coupon->minimum_spend;
            $this->maximum_spend = $this->coupon->maximum_spend;
            $this->individual_use = $this->coupon->individual_use;
            $this->exclude_sale_items = $this->coupon->exclude_sale_items;
            $this->product_ids = $this->coupon->product_ids ?? [];
            $this->exclude_product_ids = $this->coupon->exclude_product_ids ?? [];
            $this->category_ids = $this->coupon->category_ids ?? [];
            $this->exclude_category_ids = $this->coupon->exclude_category_ids ?? [];
            $this->allowed_emails = is_array($this->coupon->allowed_emails) ? implode(', ', $this->coupon->allowed_emails) : '';
            $this->usage_limit_per_coupon = $this->coupon->usage_limit_per_coupon;
            $this->limit_usage_to_x_items = $this->coupon->limit_usage_to_x_items;
            $this->usage_limit_per_user = $this->coupon->usage_limit_per_user;

            $this->loadSelectedItems();
        }
    }

    protected function loadSelectedItems()
    {
        if (!empty($this->product_ids)) {
            $this->selected_products = \App\Models\Product::whereIn('id', $this->product_ids)
                ->get(['id', 'name', 'sku'])
                ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])
                ->toArray();
        }

        if (!empty($this->exclude_product_ids)) {
            $this->selected_exclude_products = \App\Models\Product::whereIn('id', $this->exclude_product_ids)
                ->get(['id', 'name', 'sku'])
                ->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])
                ->toArray();
        }

        if (!empty($this->category_ids)) {
            $this->selected_categories = \Vanilo\Foundation\Models\Taxon::whereIn('id', $this->category_ids)
                ->get(['id', 'name'])
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
                ->toArray();
        }

        if (!empty($this->exclude_category_ids)) {
            $this->selected_exclude_categories = \Vanilo\Foundation\Models\Taxon::whereIn('id', $this->exclude_category_ids)
                ->get(['id', 'name'])
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
                ->toArray();
        }
    }

    public function generateCode()
    {
        $this->code = strtoupper(Str::random(8));
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    // Product search
    public function loadProducts()
    {
        $this->product_search_results = $this->searchProducts($this->product_search, $this->product_ids);
    }

    public function updatedProductSearch()
    {
        $this->loadProducts();
    }

    public function loadExcludeProducts()
    {
        $this->exclude_product_search_results = $this->searchProducts($this->exclude_product_search, $this->exclude_product_ids);
    }

    public function updatedExcludeProductSearch()
    {
        $this->loadExcludeProducts();
    }

    protected function searchProducts($term, $excludeIds = [])
    {
        $filtered = collect($this->all_products)->whereNotIn('id', $excludeIds);

        if (strlen($term) >= 1) {
            $term = strtolower($term);
            $filtered = $filtered->filter(fn($p) => str_contains(strtolower($p['name']), $term) || str_contains(strtolower($p['sku'] ?? ''), $term));
        }

        return $filtered->take(10)->values()->toArray();
    }

    public function addProduct($productId)
    {
        $product = \App\Models\Product::find($productId);
        if ($product && !in_array($productId, $this->product_ids)) {
            $this->product_ids[] = $productId;
            $this->selected_products[] = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku];
        }
        $this->product_search = '';
        $this->product_search_results = [];
    }

    public function removeProduct($productId)
    {
        $this->product_ids = array_values(array_diff($this->product_ids, [$productId]));
        $this->selected_products = array_values(array_filter($this->selected_products, fn($p) => $p['id'] != $productId));
    }

    public function addExcludeProduct($productId)
    {
        $product = \App\Models\Product::find($productId);
        if ($product && !in_array($productId, $this->exclude_product_ids)) {
            $this->exclude_product_ids[] = $productId;
            $this->selected_exclude_products[] = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku];
        }
        $this->exclude_product_search = '';
        $this->exclude_product_search_results = [];
    }

    public function removeExcludeProduct($productId)
    {
        $this->exclude_product_ids = array_values(array_diff($this->exclude_product_ids, [$productId]));
        $this->selected_exclude_products = array_values(array_filter($this->selected_exclude_products, fn($p) => $p['id'] != $productId));
    }

    // Category search
    public function loadCategories()
    {
        $this->category_search_results = $this->searchCategories($this->category_search, $this->category_ids);
    }

    public function updatedCategorySearch()
    {
        $this->loadCategories();
    }

    public function loadExcludeCategories()
    {
        $this->exclude_category_search_results = $this->searchCategories($this->exclude_category_search, $this->exclude_category_ids);
    }

    public function updatedExcludeCategorySearch()
    {
        $this->loadExcludeCategories();
    }

    protected function searchCategories($term, $excludeIds = [])
    {
        $filtered = collect($this->all_categories)->whereNotIn('id', $excludeIds);

        if (strlen($term) >= 1) {
            $term = strtolower($term);
            $filtered = $filtered->filter(fn($c) => str_contains(strtolower($c['name']), $term));
        }

        return $filtered->take(10)->values()->toArray();
    }

    public function addCategory($categoryId)
    {
        $category = \Vanilo\Foundation\Models\Taxon::find($categoryId);
        if ($category && !in_array($categoryId, $this->category_ids)) {
            $this->category_ids[] = $categoryId;
            $this->selected_categories[] = ['id' => $category->id, 'name' => $category->name];
        }
        $this->category_search = '';
        $this->category_search_results = [];
    }

    public function removeCategory($categoryId)
    {
        $this->category_ids = array_values(array_diff($this->category_ids, [$categoryId]));
        $this->selected_categories = array_values(array_filter($this->selected_categories, fn($c) => $c['id'] != $categoryId));
    }

    public function addExcludeCategory($categoryId)
    {
        $category = \Vanilo\Foundation\Models\Taxon::find($categoryId);
        if ($category && !in_array($categoryId, $this->exclude_category_ids)) {
            $this->exclude_category_ids[] = $categoryId;
            $this->selected_exclude_categories[] = ['id' => $category->id, 'name' => $category->name];
        }
        $this->exclude_category_search = '';
        $this->exclude_category_search_results = [];
    }

    public function removeExcludeCategory($categoryId)
    {
        $this->exclude_category_ids = array_values(array_diff($this->exclude_category_ids, [$categoryId]));
        $this->selected_exclude_categories = array_values(array_filter($this->selected_exclude_categories, fn($c) => $c['id'] != $categoryId));
    }

    public function save()
    {
        $this->validate([
            'code' => 'required|string|max:255|unique:shop_coupons,code,' . ($this->coupon?->id ?? 'NULL'),
            'discount_type' => 'required|in:percentage,fixed_cart,fixed_product',
            'amount' => 'required|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'minimum_spend' => 'nullable|numeric|min:0',
            'maximum_spend' => 'nullable|numeric|min:0',
            'usage_limit_per_coupon' => 'nullable|integer|min:0',
            'limit_usage_to_x_items' => 'nullable|integer|min:0',
            'usage_limit_per_user' => 'nullable|integer|min:0',
        ]);

        $emails = array_filter(array_map('trim', explode(',', $this->allowed_emails)));

        $data = [
            'code' => strtoupper($this->code),
            'description' => $this->description ?: null,
            'discount_type' => $this->discount_type,
            'amount' => $this->amount !== '' && $this->amount !== null ? $this->amount : 0,
            'allow_free_shipping' => $this->allow_free_shipping,
            'expiry_date' => $this->expiry_date ?: null,
            'minimum_spend' => $this->minimum_spend !== '' && $this->minimum_spend !== null ? $this->minimum_spend : null,
            'maximum_spend' => $this->maximum_spend !== '' && $this->maximum_spend !== null ? $this->maximum_spend : null,
            'individual_use' => $this->individual_use,
            'exclude_sale_items' => $this->exclude_sale_items,
            'product_ids' => !empty($this->product_ids) ? $this->product_ids : null,
            'exclude_product_ids' => !empty($this->exclude_product_ids) ? $this->exclude_product_ids : null,
            'category_ids' => !empty($this->category_ids) ? $this->category_ids : null,
            'exclude_category_ids' => !empty($this->exclude_category_ids) ? $this->exclude_category_ids : null,
            'allowed_emails' => !empty($emails) ? $emails : null,
            'usage_limit_per_coupon' => $this->usage_limit_per_coupon ?: null,
            'limit_usage_to_x_items' => $this->limit_usage_to_x_items ?: null,
            'usage_limit_per_user' => $this->usage_limit_per_user ?: null,
        ];

        if ($this->coupon && $this->coupon->exists) {
            $this->coupon->update($data);
            Flux::toast(__('Coupon updated successfully.'), variant: 'success');
        } else {
            Coupon::create($data);
            Flux::toast(__('Coupon created successfully.'), variant: 'success');
        }

        $this->redirect(route('coupons.index'), navigate: true);
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $coupon ? __('Edit Coupon') : __('Create Coupon') }}</flux:heading>
                <flux:subheading>
                    {{ $coupon ? __('Update your coupon details.') : __('Add a new coupon.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button href="{{ route('coupons.index') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        {{-- Coupon Code & Description --}}
        <flux:card class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <flux:input wire:model="code" label="{{ __('Coupon Code') }}"
                        placeholder="{{ __('Enter coupon code') }}" />
                </div>
                <div class="pt-6">
                    <flux:button wire:click.prevent="generateCode" variant="primary" size="sm">
                        {{ __('Generate Code') }}
                    </flux:button>
                </div>
            </div>
            <flux:textarea wire:model="description" label="{{ __('Description') }}"
                placeholder="{{ __('Description (optional)') }}" rows="3" />
        </flux:card>

        {{-- Tabs --}}
        <flux:card>
            <div class="flex border-b border-zinc-200 dark:border-zinc-700 mb-6">
                <button type="button" wire:click="setTab('general')"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'general' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    {{ __('General') }}
                </button>
                <button type="button" wire:click="setTab('usage_restriction')"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'usage_restriction' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    {{ __('Usage Restriction') }}
                </button>
                <button type="button" wire:click="setTab('usage_limits')"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'usage_limits' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    {{ __('Usage Limits') }}
                </button>
            </div>

            {{-- General Tab --}}
            <div class="space-y-6" x-show="$wire.activeTab === 'general'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:select wire:model="discount_type" label="{{ __('Discount Type') }}">
                        @foreach (\App\Models\Coupon::DISCOUNT_TYPES as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="amount" type="number" step="0.01" min="0"
                        label="{{ __('Coupon Amount') }}" />
                </div>

                <flux:checkbox wire:model="allow_free_shipping" label="{{ __('Allow free shipping') }}"
                    description="{{ __('Check this box if the coupon grants free shipping. A free shipping method must be enabled in your shipping zone.') }}" />

                <flux:input wire:model="expiry_date" type="date" label="{{ __('Coupon Expiry Date') }}" />
            </div>

            {{-- Usage Restriction Tab --}}
            <div class="space-y-6" x-show="$wire.activeTab === 'usage_restriction'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:input wire:model="minimum_spend" type="number" step="0.01" min="0"
                        label="{{ __('Minimum Spend') }}" placeholder="0" />

                    <flux:input wire:model="maximum_spend" type="number" step="0.01" min="0"
                        label="{{ __('Maximum Spend') }}" placeholder="{{ __('No maximum') }}" />
                </div>

                <flux:checkbox wire:model="individual_use" label="{{ __('Individual use only') }}"
                    description="{{ __('Check this box if the coupon cannot be used in conjunction with other coupons.') }}" />

                <flux:checkbox wire:model="exclude_sale_items" label="{{ __('Exclude sale items') }}"
                    description="{{ __('Check this box if the coupon should not apply to items on sale.') }}" />

                {{-- Products --}}
                <div x-data="{ open: false }">
                    <flux:field>
                        <flux:label>{{ __('Products') }}</flux:label>
                        <div class="relative">
                            <flux:input wire:model.live.debounce.300ms="product_search"
                                x-on:focus="open = true; $wire.loadProducts()" x-on:click.away="open = false"
                                placeholder="{{ __('Search products...') }}" icon="magnifying-glass" clearable />
                            <div x-show="open && $wire.product_search_results.length > 0" x-cloak
                                class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach ($product_search_results as $product)
                                    <div class="px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer flex justify-between items-center"
                                        wire:click="addProduct({{ $product['id'] }})" x-on:click="open = false">
                                        <div>
                                            <div class="font-medium text-sm">{{ $product['name'] }}</div>
                                            <div class="text-xs text-zinc-500">{{ $product['sku'] }}</div>
                                        </div>
                                        <flux:icon.plus class="size-4 text-zinc-400" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if (!empty($selected_products))
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach ($selected_products as $product)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded-md text-sm">
                                        {{ $product['name'] }} (#{{ $product['id'] }})
                                        <button type="button" wire:click="removeProduct({{ $product['id'] }})"
                                            class="text-zinc-400 hover:text-red-500">
                                            <flux:icon.x-mark class="size-3" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </flux:field>
                </div>

                {{-- Exclude Products --}}
                <div x-data="{ open: false }">
                    <flux:field>
                        <flux:label>{{ __('Exclude Products') }}</flux:label>
                        <div class="relative">
                            <flux:input wire:model.live.debounce.300ms="exclude_product_search"
                                x-on:focus="open = true; $wire.loadExcludeProducts()" x-on:click.away="open = false"
                                placeholder="{{ __('Search products to exclude...') }}" icon="magnifying-glass"
                                clearable />
                            <div x-show="open && $wire.exclude_product_search_results.length > 0" x-cloak
                                class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach ($exclude_product_search_results as $product)
                                    <div class="px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer flex justify-between items-center"
                                        wire:click="addExcludeProduct({{ $product['id'] }})"
                                        x-on:click="open = false">
                                        <div>
                                            <div class="font-medium text-sm">{{ $product['name'] }}</div>
                                            <div class="text-xs text-zinc-500">{{ $product['sku'] }}</div>
                                        </div>
                                        <flux:icon.plus class="size-4 text-zinc-400" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if (!empty($selected_exclude_products))
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach ($selected_exclude_products as $product)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded-md text-sm">
                                        {{ $product['name'] }} (#{{ $product['id'] }})
                                        <button type="button"
                                            wire:click="removeExcludeProduct({{ $product['id'] }})"
                                            class="text-zinc-400 hover:text-red-500">
                                            <flux:icon.x-mark class="size-3" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </flux:field>
                </div>

                {{-- Product Categories --}}
                <div x-data="{ open: false }">
                    <flux:field>
                        <flux:label>{{ __('Product Categories') }}</flux:label>
                        <div class="relative">
                            <flux:input wire:model.live.debounce.300ms="category_search"
                                x-on:focus="open = true; $wire.loadCategories()" x-on:click.away="open = false"
                                placeholder="{{ __('Search categories...') }}" icon="magnifying-glass" clearable />
                            <div x-show="open && $wire.category_search_results.length > 0" x-cloak
                                class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach ($category_search_results as $category)
                                    <div class="px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer"
                                        wire:click="addCategory({{ $category['id'] }})" x-on:click="open = false">
                                        <div class="font-medium text-sm">{{ $category['name'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if (!empty($selected_categories))
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach ($selected_categories as $category)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded-md text-sm">
                                        {{ $category['name'] }}
                                        <button type="button" wire:click="removeCategory({{ $category['id'] }})"
                                            class="text-zinc-400 hover:text-red-500">
                                            <flux:icon.x-mark class="size-3" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </flux:field>
                </div>

                {{-- Exclude Categories --}}
                <div x-data="{ open: false }">
                    <flux:field>
                        <flux:label>{{ __('Exclude Categories') }}</flux:label>
                        <div class="relative">
                            <flux:input wire:model.live.debounce.300ms="exclude_category_search"
                                x-on:focus="open = true; $wire.loadExcludeCategories()" x-on:click.away="open = false"
                                placeholder="{{ __('Search categories to exclude...') }}" icon="magnifying-glass"
                                clearable />
                            <div x-show="open && $wire.exclude_category_search_results.length > 0" x-cloak
                                class="absolute z-10 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                @foreach ($exclude_category_search_results as $category)
                                    <div class="px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer"
                                        wire:click="addExcludeCategory({{ $category['id'] }})"
                                        x-on:click="open = false">
                                        <div class="font-medium text-sm">{{ $category['name'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if (!empty($selected_exclude_categories))
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach ($selected_exclude_categories as $category)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-1 bg-zinc-100 dark:bg-zinc-700 rounded-md text-sm">
                                        {{ $category['name'] }}
                                        <button type="button"
                                            wire:click="removeExcludeCategory({{ $category['id'] }})"
                                            class="text-zinc-400 hover:text-red-500">
                                            <flux:icon.x-mark class="size-3" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </flux:field>
                </div>

                {{-- Allowed Emails --}}
                <flux:input wire:model="allowed_emails" label="{{ __('Allowed Emails') }}"
                    placeholder="{{ __('e.g. *@yahoo.com, john@example.com') }}"
                    description="{{ __('Comma-separated list of allowed emails. Use * for wildcards.') }}" />
            </div>

            {{-- Usage Limits Tab --}}
            <div class="space-y-6" x-show="$wire.activeTab === 'usage_limits'">
                <flux:input wire:model="usage_limit_per_coupon" type="number" min="0"
                    label="{{ __('Usage limit per coupon') }}" placeholder="{{ __('Unlimited usage') }}"
                    description="{{ __('How many times this coupon can be used before it is void.') }}" />

                <div x-show="$wire.discount_type !== 'fixed_cart'">
                    <flux:input wire:model="limit_usage_to_x_items" type="number" min="0"
                        label="{{ __('Limit usage to X items') }}"
                        placeholder="{{ __('Apply to all qualifying items in cart') }}"
                        description="{{ __('Maximum number of individual items this coupon can apply to when using product discounts. Leave blank to apply to all qualifying items in cart.') }}" />
                </div>

                <flux:input wire:model="usage_limit_per_user" type="number" min="0"
                    label="{{ __('Usage limit per user') }}" placeholder="{{ __('Unlimited usage') }}"
                    description="{{ __('How many times this coupon can be used by an individual user. Uses billing email for guests.') }}" />
            </div>
        </flux:card>

        {{-- Usage count (edit mode only) --}}
        @if ($coupon && $coupon->exists)
            <flux:card>
                <flux:field>
                    <flux:label>{{ __('Usage Count') }}</flux:label>
                    @if ($usage_limit_per_coupon)
                        <flux:progress
                            value="{{ $coupon->usage_count }}"
                            max="{{ $usage_limit_per_coupon }}"
                            color="{{ $coupon->usage_count >= $usage_limit_per_coupon ? 'red' : 'blue' }}"
                            class="h-3"
                        />
                        <flux:description>
                            {{ $coupon->usage_count }} / {{ $usage_limit_per_coupon }} {{ __('uses') }}
                        </flux:description>
                    @else
                        <flux:progress value="0" max="100" color="blue" class="h-3" />
                        <flux:description>
                            {{ $coupon->usage_count }} / ∞ {{ __('uses (unlimited)') }}
                        </flux:description>
                    @endif
                </flux:field>
            </flux:card>
        @endif
    </form>
</div>

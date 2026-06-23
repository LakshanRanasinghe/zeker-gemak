<?php

use Livewire\Component;
use App\Models\DiscountGroup;
use Flux\Flux;

new class extends Component {
    public ?DiscountGroup $discountGroup;

    public $name;
    public $discounts = [];
    public $products;
    public $product_search = '';
    public $search_results = [];

    public function mount()
    {
        if (!empty($this->discountGroup) && $this->discountGroup->exists) {
            $this->name = $this->discountGroup->name;
            $this->discounts = json_decode($this->discountGroup->discounts, true);

            $this->products = $this->discountGroup->products;
        }
    }

    public function addDiscount()
    {
        $this->discounts[] = [
            'quantity' => '',
            'discount' => ''
        ];
    }

    public function removeDiscount($index)
    {
        unset($this->discounts[$index]);
    }

    public function save()
    {
        $this->validate([
            'name' => 'required',
            'discounts' => 'required',
        ]);

        $isEditing = !empty($this->discountGroup) && $this->discountGroup->exists;

        if ($isEditing) {
            $this->discountGroup->update([
                'name' => $this->name,
                'discounts' => json_encode($this->discounts),
            ]);

            Flux::toast(__('Discount group updated successfully.'), variant: 'success');
        } else {
            DiscountGroup::create([
                'name' => $this->name,
                'discounts' => json_encode($this->discounts),
            ]);

            Flux::toast(__('Discount group created successfully.'), variant: 'success');
        }

        $this->redirect(route('discount-groups.index'), navigate: true);
    }

    public function updatedProductSearch()
    {
        if (strlen($this->product_search) > 1) {
            $this->search_results = \App\Models\Product::where('name', 'like', '%' . $this->product_search . '%')
                ->orWhere('sku', 'like', '%' . $this->product_search . '%')
                ->take(5)
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'price' => (float) $p->price
                    ];
                })
                ->toArray();
        } else {
            $this->search_results = [];
        }
    }

    public function addProduct($productId)
    {
        $product = \App\Models\Product::find($productId);
        if ($product) {
            $product->discount_group_id = $this->discountGroup->id;
            $product->save();
            $this->product_search = '';
            $this->search_results = [];
        }
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $discountGroup ? __('Edit Discount Group') : __('Create Discount Group') }}
                </flux:heading>
                <flux:subheading>
                    {{ $discountGroup ? __('Update your discount group details.') : __('Add a new discount group.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <flux:card class="space-y-6">
            <div>
                <flux:field>
                    <flux:input wire:model="name" label="{{ __('Name') }}" />
                </flux:field>
            </div>
            <flux:field label="{{ __('Discounts') }}">
                @foreach ($discounts as $index => $discount)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        <flux:input type="number" wire:model="discounts.{{ $index }}.quantity" label="{{__('Quantity')}}" />
                        <flux:input type="number" wire:model="discounts.{{ $index }}.discount" icon:trailing="percent-badge"
                            label="{{__('Discount')}}" />
                        <flux:button wire:click="removeDiscount({{ $index }})" icon="trash" />
                    </div>
                @endforeach
                <div class="flex justify-start">
                    <flux:button wire:click="addDiscount">{{__('Add Discount')}}</flux:button>
                </div>
            </flux:field>
            <flux:button wire:click="save">{{__('Save')}}</flux:button>
        </flux:card>
    </form>

    <div class="h-6"></div>

    @if($products && $products->count() > 0)
        <flux:card class="space-y-6">
            <flux:heading size="xl">{{__('Products')}}</flux:heading>
            <flux:subheading>{{__('Add products to this discount group.')}}</flux:subheading>

            <div class="relative">
                <flux:input wire:model.live.debounce.300ms="product_search" placeholder="Search products by name or SKU..."
                    icon="magnifying-glass" clearable />

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

            @foreach($products as $product)
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <flux:avatar src="{{ $product->image_url }}" />
                        <div>
                            <flux:link
                                href="{{ route('products.edit', ['productKey' => $product->product_type . '_' . $product->id]) }}"
                                variant="ghost">{{ $product->name }}
                            </flux:link>
                            <flux:text>{{ $product->sku }}</flux:text>
                        </div>
                    </div>
                    <flux:button wire:click="removeProduct({{ $product->id }})" icon="trash" />
                </div>
            @endforeach
        </flux:card>
    @endif
</div>
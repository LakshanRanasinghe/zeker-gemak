<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Flux\Flux;

new class extends Component {
    public array $productIds = [];
    public bool $showModal = false;

    // Fields to bulk edit
    public ?string $price = null;
    public ?string $status = null;
    public ?string $category = null;

    #[On('bulk-edit')]
    public function openBulkEditModal(mixed $productIds = []): void
    {
        // Normalize: may arrive as a string, a single ID, or a nested event array
        if (is_string($productIds) || is_int($productIds)) {
            $productIds = [$productIds];
        }
        if (isset($productIds['productIds'])) {
            $productIds = $productIds['productIds'];
        }

        if (empty($productIds)) {
            Flux::toast(__('Please select at least one product.'), variant: 'warning');
            return;
        }

        $this->productIds = $productIds;
        $this->showModal = true;

        $this->reset(['price', 'status', 'category']);
    }

    public function save(): void
    {
        if (empty($this->productIds)) {
            return;
        }

        $updates = [];
        if ($this->price !== null && $this->price !== '') {
            $updates['price'] = $this->price;
        }
        if ($this->status !== null && $this->status !== '') {
            $updates['state'] = $this->status;
        }

        if (!empty($updates)) {
            \Vanilo\Foundation\Models\Product::whereIn('id', $this->productIds)->update($updates);
            \Vanilo\Foundation\Models\MasterProduct::whereIn('id', $this->productIds)->update($updates);
        }

        // Category syncing is a bit more complex (many-to-many), keep it simple for now or implement if strictly needed

        $this->showModal = false;
        $this->dispatch('pg:eventRefresh-products');

        // Use Flux toast
        Flux::toast(__('Products updated successfully.'), variant: 'success');
    }
};
?>

<div>
    <flux:modal wire:model="showModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Bulk Edit Products') }}</flux:heading>
                <flux:subheading>{{ __('You are editing :count products.', ['count' => count($productIds)]) }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="price" label="{{ __('Price') }}" placeholder="{{ __('Leave blank to keep current') }}" />

                <flux:select wire:model="status" label="{{ __('Status') }}" placeholder="{{ __('Leave blank to keep current') }}">
                    <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
                    <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="flex">
                <flux:spacer />

                <flux:button type="button" variant="ghost" class="mr-2" wire:click="$set('showModal', false)">{{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="primary" wire:click="save">{{ __('Save Changes') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
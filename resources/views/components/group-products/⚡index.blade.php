<?php
use Livewire\Component;

new class extends Component {
    //
};

?>

<div class="px-6 py-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Group Products') }}</flux:heading>
            <flux:subheading>{{ __('Manage bundled products.') }}</flux:subheading>
        </div>
        <flux:button href="{{ route('group-products.create') }}" variant="primary" icon="plus" wire:navigate>
            {{ __('Add Group Product') }}
        </flux:button>
    </div>

    <flux:card>
        <livewire:group-product-table />
    </flux:card>
</div>
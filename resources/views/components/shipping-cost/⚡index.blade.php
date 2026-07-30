<?php

use Livewire\Component;

new class extends Component {};
?>

<div>
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Shipping Costs') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Manage country shipping costs and free-shipping thresholds.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('shipping-cost.create') }}" wire:navigate>
            {{ __('New Shipping Rule') }}
        </flux:button>
    </div>

    <livewire:shipping-cost-table />
</div>

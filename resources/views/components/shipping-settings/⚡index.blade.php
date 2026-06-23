<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Shipping Methods') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Define shipping options, fees, and delivery estimates.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('shipping-settings.create') }}" wire:navigate>
            {{ __('New Shipping Method') }}
        </flux:button>
    </div>

    <livewire:shipping-method-table />
</div>

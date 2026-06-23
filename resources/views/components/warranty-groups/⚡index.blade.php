<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Warranty Groups') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage reusable warranty options for products.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('warranty-groups.create') }}" wire:navigate>
            {{ __('New Group') }}
        </flux:button>
    </div>

    <livewire:warranty-group-table />
</div>

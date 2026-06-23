<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Zones') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage geographical zones for tax and shipping rules.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" href="{{ route('zones.create') }}" wire:navigate>
                {{ __('New Zone') }}
            </flux:button>
        </div>
    </div>

    <livewire:zone-table />
</div>

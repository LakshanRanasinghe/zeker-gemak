<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Tax Rates') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Define tax percentages per category and zone.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" href="{{ route('tax-settings.create') }}" wire:navigate>
            {{ __('New Tax Rate') }}
        </flux:button>
    </div>

    <livewire:tax-rate-table />
</div>

<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Discount Groups') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage your discount groups.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" href="{{ route('discount-groups.create') }}" wire:navigate>
                {{ __('New Group') }}
            </flux:button>
        </div>
    </div>
    <livewire:discount-groups />
</div>
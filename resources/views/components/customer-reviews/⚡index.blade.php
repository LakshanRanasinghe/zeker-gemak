<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Customer Reviews') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">
                {{ __('Moderate customer reviews. Only approved reviews are visible on your storefront.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" href="{{ route('customer-reviews.create') }}" wire:navigate>
                {{ __('New Review') }}
            </flux:button>
        </div>
    </div>

    <livewire:customer-review-table />
</div>

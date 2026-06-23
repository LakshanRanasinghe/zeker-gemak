<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('FAQ Pages') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">
                {{ __('Build public FAQ pages from reusable items.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" href="{{ route('faq-pages.create') }}" wire:navigate>
                {{ __('New FAQ Page') }}
            </flux:button>
        </div>
    </div>

    <livewire:faq-page-table />
</div>

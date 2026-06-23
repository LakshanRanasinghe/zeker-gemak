<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('FAQ Items') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">
                {{ __('The central bank of reusable questions & answers.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" href="{{ route('faq-items.create') }}" wire:navigate>
                {{ __('New FAQ Item') }}
            </flux:button>
        </div>
    </div>

    <livewire:faq-item-table />
</div>

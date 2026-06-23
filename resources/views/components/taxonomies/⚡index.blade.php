<?php

use Livewire\Component;

new class extends Component {
    public function render()
    {
        return view('components.taxonomies.⚡index');
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Taxonomies') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage product categories, brands, or collections.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" href="{{ route('taxonomies.create') }}">
                {{ __('New Taxonomy') }}
            </flux:button>
        </div>
    </div>

    <!-- The PowerGrid component we just generated -->
    <livewire:taxonomy-table />
</div>
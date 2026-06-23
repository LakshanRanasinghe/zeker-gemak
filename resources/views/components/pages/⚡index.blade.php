<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Pages') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage your pages and posts.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" href="{{ route('pages.create') }}" wire:navigate>
                {{ __('New Page') }}
            </flux:button>
        </div>
    </div>

    <livewire:page-table />
</div>
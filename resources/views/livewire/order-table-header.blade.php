<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
    <div class="flex items-center gap-2">
        <flux:field>
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Search orders...') }}" 
                icon="magnifying-glass" 
                class="w-full md:w-80"
            />
        </flux:field>

        <flux:field>
            <flux:select wire:model.live="statusFilter" class="w-full md:w-48" placeholder="{{ __('All Statuses') }}">
                <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
                <flux:select.option value="processing">{{ __('Processing') }}</flux:select.option>
                <flux:select.option value="shipped">{{ __('Shipped') }}</flux:select.option>
                <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
            </flux:select>
        </flux:field>
    </div>

    <div class="flex items-center gap-2">
        <flux:button.group>
            <flux:button size="sm" icon="squares-2x2" :variant="$exportFilter === '' ? 'filled' : 'outline'" wire:click="$set('exportFilter', '')">{{ __('All') }}</flux:button>
            <flux:button size="sm" icon="check" :variant="$exportFilter === 'exported' ? 'filled' : 'outline'" wire:click="$set('exportFilter', 'exported')">{{ __('Exported') }}</flux:button>
            <flux:button size="sm" icon="x-mark" :variant="$exportFilter === 'unexported' ? 'filled' : 'outline'" wire:click="$set('exportFilter', 'unexported')">{{ __('Unexported') }}</flux:button>
        </flux:button.group>
    </div>
</div>

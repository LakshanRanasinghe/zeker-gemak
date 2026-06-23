<flux:modal name="ship-order-modal" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Confirm Shipment') }}</flux:heading>
            <flux:text>{{ __('Are you sure you want to mark this order as shipped?') }}</flux:text>
        </div>

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" wire:click="shipOrder({{ $orderToShipId }})">
                {{ __('Mark as Shipped') }}
            </flux:button>
        </div>
    </div>
</flux:modal>

<flux:modal name="bulk-status-modal" class="md:w-96">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Update Order Status') }}</flux:heading>
            <flux:text>{{ __('Select the new status for the selected orders.') }}</flux:text>
        </div>

        <flux:select wire:model="bulkStatus" placeholder="{{ __('Choose status...') }}">
            <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            <flux:select.option value="processing">{{ __('Processing') }}</flux:select.option>
            <flux:select.option value="shipped">{{ __('Shipped') }}</flux:select.option>
            <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
            <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
        </flux:select>

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" wire:click="bulkChangeStatus">
                {{ __('Update Status') }}
            </flux:button>
        </div>
    </div>
</flux:modal>

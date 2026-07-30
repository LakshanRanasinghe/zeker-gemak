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

<flux:modal name="shipping-label-modal" class="w-full max-w-6xl">
    <form wire:submit="generateShippingLabel" class="space-y-6">
        <div>
            <flux:heading size="xl">{{ __('Generate shipping label') }}</flux:heading>
            <flux:text>{{ __('Check the delivery details and choose the DHL service for this shipment.') }}</flux:text>
        </div>

        <div class="grid gap-8 lg:grid-cols-[2fr_1fr]">
            <section class="space-y-5">
                <flux:heading size="lg">{{ __('Shipping details') }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="labelRecipient.first_name" label="{{ __('First name') }}" />
                    <flux:input wire:model="labelRecipient.last_name" label="{{ __('Last name') }}" />
                    <flux:input wire:model="labelRecipient.company" label="{{ __('Company') }}" />
                    <flux:checkbox wire:model.live="labelRecipient.is_business" label="{{ __('Is business address') }}" />
                    <flux:input wire:model="labelRecipient.email" type="email" label="{{ __('Email') }}" />
                    <flux:input wire:model="labelRecipient.phone" label="{{ __('Phone') }}" />
                </div>

                <flux:heading size="lg">{{ __('Shipping address') }}</flux:heading>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="labelRecipient.street" label="{{ __('Street') }}" />
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="labelRecipient.house_number" label="{{ __('House number') }}" />
                        <flux:input wire:model="labelRecipient.addition" label="{{ __('Addition') }}" />
                    </div>
                    <flux:input wire:model="labelRecipient.postal_code" label="{{ __('Postal code') }}" />
                    <flux:input wire:model="labelRecipient.city" label="{{ __('City') }}" />
                    <flux:input wire:model="labelRecipient.country_code" label="{{ __('Country code') }}" />
                </div>
            </section>

            <section class="space-y-5 border-zinc-200 lg:border-l lg:pl-8 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ __('DHL configuration') }}</flux:heading>
                    <flux:text>{{ __('These selections apply only to this label.') }}</flux:text>
                </div>

                <flux:select wire:model="labelCarrier" label="{{ __('Carrier') }}">
                    <flux:select.option value="DHL-PARCEL">{{ __('DHL Parcel') }}</flux:select.option>
                    <flux:select.option value="DHL-EXPRESS">{{ __('DHL Express') }}</flux:select.option>
                </flux:select>

                <flux:input wire:model="labelParcelType" list="dhl-parcel-types" label="{{ __('Parcel type') }}" description="{{ __('DHL code, for example SMALL') }}" />
                <datalist id="dhl-parcel-types">
                    <option value="SMALL">XS Small</option>
                </datalist>

                <flux:input wire:model="labelShippingMethod" list="dhl-shipping-methods" label="{{ __('Shipping method') }}" description="{{ __('DHL product code, for example DFY-B2C') }}" />
                <datalist id="dhl-shipping-methods">
                    <option value="DFY-B2C"></option>
                    <option value="EPL"></option>
                </datalist>
            </section>
        </div>

        <div class="flex gap-2">
            <flux:spacer />

            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="primary" icon="printer" wire:loading.attr="disabled">
                {{ __('Generate DHL label') }}
            </flux:button>
        </div>
    </form>
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

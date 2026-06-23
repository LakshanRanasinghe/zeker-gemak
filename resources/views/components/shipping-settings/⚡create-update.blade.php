<?php

use Livewire\Component;
use Vanilo\Shipment\Models\Carrier;
use Vanilo\Shipment\Models\ShippingMethod;
use Vanilo\Shipment\ShippingFeeCalculators;
use Konekt\Address\Models\Zone;
use Flux\Flux;
use Illuminate\Validation\ValidationException;

new class extends Component {
    public ?ShippingMethod $shippingMethod = null;

    public string $methodName = '';
    public ?string $methodCarrierId = null;
    public ?string $methodZoneId = null;
    public string $methodCalculator = 'flat_fee';
    public bool $methodIsActive = true;
    public string $methodEtaMin = '';
    public string $methodEtaMax = '';
    public string $configCost = '0';
    public string $configFreeThreshold = '';
    public string $configTitle = '';
    public string $configDiscountedThreshold = '';
    public string $configDiscountedCost = '';

    public ?int $editingCarrierId = null;
    public string $carrierName = '';
    public bool $carrierIsActive = true;

    public function getCarriersProperty()
    {
        return Carrier::orderBy('name')->get();
    }

    public function getActiveCarriersProperty()
    {
        return Carrier::where('is_active', true)->orderBy('name')->get();
    }

    public function getZonesProperty()
    {
        return Zone::orderBy('name')->get();
    }

    public function getCalculatorChoicesProperty(): array
    {
        return ShippingFeeCalculators::choices();
    }

    public function mount(?ShippingMethod $shippingMethod = null): void
    {
        if ($shippingMethod && $shippingMethod->exists) {
            $this->shippingMethod = $shippingMethod;
            $this->methodName = $shippingMethod->name;
            $this->methodCarrierId = $shippingMethod->carrier_id ? (string) $shippingMethod->carrier_id : null;
            $this->methodZoneId = $shippingMethod->zone_id ? (string) $shippingMethod->zone_id : null;
            $this->methodCalculator = $shippingMethod->calculator ?? 'flat_fee';
            $this->methodIsActive = (bool) $shippingMethod->is_active;
            $this->methodEtaMin = $shippingMethod->eta_min ? (string) $shippingMethod->eta_min : '';
            $this->methodEtaMax = $shippingMethod->eta_max ? (string) $shippingMethod->eta_max : '';

            $config = $shippingMethod->configuration ?? [];
            $this->configCost = isset($config['cost']) ? (string) $config['cost'] : '0';
            $this->configFreeThreshold = isset($config['free_threshold']) ? (string) $config['free_threshold'] : '';
            $this->configTitle = $config['title'] ?? '';
            $this->configDiscountedThreshold = isset($config['discounted_threshold']) ? (string) $config['discounted_threshold'] : '';
            $this->configDiscountedCost = isset($config['discounted_cost']) ? (string) $config['discounted_cost'] : '';
        }
    }

    public function save(): void
    {
        try {
            $this->validate(
                [
                    'methodName' => 'required|string|max:255',
                    'methodCarrierId' => 'nullable|exists:carriers,id',
                    'methodZoneId' => 'nullable|exists:zones,id',
                    'methodCalculator' => 'required|string',
                    'configCost' => 'required|numeric|min:0',
                    'configFreeThreshold' => 'nullable|numeric|min:0',
                    'configDiscountedThreshold' => 'nullable|numeric|min:0',
                    'configDiscountedCost' => 'nullable|numeric|min:0',
                    'methodEtaMin' => 'nullable|integer|min:0',
                    'methodEtaMax' => 'nullable|integer|min:0|gte:methodEtaMin',
                ],
                [
                    'methodName.required' => __('Shipping method name is required.'),
                    'configCost.required' => __('Shipping cost is required.'),
                    'methodEtaMax.gte' => __('Max ETA must be greater than or equal to min ETA.'),
                ],
            );
        } catch (ValidationException $e) {
            $this->dispatch('scroll-to-error');
            throw $e;
        }

        $data = [
            'name' => $this->methodName,
            'carrier_id' => filled($this->methodCarrierId) ? $this->methodCarrierId : null,
            'zone_id' => filled($this->methodZoneId) ? $this->methodZoneId : null,
            'calculator' => $this->methodCalculator,
            'configuration' => $this->buildMethodConfiguration(),
            'is_active' => $this->methodIsActive,
            'eta_min' => filled($this->methodEtaMin) ? (int) $this->methodEtaMin : null,
            'eta_max' => filled($this->methodEtaMax) ? (int) $this->methodEtaMax : null,
        ];

        if ($this->shippingMethod && $this->shippingMethod->exists) {
            $this->shippingMethod->update($data);
            Flux::toast(__('Shipping method updated.'), variant: 'success');
        } else {
            ShippingMethod::create($data);
            Flux::toast(__('Shipping method created.'), variant: 'success');
        }

        $this->redirect(route('shipping-settings.index'), navigate: true);
    }

    protected function buildMethodConfiguration(): array
    {
        $config = [];

        if (in_array($this->methodCalculator, ['flat_fee', 'discountable_shipping_fee', 'payment_dependent_fee'])) {
            $config['cost'] = (float) $this->configCost;
            $config['title'] = filled($this->configTitle) ? $this->configTitle : 'Shipping fee';

            if (filled($this->configFreeThreshold)) {
                $config['free_threshold'] = (float) $this->configFreeThreshold;
            }
        }

        if ($this->methodCalculator === 'discountable_shipping_fee') {
            if (filled($this->configDiscountedThreshold)) {
                $config['discounted_threshold'] = (float) $this->configDiscountedThreshold;
            }
            if (filled($this->configDiscountedCost)) {
                $config['discounted_cost'] = (float) $this->configDiscountedCost;
            }
        }

        return $config;
    }

    public function openCreateCarrier(): void
    {
        $this->resetValidation(['carrierName']);
        $this->editingCarrierId = null;
        $this->carrierName = '';
        $this->carrierIsActive = true;
    }

    public function openEditCarrier(int $id): void
    {
        $this->resetValidation(['carrierName']);
        $carrier = Carrier::findOrFail($id);
        $this->editingCarrierId = $carrier->id;
        $this->carrierName = $carrier->name;
        $this->carrierIsActive = (bool) $carrier->is_active;
    }

    public function saveCarrier(): void
    {
        $uniqueRule = 'unique:carriers,name' . ($this->editingCarrierId ? ',' . $this->editingCarrierId : '');

        $this->validate(
            [
                'carrierName' => 'required|string|max:255|' . $uniqueRule,
            ],
            [
                'carrierName.required' => __('Carrier name is required.'),
                'carrierName.unique' => __('A carrier with this name already exists.'),
            ],
        );

        $data = [
            'name' => $this->carrierName,
            'is_active' => $this->carrierIsActive,
        ];

        if ($this->editingCarrierId) {
            Carrier::findOrFail($this->editingCarrierId)->update($data);
            Flux::toast(__('Carrier updated.'), variant: 'success');
        } else {
            Carrier::create($data);
            Flux::toast(__('Carrier created.'), variant: 'success');
        }

        $this->openCreateCarrier();
    }

    public function deleteCarrier(int $id): void
    {
        if (ShippingMethod::where('carrier_id', $id)->exists()) {
            Flux::toast(__('Cannot delete: this carrier has shipping methods assigned.'), variant: 'danger');
            return;
        }

        Carrier::findOrFail($id)->delete();

        if ($this->editingCarrierId === $id) {
            $this->openCreateCarrier();
        }

        Flux::toast(__('Carrier deleted.'), variant: 'success');
    }
};
?>

<div>
    <x-scroll-to-error />

    <form wire:submit="save" class="space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">
                    {{ $shippingMethod ? __('Edit Shipping Method') : __('Create Shipping Method') }}
                </flux:heading>
                <flux:subheading>
                    {{ $shippingMethod ? __('Update the shipping method details.') : __('Define a new shipping method.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button href="{{ route('shipping-settings.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Save Method') }}
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">

            {{-- Main (2/3): Shipping Method form --}}
            <div class="md:col-span-2 space-y-6">

                {{-- Core details --}}
                <flux:card class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Method Details') }}</flux:heading>
                            <flux:subheading>{{ __('Name, carrier and zone.') }}</flux:subheading>
                        </div>
                        <flux:switch wire:model="methodIsActive" label="{{ __('Active') }}" />
                    </div>

                    <flux:input wire:model="methodName" label="{{ __('Name') }}"
                        placeholder="{{ __('e.g. Standard Shipping, Express Delivery') }}" />

                    <div class="grid grid-cols-2 gap-4 items-start">
                        <flux:select wire:model="methodCarrierId" label="{{ __('Carrier') }}">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach ($this->activeCarriers as $carrier)
                                <option value="{{ $carrier->id }}">{{ $carrier->name }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="methodZoneId" label="{{ __('Zone') }}">
                            <option value="">{{ __('— All zones —') }}</option>
                            @foreach ($this->zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </flux:card>

                {{-- Fee calculator --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Fee Calculator') }}</flux:heading>
                        <flux:subheading>{{ __('How shipping costs are calculated.') }}</flux:subheading>
                    </div>

                    <flux:select wire:model.live="methodCalculator" label="{{ __('Calculator') }}">
                        @foreach ($this->calculatorChoices as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>

                    @if (in_array($methodCalculator, ['flat_fee', 'discountable_shipping_fee', 'payment_dependent_fee']))
                        <flux:input wire:model="configTitle" label="{{ __('Fee Title') }}"
                            placeholder="{{ __('Shipping fee') }}"
                            description="{{ __('Shown on invoices and order summaries.') }}" />

                        <div class="grid grid-cols-2 gap-4 items-start">
                            <flux:input wire:model="configCost" type="number" step="0.01" min="0"
                                label="{{ __('Shipping Cost') }}" placeholder="7.99" />

                            <div>
                                <flux:input wire:model="configFreeThreshold" type="number" step="0.01"
                                    min="0" label="{{ __('Free Shipping Threshold') }}"
                                    placeholder="{{ __('e.g. 100.00') }}" />
                                <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Leave empty to disable.') }}</flux:text>
                            </div>
                        </div>
                    @endif

                    @if ($methodCalculator === 'discountable_shipping_fee')
                        <flux:separator />
                        <flux:heading size="sm">{{ __('Discount Tier') }}</flux:heading>

                        <div class="grid grid-cols-2 gap-4 items-start">
                            <flux:input wire:model="configDiscountedThreshold" type="number" step="0.01"
                                min="0" label="{{ __('Discount Threshold') }}"
                                placeholder="{{ __('e.g. 50.00') }}" />

                            <flux:input wire:model="configDiscountedCost" type="number" step="0.01" min="0"
                                label="{{ __('Discounted Cost') }}" placeholder="{{ __('e.g. 4.99') }}" />
                        </div>
                    @endif
                </flux:card>

            </div>

            {{-- Sidebar (1/3): Carrier CRUD + Delivery & Status --}}
            <div class="space-y-6 sticky top-6">
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Carriers') }}</flux:heading>
                        <flux:subheading>{{ __('Manage carriers used by shipping methods.') }}</flux:subheading>
                    </div>

                    {{-- Carrier list --}}
                    @if ($this->carriers->isEmpty())
                        <flux:text class="text-sm text-zinc-500">{{ __('No carriers yet.') }}</flux:text>
                    @else
                        <div @class([
                            'divide-y divide-zinc-100 dark:divide-zinc-800',
                            'max-h-[240px] overflow-y-auto pr-1' => $this->carriers->count() >= 4,
                        ])>
                            @foreach ($this->carriers as $carrier)
                                <div @class([
                                    'flex items-center justify-between py-2.5 px-1 rounded-lg',
                                    'bg-blue-50 dark:bg-blue-900/10 px-2' => $editingCarrierId === $carrier->id,
                                ])>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <flux:text class="text-sm font-medium">{{ $carrier->name }}</flux:text>
                                            @if ($carrier->is_active)
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">{{ __('Active') }}</span>
                                            @else
                                                <span
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">{{ __('Inactive') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex gap-0.5 shrink-0">
                                        <flux:button size="xs" variant="ghost" icon="pencil"
                                            wire:click="openEditCarrier({{ $carrier->id }})" type="button" />
                                        <flux:button size="xs" variant="ghost" icon="trash"
                                            class="text-red-500 hover:text-red-700"
                                            wire:click="deleteCarrier({{ $carrier->id }})"
                                            wire:confirm="{{ __('Are you sure you want to delete this carrier?') }}"
                                            type="button" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <flux:separator />

                    {{-- Inline create / edit form --}}
                    <div class="space-y-3">
                        <flux:heading size="sm">
                            {{ $editingCarrierId ? __('Edit Carrier') : __('New Carrier') }}
                        </flux:heading>

                        <flux:input wire:model="carrierName" placeholder="{{ __('e.g. DHL, UPS, FedEx') }}"
                            size="sm" />
                        <flux:error name="carrierName" />

                        <flux:switch wire:model="carrierIsActive" label="{{ __('Active') }}" />

                        <div class="flex gap-2 pt-1">
                            @if ($editingCarrierId)
                                <flux:button size="sm" variant="ghost" type="button"
                                    wire:click="openCreateCarrier">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @endif
                            <flux:button size="sm" variant="primary" type="button" wire:click="saveCarrier"
                                class="flex-1">
                                {{ $editingCarrierId ? __('Update') : __('Add Carrier') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>

                {{-- Delivery & status --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Delivery & Status') }}</flux:heading>
                        <flux:subheading>{{ __('Estimated delivery window and active state.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="methodEtaMin" type="number" min="0"
                        label="{{ __('Min Delivery Days') }}" placeholder="3" />

                    <flux:input wire:model="methodEtaMax" type="number" min="0"
                        label="{{ __('Max Delivery Days') }}" placeholder="7" />
                </flux:card>
            </div>

        </div>
    </form>
</div>

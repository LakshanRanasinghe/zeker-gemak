<?php

use App\Models\CountryShippingRule;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public ?CountryShippingRule $shippingRule = null;

    public string $countryCode = '';

    public string $countryName = '';

    public string $shippingCost = '0.00';

    public string $freeShippingThreshold = '0.00';

    public bool $isActive = true;

    public function mount(?CountryShippingRule $shippingRule = null): void
    {
        if (! $shippingRule?->exists) {
            return;
        }

        $this->shippingRule = $shippingRule;
        $this->countryCode = $shippingRule->country_code;
        $this->countryName = $shippingRule->country_name;
        $this->shippingCost = $shippingRule->shipping_cost;
        $this->freeShippingThreshold = $shippingRule->free_shipping_threshold;
        $this->isActive = $shippingRule->is_active;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'countryCode' => [
                'required',
                'string',
                'size:2',
                Rule::unique('country_shipping_rules', 'country_code')->ignore($this->shippingRule),
            ],
            'countryName' => ['required', 'string', 'max:255'],
            'shippingCost' => ['required', 'decimal:2', 'min:0'],
            'freeShippingThreshold' => ['required', 'decimal:2', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        CountryShippingRule::query()->updateOrCreate(
            ['id' => $this->shippingRule?->id],
            [
                'country_code' => strtoupper($validated['countryCode']),
                'country_name' => $validated['countryName'],
                'shipping_cost' => $validated['shippingCost'],
                'free_shipping_threshold' => $validated['freeShippingThreshold'],
                'is_active' => $validated['isActive'],
            ],
        );

        Flux::toast($this->shippingRule ? __('Shipping rule updated.') : __('Shipping rule created.'), variant: 'success');
        $this->redirect(route('shipping-cost.index'), navigate: true);
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $shippingRule ? __('Edit Shipping Rule') : __('Create Shipping Rule') }}</flux:heading>
                <flux:subheading>{{ __('Define the country rate used by checkout.') }}</flux:subheading>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <flux:button href="{{ route('shipping-cost.index') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">{{ __('Save Rule') }}</flux:button>
            </div>
        </div>

        <flux:card class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <flux:input wire:model="countryCode" label="{{ __('Country Code') }}" maxlength="2" placeholder="NL" />
            <flux:input wire:model="countryName" label="{{ __('Country Name') }}" placeholder="Netherlands" />
            <flux:input wire:model="shippingCost" type="number" min="0" step="0.01" icon="currency-euro" label="{{ __('Shipping Cost (€)') }}" />
            <flux:input wire:model="freeShippingThreshold" type="number" min="0" step="0.01" icon="currency-euro" label="{{ __('Free Shipping Threshold (€)') }}" />
            <flux:switch wire:model="isActive" label="{{ __('Active') }}" />
        </flux:card>
    </form>
</div>

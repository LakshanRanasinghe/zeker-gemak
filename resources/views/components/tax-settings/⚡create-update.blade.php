<?php

use Livewire\Component;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Taxes\Models\TaxCategoryType;
use Vanilo\Taxes\Models\TaxRate;
use Vanilo\Taxes\TaxCalculators;
use Konekt\Address\Models\Zone;
use Flux\Flux;

new class extends Component {
    public ?TaxRate $taxRate = null;

    public string $rateName = '';
    public ?string $rateTaxCategoryId = null;
    public ?string $rateZoneId = null;
    public string $rateValue = '0';
    public string $rateCalculator = 'default';
    public bool $rateIsActive = true;
    public ?string $rateValidFrom = null;
    public ?string $rateValidUntil = null;
    public string $rateConfigTitle = '';
    public bool $rateConfigIncluded = false;

    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public string $categoryType = 'physical_goods';
    public bool $categoryIsActive = true;
    public bool $categoryIsDefault = false;

    public function getCategoriesProperty()
    {
        return TaxCategory::orderBy('name')->get();
    }

    public function getCategoryTypesProperty(): array
    {
        return [
            'physical_goods'             => (new TaxCategoryType('physical_goods'))->label(),
            'digital_goods_and_services' => (new TaxCategoryType('digital_goods_and_services'))->label(),
            'transport_services'         => (new TaxCategoryType('transport_services'))->label(),
        ];
    }

    public function getActiveCategoriesProperty()
    {
        return TaxCategory::where('is_active', true)->orderBy('name')->get();
    }

    public function getZonesProperty()
    {
        return Zone::orderBy('name')->get();
    }

    public function getCalculatorChoicesProperty(): array
    {
        return TaxCalculators::choices();
    }

    public function mount(?TaxRate $taxRate = null): void
    {
        if ($taxRate && $taxRate->exists) {
            $this->taxRate = $taxRate;
            $this->rateName = $taxRate->name;
            $this->rateTaxCategoryId = $taxRate->tax_category_id ? (string) $taxRate->tax_category_id : null;
            $this->rateZoneId = $taxRate->zone_id ? (string) $taxRate->zone_id : null;
            $this->rateValue = (string) $taxRate->rate;
            $this->rateCalculator = $taxRate->calculator ?? 'default';
            $this->rateIsActive = (bool) $taxRate->is_active;
            $this->rateValidFrom = $taxRate->valid_from?->format('Y-m-d');
            $this->rateValidUntil = $taxRate->valid_until?->format('Y-m-d');

            $config = $taxRate->configuration ?? [];
            $this->rateConfigTitle = $config['title'] ?? '';
            $this->rateConfigIncluded = $config['included'] ?? false;
        }
    }


    public function save(): void
    {
        $rateId = $this->taxRate?->id;

        $this->validate([
            'rateName'          => 'required|string|max:255',
            'rateTaxCategoryId' => 'required|exists:tax_categories,id',
            'rateZoneId'        => 'nullable|exists:zones,id',
            'rateValue'         => 'required|numeric|min:0|max:100',
            'rateCalculator'    => 'required|string',
            'rateValidFrom'     => 'nullable|date',
            'rateValidUntil'    => 'nullable|date|after_or_equal:rateValidFrom',
        ], [
            'rateName.required'          => __('Tax rate name is required.'),
            'rateTaxCategoryId.required' => __('Please select a tax category.'),
            'rateValue.required'         => __('Rate percentage is required.'),
            'rateValidUntil.after_or_equal' => __('Valid until must be after valid from.'),
        ]);

        // Prevent duplicate category+zone combination
        $duplicate = TaxRate::where('tax_category_id', $this->rateTaxCategoryId)
            ->where('zone_id', filled($this->rateZoneId) ? $this->rateZoneId : null)
            ->when($rateId, fn ($q) => $q->where('id', '!=', $rateId))
            ->exists();

        if ($duplicate) {
            $this->addError('rateTaxCategoryId', __('A tax rate for this category and zone already exists.'));
            return;
        }

        $data = [
            'name'            => $this->rateName,
            'tax_category_id' => filled($this->rateTaxCategoryId) ? $this->rateTaxCategoryId : null,
            'zone_id'         => filled($this->rateZoneId) ? $this->rateZoneId : null,
            'rate'            => (float) $this->rateValue,
            'calculator'      => $this->rateCalculator,
            'configuration'   => $this->buildRateConfiguration(),
            'is_active'       => $this->rateIsActive,
            'valid_from'      => filled($this->rateValidFrom) ? $this->rateValidFrom : null,
            'valid_until'     => filled($this->rateValidUntil) ? $this->rateValidUntil : null,
        ];

        if ($this->taxRate && $this->taxRate->exists) {
            $this->taxRate->update($data);
            Flux::toast(__('Tax rate updated.'), variant: 'success');
        } else {
            TaxRate::create($data);
            Flux::toast(__('Tax rate created.'), variant: 'success');
        }

        $this->redirect(route('tax-settings.index'), navigate: true);
    }

    protected function buildRateConfiguration(): array
    {
        if ($this->rateCalculator === 'none') {
            return [];
        }

        return array_filter([
            'rate'     => (float) $this->rateValue,
            'title'    => filled($this->rateConfigTitle) ? $this->rateConfigTitle : null,
            'included' => $this->rateConfigIncluded,
        ], fn ($v) => $v !== null);
    }


    public function openCreateCategory(): void
    {
        $this->resetValidation(['categoryName', 'categoryType']);
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categoryType = 'physical_goods';
        $this->categoryIsActive = true;
        $this->categoryIsDefault = false;
    }

    public function openEditCategory(int $id): void
    {
        $this->resetValidation(['categoryName', 'categoryType']);
        $cat = TaxCategory::findOrFail($id);
        $this->editingCategoryId = $cat->id;
        $this->categoryName = $cat->name;
        $this->categoryType = $cat->type->value();
        $this->categoryIsActive = (bool) $cat->is_active;
        $this->categoryIsDefault = (bool) $cat->is_default;
    }

    public function saveCategory(): void
    {
        $uniqueRule = 'unique:tax_categories,name' . ($this->editingCategoryId ? ',' . $this->editingCategoryId : '');

        $this->validate([
            'categoryName' => 'required|string|max:255|' . $uniqueRule,
            'categoryType' => 'required|in:physical_goods,digital_goods_and_services,transport_services',
        ], [
            'categoryName.required' => __('Category name is required.'),
            'categoryName.unique'   => __('A category with this name already exists.'),
            'categoryType.in'       => __('Invalid category type.'),
        ]);

        $data = [
            'name'       => $this->categoryName,
            'type'       => $this->categoryType,
            'is_active'  => $this->categoryIsActive,
            'is_default' => $this->categoryIsDefault,
        ];

        if ($this->categoryIsDefault) {
            TaxCategory::where('id', '!=', $this->editingCategoryId)->update(['is_default' => false]);
        }

        if ($this->editingCategoryId) {
            $category = TaxCategory::findOrFail($this->editingCategoryId);
            $category->update($data);
            Flux::toast(__('Tax category updated.'), variant: 'success');
        } else {
            $category = TaxCategory::create($data);
            Flux::toast(__('Tax category created.'), variant: 'success');
        }

        unset($this->categories, $this->activeCategories);

        if ($category->is_active) {
            $this->rateTaxCategoryId = (string) $category->id;
        }

        $this->openCreateCategory();
    }

    public function deleteCategory(int $id): void
    {
        if (TaxRate::where('tax_category_id', $id)->exists()) {
            Flux::toast(__('Cannot delete: this category has tax rates assigned.'), variant: 'danger');
            return;
        }

        TaxCategory::findOrFail($id)->delete();

        if ($this->editingCategoryId === $id) {
            $this->openCreateCategory();
        }

        Flux::toast(__('Tax category deleted.'), variant: 'success');
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
                    {{ $taxRate ? __('Edit Tax Rate') : __('Create Tax Rate') }}
                </flux:heading>
                <flux:subheading>
                    {{ $taxRate ? __('Update the tax rate details.') : __('Define a new tax rate.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button href="{{ route('tax-settings.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Save Rate') }}
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">

            {{-- Main (2/3): Tax Rate form --}}
            <div class="md:col-span-2 space-y-6">

                {{-- Core details --}}
                <flux:card class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Rate Details') }}</flux:heading>
                            <flux:subheading>{{ __('Name, category, zone and percentage.') }}</flux:subheading>
                        </div>
                        <flux:switch wire:model="rateIsActive" label="{{ __('Active') }}" />
                    </div>

                    <flux:input wire:model="rateName" label="{{ __('Name') }}"
                        placeholder="{{ __('e.g. German VAT 19%') }}" />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <flux:select
                            wire:model="rateTaxCategoryId"
                            wire:key="rate-tax-category-{{ $this->activeCategories->pluck('id')->join('-') }}"
                            label="{{ __('Tax Category') }}"
                        >
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($this->activeCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="rateZoneId" label="{{ __('Zone') }}">
                            <option value="">{{ __('— All zones —') }}</option>
                            @foreach($this->zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    @if($this->activeCategories->isEmpty())
                        <flux:text class="-mt-4 text-sm text-zinc-500">
                            {{ __('Add an active category in the Tax Categories panel first.') }}
                        </flux:text>
                    @endif

                    <div class="grid grid-cols-2 gap-4 items-start">
                        <flux:input wire:model="rateValue" type="number" step="0.01" min="0" max="100"
                            label="{{ __('Rate (%)') }}" placeholder="19.00" />

                        <flux:select wire:model.live="rateCalculator" label="{{ __('Calculator') }}">
                            @foreach($this->calculatorChoices as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </flux:card>

                {{-- Calculator config --}}
                @if($rateCalculator !== 'none')
                    <flux:card class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Calculator Config') }}</flux:heading>
                            <flux:subheading>{{ __('How this tax is displayed and applied.') }}</flux:subheading>
                        </div>

                        <flux:input wire:model="rateConfigTitle" label="{{ __('Display Title') }}"
                            placeholder="{{ __('e.g. VAT, Sales Tax') }}"
                            description="{{ __('Shown on invoices. Leave empty to use rate %.') }}" />

                        <flux:switch wire:model="rateConfigIncluded" label="{{ __('Tax included in price') }}"
                            description="{{ __('Enable if product prices already include this tax.') }}" />
                    </flux:card>
                @endif

                {{-- Validity & status --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Validity & Status') }}</flux:heading>
                        <flux:subheading>{{ __('Optional date range and active state.') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-2 gap-4 items-start">
                        <flux:input wire:model="rateValidFrom" type="date" label="{{ __('Valid From') }}" />
                        <flux:input wire:model="rateValidUntil" type="date" label="{{ __('Valid Until') }}" />
                    </div>
                </flux:card>
            </div>

            {{-- Sidebar (1/3): Tax Categories CRUD --}}
            <div class="sticky top-6">
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Tax Categories') }}</flux:heading>
                        <flux:subheading>{{ __('Manage categories used by tax rates.') }}</flux:subheading>
                    </div>

                    {{-- Category list --}}
                    @if($this->categories->isEmpty())
                        <flux:text class="text-sm text-zinc-500">{{ __('No categories yet.') }}</flux:text>
                    @else
                        <div @class([
                            'divide-y divide-zinc-100 dark:divide-zinc-800',
                            'max-h-[240px] overflow-y-auto pr-1' => $this->categories->count() >= 4,
                        ])>
                            @foreach($this->categories as $cat)
                                <div @class([
                                    'flex items-center justify-between py-2.5 px-1 rounded-lg',
                                    'bg-blue-50 dark:bg-blue-900/10 px-2' => $editingCategoryId === $cat->id,
                                ])>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <flux:text class="text-sm font-medium">{{ $cat->name }}</flux:text>
                                            @if($cat->is_default)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">{{ __('Default') }}</span>
                                            @endif
                                            @if($cat->is_active)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">{{ __('Active') }}</span>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">{{ __('Inactive') }}</span>
                                            @endif
                                        </div>
                                        <flux:text class="text-xs text-zinc-500">
                                            {{ ucfirst(str_replace('_', ' ', $cat->type->value())) }}
                                        </flux:text>
                                    </div>
                                    <div class="flex gap-0.5 shrink-0">
                                        <flux:button size="xs" variant="ghost" icon="pencil"
                                            wire:click="openEditCategory({{ $cat->id }})" type="button" />
                                        <flux:button size="xs" variant="ghost" icon="trash"
                                            class="text-red-500 hover:text-red-700"
                                            wire:click="deleteCategory({{ $cat->id }})"
                                            wire:confirm="{{ __('Are you sure you want to delete this tax category?') }}"
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
                            {{ $editingCategoryId ? __('Edit Category') : __('New Category') }}
                        </flux:heading>

                        <flux:input wire:model="categoryName" placeholder="{{ __('Category name...') }}" size="sm" />
                        <flux:error name="categoryName" />

                        <flux:select wire:model="categoryType" size="sm">
                            @foreach($this->categoryTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>

                        <flux:switch wire:model="categoryIsActive" label="{{ __('Active') }}" />
                        <flux:switch wire:model="categoryIsDefault" label="{{ __('Default') }}" />

                        <div class="flex gap-2 pt-1">
                            @if($editingCategoryId)
                                <flux:button size="sm" variant="ghost" type="button" wire:click="openCreateCategory">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @endif
                            <flux:button size="sm" variant="primary" type="button" wire:click="saveCategory" class="flex-1">
                                {{ $editingCategoryId ? __('Update') : __('Add Category') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            </div>

        </div>
    </form>
</div>

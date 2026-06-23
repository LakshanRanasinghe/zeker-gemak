<?php

use Livewire\Component;
use Konekt\Address\Models\Zone;
use Konekt\Address\Models\Country;
use Flux\Flux;

new class extends Component {
    public ?Zone $zone = null;

    public string $name = '';
    public string $scope = 'shipping';
    public array $selectedCountries = [];
    public string $countrySearch = '';

    public function mount(?Zone $zone = null)
    {
        if ($zone && $zone->exists) {
            $this->zone = $zone;
            $this->name = $zone->name;
            $this->scope = $zone->scope->value();

            // Load existing country members — use isCountry() to safely filter enum
            $this->selectedCountries = $zone->members->filter(fn($m) => $m->isCountry())->pluck('member_id')->values()->toArray();
        }
    }

    public function getCountriesProperty()
    {
        $query = Country::query();

        if (filled($this->countrySearch)) {
            $query->where('name', 'like', '%' . $this->countrySearch . '%');
        }

        return $query->orderBy('name')->get();
    }

    public function getSelectedCountryNamesProperty()
    {
        if (empty($this->selectedCountries)) {
            return collect();
        }

        return Country::whereIn('id', $this->selectedCountries)->orderBy('name')->get();
    }

    public function toggleCountry(string $countryId)
    {
        if (in_array($countryId, $this->selectedCountries)) {
            $this->selectedCountries = array_values(array_diff($this->selectedCountries, [$countryId]));
        } else {
            $this->selectedCountries[] = $countryId;
        }
    }

    public function removeCountry(string $countryId)
    {
        $this->selectedCountries = array_values(array_diff($this->selectedCountries, [$countryId]));
    }

    public function save()
    {
        $uniqueRule = 'unique:zones,name' . ($this->zone?->id ? ',' . $this->zone->id : '');

        $this->validate(
            [
                'name' => 'required|string|max:255|' . $uniqueRule,
                'scope' => 'required|in:shipping,billing,taxation,pricing,content',
                'selectedCountries' => 'required|array|min:1',
            ],
            [
                'name.required' => __('Zone name is required.'),
                'name.unique' => __('A zone with this name already exists.'),
                'scope.in' => __('Invalid scope.'),
                'selectedCountries.required' => __('Please select at least one country.'),
                'selectedCountries.min' => __('Please select at least one country.'),
            ],
        );

        if ($this->zone && $this->zone->exists) {
            $this->zone->update([
                'name' => $this->name,
                'scope' => $this->scope,
            ]);
            // Wipe existing members and re-add
            $this->zone->members()->delete();
            foreach ($this->selectedCountries as $code) {
                $this->zone->addCountry($code);
            }
            Flux::toast(__('Zone updated successfully.'), variant: 'success');
        } else {
            $zone = Zone::create([
                'name' => $this->name,
                'scope' => $this->scope,
            ]);
            foreach ($this->selectedCountries as $code) {
                $zone->addCountry($code);
            }
            Flux::toast(__('Zone created successfully.'), variant: 'success');
        }

        $this->redirect(route('zones.index'), navigate: true);
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
                    {{ $zone ? __('Edit Zone') : __('Create Zone') }}
                </flux:heading>
                <flux:subheading>
                    {{ $zone ? __('Update zone details and member countries.') : __('Define a new geographical zone.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button href="{{ route('zones.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Save Zone') }}
                </flux:button>
            </div>
        </div>

        {{-- 2-col layout --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">

            {{-- Main (2/3) --}}
            <div class="md:col-span-2 space-y-6">

                {{-- Zone details --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Zone Details') }}</flux:heading>
                        <flux:subheading>{{ __('Name and purpose of this zone.') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-2 gap-4 items-start">
                        <flux:input wire:model="name" label="{{ __('Name') }}"
                            placeholder="{{ __('e.g. European Union, North America') }}" />

                        <flux:select wire:model="scope" label="{{ __('Scope') }}">
                            <option value="shipping">{{ __('Shipping') }}</option>
                            <option value="billing">{{ __('Billing') }}</option>
                            <option value="taxation">{{ __('Taxation') }}</option>
                            <option value="pricing">{{ __('Pricing') }}</option>
                            <option value="content">{{ __('Content') }}</option>
                        </flux:select>
                    </div>
                </flux:card>

                {{-- Country picker --}}
                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('Member Countries') }}</flux:heading>
                        <flux:subheading>{{ __('Select the countries that belong to this zone.') }}</flux:subheading>
                    </div>

                    @error('selectedCountries')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    {{-- Selected country tags --}}
                    @if (count($selectedCountries) > 0)
                        <div
                            class="flex flex-wrap gap-1.5 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            @foreach ($this->selectedCountryNames as $country)
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                    {{ $country->name }}
                                    <button type="button" wire:click="removeCountry('{{ $country->id }}')"
                                        class="ml-0.5 hover:text-blue-600 dark:hover:text-blue-100 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Search --}}
                    <flux:input wire:model.live.debounce.300ms="countrySearch"
                        placeholder="{{ __('Search countries...') }}" icon="magnifying-glass" clearable />

                    {{-- Scrollable list --}}
                    <div
                        class="max-h-72 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($this->countries as $country)
                            <label
                                class="flex items-center gap-3 px-4 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 cursor-pointer transition-colors
                                    {{ in_array($country->id, $selectedCountries) ? 'bg-blue-50 dark:bg-blue-900/10' : '' }}">
                                <input type="checkbox" wire:click="toggleCountry('{{ $country->id }}')"
                                    @checked(in_array($country->id, $selectedCountries))
                                    class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500 dark:bg-zinc-700" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $country->name }}</span>
                                <span class="ml-auto text-xs text-zinc-400 font-mono">{{ $country->id }}</span>
                            </label>
                        @empty
                            <div class="px-4 py-6 text-center text-sm text-zinc-500">
                                {{ __('No countries match your search.') }}
                            </div>
                        @endforelse
                    </div>

                    <flux:text class="text-zinc-500 text-xs">
                        {{ __(':count countries selected', ['count' => count($selectedCountries)]) }}
                    </flux:text>
                </flux:card>
            </div>

            {{-- Sidebar (1/3) --}}
            <div class="space-y-6 sticky top-6">

                @if ($zone)
                    <flux:card class="space-y-4">
                        <flux:heading size="lg">{{ __('Info') }}</flux:heading>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Created') }}</flux:text>
                                <flux:text>{{ $zone->created_at->format('M d, Y') }}</flux:text>
                            </div>
                            <flux:separator />
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Updated') }}</flux:text>
                                <flux:text>{{ $zone->updated_at->format('M d, Y') }}</flux:text>
                            </div>
                            <flux:separator />
                            <div class="flex justify-between">
                                <flux:text class="text-zinc-500">{{ __('Countries') }}</flux:text>
                                <flux:text>{{ count($selectedCountries) }}</flux:text>
                            </div>
                        </div>
                    </flux:card>
                @endif

                {{-- Help guide --}}
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Guide') }}</flux:heading>
                    <div class="space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                        <p>{{ __('A zone groups countries for a specific purpose, controlled by its scope.') }}</p>
                        <flux:separator />
                        <div class="space-y-2">
                            <p><span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Shipping') }}</span> —
                                {{ __('Used by shipping methods to apply rates per region.') }}</p>
                            <p><span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Billing') }}</span> —
                                {{ __('Groups countries for invoice or billing rules.') }}</p>
                            <p><span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Taxation') }}</span> —
                                {{ __('Defines where a tax rate applies.') }}</p>
                            <p><span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Pricing') }}</span> —
                                {{ __('Enables different prices per region.') }}</p>
                            <p><span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Content') }}</span> —
                                {{ __('Controls content visibility by country.') }}</p>
                        </div>
                    </div>
                </flux:card>

            </div>
        </div>
    </form>
</div>

<?php

use App\Models\WarrantyGroup;
use App\Services\WarrantyGroupOptionService;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

new class extends Component {
    public ?WarrantyGroup $warrantyGroup = null;

    public string $name = '';

    public string $description = '';

    public string $is_active = '1';

    public array $warranty_options = [];

    public function mount(?WarrantyGroup $warrantyGroup = null): void
    {
        if ($warrantyGroup && $warrantyGroup->exists) {
            $this->warrantyGroup = $warrantyGroup->load('options');
            $this->name = (string) $warrantyGroup->name;
            $this->description = (string) ($warrantyGroup->description ?? '');
            $this->is_active = $warrantyGroup->is_active ? '1' : '0';
            $this->warranty_options = $warrantyGroup->options->map(fn($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'duration_months' => $option->duration_months,
                'price' => $option->price,
                'description' => $option->description ?? '',
                'is_default' => $option->is_default,
                'is_active' => $option->is_active,
                'sort_order' => $option->sort_order,
            ])->toArray();
        }

        if ($this->warranty_options === []) {
            $this->addWarrantyOption(default: true);
        }
    }

    public function save(): void
    {
        $validator = Validator::make($this->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'required|in:0,1',
            'warranty_options' => 'required|array|min:1',
            'warranty_options.*.id' => 'nullable|integer|exists:product_warranty_options,id',
            'warranty_options.*.name' => 'required|string|max:255',
            'warranty_options.*.duration_months' => 'required|integer|min:0',
            'warranty_options.*.price' => 'required|numeric|gte:0',
            'warranty_options.*.description' => 'nullable|string|max:500',
            'warranty_options.*.is_default' => 'boolean',
            'warranty_options.*.is_active' => 'boolean',
            'warranty_options.*.sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $validated = $validator->validated();
        $validated['is_active'] = $validated['is_active'] === '1';

        try {
            app(WarrantyGroupOptionService::class)->validateDefaultOption($validated['warranty_options']);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $group = $this->warrantyGroup && $this->warrantyGroup->exists
            ? tap($this->warrantyGroup)->update(collect($validated)->only(['name', 'description', 'is_active'])->all())
            : WarrantyGroup::create(collect($validated)->only(['name', 'description', 'is_active'])->all());

        app(WarrantyGroupOptionService::class)->sync($group, $validated['warranty_options']);

        $group->reindexAssignedProducts();

        Flux::toast($this->warrantyGroup ? __('Warranty group updated successfully.') : __('Warranty group created successfully.'), variant: 'success');

        $this->redirect(route('warranty-groups.index'), navigate: true);
    }

    public function addWarrantyOption(bool $default = false): void
    {
        $this->warranty_options[] = [
            'id' => null,
            'name' => '',
            'duration_months' => 0,
            'price' => 0.00,
            'description' => '',
            'is_default' => $default,
            'is_active' => true,
            'sort_order' => count($this->warranty_options),
        ];
    }

    public function removeWarrantyOption(int $index): void
    {
        unset($this->warranty_options[$index]);
        $this->warranty_options = array_values($this->warranty_options);
    }

    public function markDefault(int $index): void
    {
        foreach ($this->warranty_options as $optionIndex => $option) {
            $this->warranty_options[$optionIndex]['is_default'] = $optionIndex === $index;
        }

        $this->warranty_options[$index]['price'] = 0;
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ $warrantyGroup ? __('Edit Warranty Group') : __('Create Warranty Group') }}
                </flux:heading>
                <flux:subheading>{{ __('Configure reusable warranty options for product assignment.') }}
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('warranty-groups.index') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <flux:card class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input wire:model="name" label="{{ __('Name') }}"
                    placeholder="{{ __('Standard product warranty') }}" />
                <flux:select wire:model="is_active" label="{{ __('Status') }}">
                    <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model="description" label="{{ __('Description') }}" rows="3" />
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Warranty Options') }}</flux:heading>
                    <flux:subheading>{{ __('One active option must be selected as the free default.') }}
                    </flux:subheading>
                </div>
                <flux:button type="button" variant="ghost" icon="plus" wire:click="addWarrantyOption">
                    {{ __('Add Option') }}
                </flux:button>
            </div>

            <flux:error name="warranty_options" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($warranty_options as $index => $option)
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-4"
                        wire:key="warranty-option-{{ $index }}">
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading size="sm">{{ __('Option') }} {{ $index + 1 }}</flux:heading>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <flux:checkbox wire:model="warranty_options.{{ $index }}.is_active">{{ __('Active') }}
                                </flux:checkbox>
                                <flux:button type="button" size="sm"
                                    variant="{{ $option['is_default'] ? 'primary' : 'ghost' }}"
                                    wire:click="markDefault({{ $index }})">
                                    {{ __('Default') }}
                                </flux:button>
                                <flux:button type="button" size="sm" variant="danger" icon="trash"
                                    wire:click="removeWarrantyOption({{ $index }})" />
                            </div>
                        </div>

                        <input type="hidden" wire:model="warranty_options.{{ $index }}.id">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input wire:model="warranty_options.{{ $index }}.name" label="{{ __('Name') }}" />
                            <flux:input wire:model="warranty_options.{{ $index }}.duration_months"
                                label="{{ __('Duration') }}" type="number" min="0" />
                            <flux:input wire:model="warranty_options.{{ $index }}.price" label="{{ __('Price') }}"
                                type="number" min="0" step="0.01" />
                            <flux:input wire:model="warranty_options.{{ $index }}.sort_order" label="{{ __('Sort') }}"
                                type="number" min="0" />
                        </div>

                        <flux:textarea wire:model="warranty_options.{{ $index }}.description"
                            label="{{ __('Description') }}" rows="2" />

                        <flux:error name="warranty_options.{{ $index }}.name" />
                        <flux:error name="warranty_options.{{ $index }}.duration_months" />
                        <flux:error name="warranty_options.{{ $index }}.price" />
                    </div>
                @endforeach
            </div>
        </flux:card>
    </form>
</div>

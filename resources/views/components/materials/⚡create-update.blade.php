<?php

use App\Models\Material;
use Vanilo\Category\Models\Taxon;
use Vanilo\Category\Models\Taxonomy;
use App\Concerns\HandlesWysiwygMedia;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Vanilo\Translation\Models\Translation;

new class extends Component {
    use HandlesWysiwygMedia;
    use WithFileUploads;

    public ?Material $material = null;
    public bool $editMode = false;

    public string $selectedLocale = '';

    public array $translations = [];

    /** Temporary FilePond upload for the optional spec-sheet PDF. */
    public $spec_pdf = null;

    #[Validate('required|string|max:255', message: 'Enter a material title.')]
    public string $title = '';

    #[Validate('nullable|string|max:255')]
    public string $subtitle = '';

    #[Validate('nullable|string|max:255')]
    public string $slug = '';

    #[Validate('nullable|string')]
    public string $description = '';

    public array $taxon_ids = [];

    #[Validate('nullable|string|max:255')]
    public string $new_category_name = '';

    public ?int $editingCategoryId = null;

    public array $specs = [];

    public $code = '';
    public $brand = '';
    public $status = '';
    public $print_method = '';
    public $base_material = '';
    public $finish = '';
    public $adhesive = '';
    public $supplier = '';
    public $supplier_reference = '';
    public $price_per_sq_meter = '';
    public $certificate = 'none';

    protected function initTranslations(): void
    {
        foreach (array_keys($this->locales()) as $locale) {
            $this->translations[$locale] = [
                'title' => '',
                'subtitle' => '',
                'slug' => '',
                'description' => '',
                'specs' => [],
            ];
        }
    }

    public function hydrate(): void
    {
        if ($this->selectedLocale) {
            app()->setLocale($this->selectedLocale);
        }
    }

    public function switchLocale(string $locale): void
    {
        if ($locale === $this->selectedLocale || !array_key_exists($locale, $this->locales())) {
            return;
        }

        $this->storeFieldsToTranslations();
        $this->loadFieldsFromTranslations($locale);
        $this->selectedLocale = $locale;
        app()->setLocale($locale);
    }

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:materials,slug' . ($this->material ? ',' . $this->material->id : ''),
            'description' => 'nullable|string',
            'taxon_ids' => 'nullable|array',
            'taxon_ids.*' => 'exists:taxons,id',
            'new_category_name' => 'nullable|string|max:255',
            'specs' => 'nullable|array',
            'specs.*.label' => 'required|string|max:255',
            'specs.*.value' => 'required|string|max:255',

            'code' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'status' => 'required|string|in:active,in_testing,phase_out,archived,rejected',
            'print_method' => 'nullable|string|max:255',
            'base_material' => 'required|string|max:255',
            'finish' => 'nullable|string|max:255',
            'adhesive' => 'required|string|max:255',
            'supplier' => 'required|string|max:255',
            'supplier_reference' => 'required|string|max:255',
            'price_per_sq_meter' => 'nullable|numeric',
            'certificate' => 'nullable|string|max:255',

            'spec_pdf' => 'nullable|file|mimes:pdf|max:20480',
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => __('Enter a material title.'),
            'slug.unique' => __('This slug already exists.'),
            'taxon_ids.required' => __('Please select at least one category.'),
            'specs.*.label.required' => __('Spec label is required.'),
            'specs.*.value.required' => __('Spec value is required.'),

            'code.required' => __('Enter a material code.'),
            'brand.required' => __('Enter a material brand.'),
            'status.required' => __('Enter a material status.'),
            'print_method.required' => __('Enter a material print method.'),
            'base_material.required' => __('Enter a material base material.'),
            'finish.required' => __('Enter a material finish.'),
            'adhesive.required' => __('Enter a material adhesive.'),
            'supplier.required' => __('Enter a material supplier.'),
            'supplier_reference.required' => __('Enter a material supplier reference.'),
            'price_per_sq_meter.required' => __('Enter a material price per sq meter.'),
            'certificate.required' => __('Enter a material certificate.'),

            'spec_pdf.mimes' => __('The spec sheet must be a PDF file.'),
            'spec_pdf.max' => __('The spec sheet may not be larger than 20MB.'),
        ];
    }

    public function statusOptions(): array
    {
        return [
            'active' => __('Active'),
            'in_testing' => __('In Testing'),
            'phase_out' => __('Phase Out'),
            'archived' => __('Archived'),
            'rejected' => __('Rejected'),
        ];
    }

    public function mount(?Material $material = null)
    {
        $this->selectedLocale = $this->mainLocale();
        $this->initTranslations();
        app()->setLocale($this->selectedLocale);

        if ($material && $material->exists) {
            $this->material = $material;
            $this->editMode = true;
            $this->fillFormFromMaterial($material);
            $this->storeFieldsToTranslations($this->mainLocale());
            $this->loadTranslationsFromMaterial($material);
            $this->loadFieldsFromTranslations($this->selectedLocale);
        }

        if (empty($this->specs)) {
            $this->specs = [['label' => '', 'value' => '']];
        }
    }

    protected function fillFormFromMaterial(Material $material): void
    {
        $this->title = (string) $material->title;
        $this->subtitle = (string) $material->subtitle;
        $this->slug = (string) $material->slug;
        $this->description = (string) $material->description;
        $this->taxon_ids = $material->taxons->pluck('id')->map(fn($id) => (string) $id)->toArray();
        $this->specs = $this->normalizeSpecs($material->specifications['material_specs'] ?? []);
        $this->code = (string) $material->code;
        $this->brand = (string) $material->brand;
        $this->status = $this->normalizeStatus((string) $material->status);
        $this->print_method = (string) $material->print_method;
        $this->base_material = (string) $material->base_material;
        $this->finish = (string) $material->finish;
        $this->adhesive = (string) $material->adhesive;
        $this->supplier = (string) $material->supplier;
        $this->supplier_reference = (string) $material->supplier_reference;
        $this->price_per_sq_meter = filled($material->price_per_sq_meter) ? (string) $material->price_per_sq_meter : '';
        $this->certificate = filled($material->certificate) ? (string) $material->certificate : 'none';
    }

    public function addSpec(): void
    {
        $this->specs[] = ['label' => '', 'value' => ''];
    }

    public function removeSpec(int $index): void
    {
        unset($this->specs[$index]);
        $this->specs = array_values($this->specs);
    }

    /** Human-readable name of the locale currently being edited. */
    #[Computed]
    public function localeLabel(): string
    {
        $locale = $this->selectedLocale ?: $this->mainLocale();

        return __(config('app.available_locales')[$locale] ?? $locale);
    }

    /** Uploaded spec-sheet PDF for the locale currently being edited. */
    #[Computed]
    public function existingSpecSheet()
    {
        if (!$this->editMode || !$this->material) {
            return collect();
        }

        return $this->material->getMedia(Material::specSheetCollection($this->selectedLocale ?: $this->mainLocale()));
    }

    protected function saveSpecSheet(Material $material): void
    {
        if (!$this->spec_pdf) {
            return;
        }

        $originalName = $this->spec_pdf->getClientOriginalName();

        // Single-file collection per locale — a new upload replaces the prior
        // PDF for the language currently being edited, leaving others intact.
        $material->addMedia($this->spec_pdf->getRealPath())
            ->usingFileName($originalName)
            ->usingName(pathinfo($originalName, PATHINFO_FILENAME))
            ->toMediaCollection(Material::specSheetCollection($this->selectedLocale ?: $this->mainLocale()));

        $this->spec_pdf = null;
    }

    #[On('remove-upload')]
    public function handleRemoveUpload(array $params): void
    {
        $mediaId = $params['id'] ?? null;

        if (!$mediaId || !$this->editMode || !$this->material) {
            return;
        }

        $this->material->media()->where('id', $mediaId)->first()?->delete();

        unset($this->existingSpecSheet);
    }

    public function save(): void
    {
        $this->storeFieldsToTranslations();

        $primaryLocale = $this->mainLocale();
        if (empty(trim((string) ($this->translations[$primaryLocale]['title'] ?? '')))) {
            foreach (array_keys($this->locales()) as $locale) {
                if (!empty(trim((string) ($this->translations[$locale]['title'] ?? '')))) {
                    $primaryLocale = $locale;
                    break;
                }
            }
        }

        if (empty(trim((string) ($this->translations[$primaryLocale]['title'] ?? '')))) {
            $this->addError('title', __('Enter a material title in at least one language.'));
            $this->dispatch('scroll-to-error');

            return;
        }

        $this->loadFieldsFromTranslations($primaryLocale);
        $this->slug = $this->slug ?: Str::slug($this->title);
        $this->storeFieldsToTranslations($primaryLocale);
        $this->status = $this->normalizeStatus($this->status);
        $this->price_per_sq_meter = $this->nullableNumericValue($this->price_per_sq_meter);

        try {
            $validated = $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->loadFieldsFromTranslations($this->selectedLocale);
            $this->dispatch('scroll-to-error');
            throw $e;
        }

        $oldWysiwygIds = [];
        if ($this->editMode && $this->material) {
            $oldWysiwygIds = $this->extractWysiwygMediaIds(...$this->originalLocalizedDescriptions($this->material));
        }

        $materialData = collect($validated)
            ->only(['title', 'subtitle', 'slug', 'description', 'code', 'brand', 'status', 'print_method', 'base_material', 'finish', 'adhesive', 'supplier', 'supplier_reference', 'price_per_sq_meter', 'certificate'])
            ->toArray();
        $materialData['price_per_sq_meter'] = $this->nullableNumericValue($materialData['price_per_sq_meter'] ?? null);

        $materialData['specifications'] = [
            'material_specs' => collect($validated['specs'] ?? [])
                ->filter(fn ($spec) => filled($spec['label']) && filled($spec['value']))
                ->values()
                ->toArray(),
        ];

        if (!$this->material) {
            $material = Material::create($materialData);
            $material->taxons()->sync($this->taxon_ids);
            $this->syncTranslations($material, $primaryLocale);

            $savedMaterial = $material;

            Flux::toast(__('Material created successfully.'), variant: 'success');
        } else {
            $this->material->update($materialData);
            $this->syncTranslations($this->material, $primaryLocale);

            $this->material->taxons()->sync($this->taxon_ids);

            $savedMaterial = $this->material;

            Flux::toast(__('Material updated successfully.'), variant: 'success');
        }

        $this->saveSpecSheet($savedMaterial);

        $this->cleanupRemovedWysiwygMedia($oldWysiwygIds);

        $this->redirect(route('materials.index'), navigate: true);
    }

    protected function wysiwygFieldValues(): array
    {
        return collect($this->translations)
            ->pluck('description')
            ->push($this->description)
            ->filter()
            ->values()
            ->all();
    }

    protected function usesMainLocale(): bool
    {
        return ($this->selectedLocale ?: app()->getLocale()) === $this->mainLocale();
    }

    protected function translatableFields(): array
    {
        return ['title', 'subtitle', 'slug', 'description', 'specs'];
    }

    protected function mainLocale(): string
    {
        return config('app.main_locale');
    }

    protected function locales(): array
    {
        return config('app.available_locales');
    }

    protected function otherLocales(): array
    {
        return array_diff(array_keys($this->locales()), [$this->mainLocale()]);
    }

    protected function normalizeStatus(?string $status): string
    {
        $status = trim((string) $status);

        if ($status === '') {
            return '';
        }

        $legacyStatuses = [
            'Active' => 'active',
            'In Testing' => 'in_testing',
            'Phase Out' => 'phase_out',
            'Archived' => 'archived',
            'Rejected' => 'rejected',
        ];

        return $legacyStatuses[$status] ?? Str::of($status)->lower()->replace([' ', '-'], '_')->toString();
    }

    protected function nullableNumericValue(mixed $value): mixed
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $value;
    }

    protected function storeFieldsToTranslations(?string $locale = null): void
    {
        $locale ??= $this->selectedLocale;

        foreach ($this->translatableFields() as $field) {
            $this->translations[$locale][$field] = $field === 'specs'
                ? $this->normalizeSpecs($this->specs)
                : $this->{$field};
        }
    }

    protected function loadFieldsFromTranslations(string $locale): void
    {
        foreach ($this->translatableFields() as $field) {
            if ($field === 'specs') {
                $this->specs = $this->normalizeSpecs($this->translations[$locale][$field] ?? []);

                continue;
            }

            $this->{$field} = (string) ($this->translations[$locale][$field] ?? '');
        }

        if (empty($this->specs)) {
            $this->specs = [['label' => '', 'value' => '']];
        }
    }

    protected function loadTranslationsFromMaterial(Material $material): void
    {
        foreach ($this->otherLocales() as $locale) {
            $translation = Translation::findByModel($material, $locale);

            if (!$translation) {
                continue;
            }

            $fields = is_array($translation->fields) ? $translation->fields : [];

            $this->translations[$locale]['title'] = (string) ($translation->name ?? ($fields['title'] ?? ($fields['name'] ?? '')));
            $this->translations[$locale]['subtitle'] = (string) ($fields['subtitle'] ?? '');
            $this->translations[$locale]['slug'] = (string) ($translation->slug ?? ($fields['slug'] ?? ''));
            $this->translations[$locale]['description'] = (string) ($fields['description'] ?? '');
            $this->translations[$locale]['specs'] = $this->normalizeSpecs($fields['specifications']['material_specs'] ?? []);
        }
    }

    protected function originalLocalizedDescriptions(Material $material): array
    {
        $descriptions = [$this->mainLocale() => (string) $material->description];

        foreach ($this->otherLocales() as $locale) {
            $translation = Translation::findByModel($material, $locale);
            $fields = is_array($translation?->fields) ? $translation->fields : [];
            $descriptions[$locale] = (string) ($fields['description'] ?? '');
        }

        return array_filter($descriptions);
    }

    protected function syncTranslations(Material $material, string $primaryLocale): void
    {
        foreach ($this->otherLocales() as $locale) {
            if ($locale === $primaryLocale) {
                continue;
            }

            $localeData = $this->translations[$locale] ?? [];
            $title = trim((string) ($localeData['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            if (blank($localeData['slug'] ?? null)) {
                $localeData['slug'] = Str::slug($title);
            }

            $this->syncTranslationForLocale($material, $localeData, $locale);
        }
    }

    protected function syncTranslationForLocale(Material $material, array $payload, string $locale): void
    {
        $payload['name'] = $payload['title'] ?? null;
        $payload['specifications'] = [
            'material_specs' => collect($this->normalizeSpecs($payload['specs'] ?? []))
                ->filter(fn ($spec) => filled($spec['label']) && filled($spec['value']))
                ->values()
                ->toArray(),
        ];
        unset($payload['specs']);

        $translation = Translation::findByModel($material, $locale);

        if ($translation) {
            $translation->update([
                'name' => $payload['title'],
                'slug' => $payload['slug'],
                'fields' => $payload,
            ]);

            return;
        }

        Translation::createForModel($material, $locale, $payload);
    }

    protected function normalizeSpecs(array $specs): array
    {
        return collect($specs)
            ->map(fn ($spec) => [
                'label' => (string) ($spec['label'] ?? ''),
                'value' => (string) ($spec['value'] ?? ''),
            ])
            ->values()
            ->toArray();
    }

};
?>

<div>
    <x-scroll-to-error />
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $editMode ? __('Edit Material') : __('Create Material') }}</flux:heading>
                <flux:subheading>
                    {{ $editMode ? __('Update your material details.') : __('Add a new material.') }}
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('materials.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ $editMode ? __('Update Material') : __('Save Material') }}
                </flux:button>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3 md:items-start">
            <div class="space-y-6 md:col-span-2">
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Essentials') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <flux:input wire:model="code" label="{{ __('Code') }} *" placeholder="E.g.  MAT-001" />
                        </div>
                        <div class="md:col-span-1">
                            <flux:select wire:model="brand" label="{{ __('Brand') }} *"
                                placeholder="{{ __('Select Brand') }}" variant="listbox" searchable clearable>
                                @foreach (config('app.material_brands') as $key => $value)
                                    <flux:select.option value="{{ $key }}">{{ $value }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="flex-1">
                            <flux:select wire:model="status" label="{{ __('Status') }} *">
                                @foreach ($this->statusOptions() as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </flux:card>

                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Material Composition') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <flux:select wire:model="print_method" label="{{ __('Print Method') }}"
                                placeholder="{{ __('Select Print Method') }}" variant="listbox" searchable clearable>
                                @foreach (config('app.material_print_method') as $key => $value)
                                    <flux:select.option value="{{ $key }}">{{ $value }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-1">
                            <flux:select wire:model="base_material" label="{{ __('Base Material') }} *"
                                placeholder="{{ __('Select Base Material') }}" variant="listbox" searchable clearable>
                                @foreach (config('app.material_base_material') as $key => $value)
                                    <flux:select.option value="{{ $key }}">{{ $value }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-1">
                            <flux:select wire:model="finish" label="{{ __('Finish') }}"
                                placeholder="{{ __('Select Finish') }}" variant="listbox" searchable clearable>
                                @foreach (config('app.material_finish') as $key => $value)
                                    <flux:select.option value="{{ $key }}">{{ $value }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-1">
                            <flux:select wire:model="adhesive" label="{{ __('Adhesive') }}"
                                placeholder="{{ __('Select Adhesive') }}" variant="listbox" searchable clearable>
                                @foreach (config('app.material_adhesive') as $key => $value)
                                    <flux:select.option value="{{ $key }}">{{ $value }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </flux:card>

                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Material Composition') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <flux:select wire:model="supplier" label="{{ __('Supplier') }}"
                                placeholder="{{ __('Select Supplier') }} *" variant="listbox" searchable clearable>
                                @foreach (config('app.material_suppliers') as $key => $value)
                                    <flux:select.option value="{{ $key }}">{{ $value }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="md:col-span-1">
                            <flux:input wire:model="supplier_reference" label="{{ __('Supplier Reference') }} *"
                                placeholder="E.g. MAT-001" />
                        </div>
                        <div class="md:col-span-1">
                            <flux:input type="number" wire:model="price_per_sq_meter"
                                label="{{ __('Indicative Price per m² (1,000 m² jumbo roll)') }}"
                                placeholder="E.g. 0.05" />
                            <flux:subheading>
                                {{ __('Internal reference only - for comparing materials and advising customers. Not used for calculations.') }}
                            </flux:subheading>
                        </div>
                    </div>
                </flux:card>

                <flux:card class="space-y-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Basic Information') }}</flux:heading>
                            <flux:subheading>{{ __('Material title, description and general details.') }}</flux:subheading>
                        </div>
                        <div class="flex items-center gap-1">
                            @foreach (config('app.available_locales') as $localeCode => $localeName)
                                <button type="button" wire:click="switchLocale('{{ $localeCode }}')"
                                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-sm font-medium transition-colors {{ $selectedLocale === $localeCode ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
                                    <span class="text-base leading-none">{{ $localeCode === 'en' ? '🇬🇧' : '🇳🇱' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <flux:input wire:model="title" label="{{ __('Title') }} *"
                                placeholder="Enter material title" />
                        </div>
                        <div class="flex-1">
                            <x-slug-input model="slug" placeholder="Enter slug" />
                        </div>
                    </div>

                    <flux:input wire:model="subtitle" label="{{ __('Subtitle') }}" placeholder="Short subtitle..." />

                    <x-wysiwyg-editor wire:model="description" :defer-delete="$editMode" label="{{ __('Description') }}"
                        placeholder="Material description..." />
                </flux:card>

                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Specifications') }}</flux:heading>
                        <flux:subheading>{{ __('Add technical specifications for this material.') }}</flux:subheading>
                    </div>

                    <div class="space-y-4">
                        @foreach ($specs as $index => $spec)
                            <div class="flex items-start space-x-4 border border-zinc-200 dark:border-zinc-700 p-4 rounded-xl"
                                wire:key="spec-{{ $index }}">
                                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <flux:input wire:model="specs.{{ $index }}.label"
                                            label="{{ __('Label') }}" placeholder="e.g. Thickness" />
                                    </div>
                                    <div>
                                        <flux:input wire:model="specs.{{ $index }}.value"
                                            label="{{ __('Value') }}" placeholder="e.g. 0.5mm" />
                                    </div>
                                </div>

                                <div class="pt-8">
                                    <div x-data="{ hover: false }" @mouseenter="hover = true"
                                        @mouseleave="hover = false">
                                        <template x-if="!hover">
                                            <flux:icon.trash class="size-6 text-red-500 cursor-pointer"
                                                wire:click="removeSpec({{ $index }})" />
                                        </template>
                                        <template x-if="hover">
                                            <flux:icon.trash class="size-6 text-red-700 cursor-pointer"
                                                variant="solid" wire:click="removeSpec({{ $index }})" />
                                        </template>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <flux:button variant="subtle" size="sm" icon="plus" wire:click="addSpec">
                            {{ __('Add Specification') }}
                        </flux:button>
                    </div>
                </flux:card>

                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Certifications') }}</flux:heading>
                    </div>
                    <div class="gap-6">
                        <flux:radio.group wire:model="certificate" label="BS5609" variant="cards"
                            class="max-sm:flex-col">
                            <flux:radio value="BS5609" checked>
                                <flux:radio.indicator />

                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <img class="w-6"
                                            src="{{ asset('images/material-certificates/BS5609_1761831027498.webp') }}"
                                            alt="BS5609">
                                        <flux:heading class="leading-4">BS5609</flux:heading>
                                    </div>
                                    <flux:text size="sm" class="mt-2">BS5609 Chemical transportation
                                    </flux:text>
                                </div>
                            </flux:radio>

                            <flux:radio value="FSC">
                                <flux:radio.indicator />

                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <img class="w-6"
                                            src="{{ asset('images/material-certificates/FSC_1761831037218.webp') }}"
                                            alt="FSC">
                                        <flux:heading class="leading-4">FSC</flux:heading>
                                    </div>
                                </div>
                            </flux:radio>

                            <flux:radio value="none">
                                <flux:radio.indicator />

                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading class="leading-4">None</flux:heading>
                                    </div>
                                </div>
                            </flux:radio>
                        </flux:radio.group>
                    </div>
                </flux:card>
            </div>

            <div class="space-y-6 md:sticky md:top-6 md:self-start">
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Category Tree') }}</flux:heading>
                        <flux:subheading>{{ __('Search and assign categories to this material.') }}</flux:subheading>
                    </div>

                    <livewire:category-tree taxonomy-name="Material Category" wire:model="taxon_ids" />
                </flux:card>

                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('Spec Sheet') }} — {{ $this->localeLabel }}</flux:heading>
                        <flux:subheading>
                            {{ __('Upload a PDF spec sheet for the :language version of this material. If none is uploaded, a PDF is generated automatically from the material details when downloaded.', ['language' => $this->localeLabel]) }}
                        </flux:subheading>
                    </div>

                    <x-file-pond wire:model="spec_pdf" accept="application/pdf"
                        :uploads="$this->existingSpecSheet" />

                    <flux:error name="spec_pdf" />

                    @if ($editMode && $material)
                        <flux:button icon="arrow-down-tray" class="w-full"
                            href="{{ route('api.materials.spec-sheet', ['id' => $material->id, 'lang' => app()->getLocale()]) }}"
                            target="_blank">
                            {{ __('Download :language Spec Sheet', ['language' => $this->localeLabel]) }}
                        </flux:button>
                    @endif
                </flux:card>
            </div>
        </div>
    </form>
</div>

<?php

use App\Concerns\HandlesWysiwygMedia;
use App\Models\Post;
use App\Models\Product;
use App\Services\PrinterProductCompatibilitySyncService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;
use Vanilo\Translation\Models\Translation;

new class extends Component {
    use HandlesWysiwygMedia, WithFileUploads;

    public Collection $all_properties;

    public array $printer_properties = [];

    public ?Post $post = null;

    public $title = '';
    public $content = '';
    public $excerpt = '';
    public $slug = '';
    public $status = 'draft';
    public $post_type = 'printer';
    public $template = 'default';

    public $main_image;

    // Meta Fields
    public $subtitle = '';
    public $kern = [];
    public $label_breedte = '';
    public $label_type = '';
    public $max_buiten_diameter = '';
    public $width = [];
    public $druktype = [];
    public $buiten_diameter = [];
    public $detectie = [];
    public $featured = false;
    public $printer_url = '';

    // Catalog product URL linked via the "Printer Url" property (Technical Details).
    public string $printer_url_value = '';

    public bool $editMode = false;

    public string $selectedLocale = '';

    public array $translations = [];

    protected function initTranslations(): void
    {
        foreach (array_keys($this->locales()) as $locale) {
            $this->translations[$locale] = array_fill_keys($this->translatableFields(), '');
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
        if ($locale === $this->selectedLocale) {
            return;
        }

        $this->storeFieldsToTranslations();
        $this->loadFieldsFromTranslations($locale);
        $this->selectedLocale = $locale;
        app()->setLocale($locale);
    }

    public function mount(?Post $printer = null)
    {
        $this->selectedLocale = $this->mainLocale();
        $this->initTranslations();
        $this->all_properties = Property::with('propertyValues')->get();

        if ($printer && $printer->exists) {
            $printer->loadMissing('propertyValues.property');

            $this->post = $printer;
            $this->title = $printer->title;
            $this->content = $printer->content;
            $this->excerpt = $printer->excerpt;
            $this->slug = $printer->slug;
            $this->status = $printer->status;
            $this->post_type = 'printer';
            $this->template = $printer->template ?: 'default';

            $this->editMode = (bool) true;

            // Load meta fields
            $meta = collect($printer->meta()->pluck('meta_value', 'meta_key')->toArray());
            $this->subtitle = $meta->get('subtitle', '');
            $this->kern = $meta->get('kern', '');
            $this->label_breedte = $meta->get('label_breedte', '');
            $this->label_type = $meta->get('label_type', '');
            $this->max_buiten_diameter = $meta->get('max_buiten_diameter', '');
            $this->width = $meta->get('width', '');
            $this->druktype = $meta->get('druktype', '');
            $this->buiten_diameter = $meta->get('buiten_diameter', '');
            $this->detectie = $meta->get('detectie', '');
            $this->featured = (bool) $meta->get('featured', false);
            $this->printer_url = $meta->get('printer_url', '');

            $printerUrlProperty = $this->all_properties->firstWhere('slug', 'printer-url');

            foreach ($this->all_properties as $property) {
                // The "Printer Url" property is handled by its own product dropdown.
                if ($printerUrlProperty && $property->id === $printerUrlProperty->id) {
                    continue;
                }

                $this->printer_properties[$property->id] = $printer->propertyValues
                    ->where('property_id', $property->id)
                    ->pluck('value')
                    ->map(fn($value) => (string) $value)
                    ->unique()
                    ->values()
                    ->all();
            }

            if ($printerUrlProperty) {
                $this->printer_url_value = (string) ($printer->propertyValues
                    ->firstWhere('property_id', $printerUrlProperty->id)?->value ?? '');
            }

            $this->storeFieldsToTranslations($this->mainLocale());

            foreach ($this->otherLocales() as $locale) {
                $translation = Translation::findByModel($printer, $locale);

                if (! $translation) {
                    continue;
                }

                $fields = is_array($translation->fields) ? $translation->fields : [];

                foreach ($this->translatableFields() as $field) {
                    $value = $translation->{$field} ?? ($fields[$field] ?? '');

                    if (empty($value) && $field === 'title') {
                        $value = $translation->name ?? ($fields['name'] ?? '');
                    }

                    $this->translations[$locale][$field] = (string) $value;
                }
            }
        }
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Title is required.',
            'slug.unique' => 'Slug already exists.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be draft or published.',
            'printer_url.url' => 'Printer URL must be a valid URL.',
            'main_image.image' => __('Must be an image.'),
            'main_image.max' => __('Max 10MB.'),
        ];
    }

    public function save()
    {
        $this->storeFieldsToTranslations();

        $primaryLocale = $this->mainLocale();
        if (empty(trim((string) ($this->translations[$primaryLocale]['title'] ?? '')))) {
            foreach (array_keys($this->locales()) as $locale) {
                if (! empty(trim((string) ($this->translations[$locale]['title'] ?? '')))) {
                    $primaryLocale = $locale;
                    break;
                }
            }
        }

        if (empty(trim((string) ($this->translations[$primaryLocale]['title'] ?? '')))) {
            $this->addError('title', __('Enter a printer title in at least one language.'));
            $this->dispatch('scroll-to-error');

            return;
        }

        $this->loadFieldsFromTranslations($primaryLocale);

        if (blank($this->slug)) {
            $this->slug = Str::slug($this->title);
            $this->storeFieldsToTranslations($primaryLocale);
        }

        $oldWysiwygIds = [];

        if ($this->post && $this->post->exists) {
            $oldWysiwygIds = $this->extractWysiwygMediaIds($this->originalLocalizedContent($this->post));
        }

        $rules = [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug' . ($this->post ? ',' . $this->post->id : ''),
            'status' => 'required|in:draft,published',
            'template' => 'nullable|string|max:255',

            'main_image' => 'nullable|image|max:10240',

            // Meta rules
            'subtitle' => 'nullable|string|max:255',
            'kern' => 'nullable|array',
            'label_breedte' => 'nullable|string|max:255',
            'label_type' => 'nullable|string|max:255',
            'max_buiten_diameter' => 'nullable|string|max:255',
            'width' => 'nullable|array',
            'druktype' => 'nullable|array',
            'buiten_diameter' => 'nullable|array',
            'detectie' => 'nullable|array',
            'featured' => 'boolean',
            'printer_url' => 'nullable|url|max:2048',
            'printer_url_value' => 'nullable|string|max:2048',
            'printer_properties' => 'nullable|array',
            'printer_properties.*' => 'nullable|array',
            'printer_properties.*.*' => 'nullable|string|max:255',
        ];

        $this->resetErrorBag();

        $validator = Validator::make($this->all(), $rules, $this->messages());

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $msgs) {
                $this->addError($field, $msgs[0]);
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            $this->loadFieldsFromTranslations($this->selectedLocale);
            $this->dispatch('scroll-to-error');
            return;
        }

        $validated = $this->validate($rules);

        // Core Post Data
        $postData = collect($validated)
            ->only(['title', 'content', 'excerpt', 'slug', 'status', 'template'])
            ->toArray();
        $postData['post_type'] = 'printer';

        if (!$this->post) {
            $postData['author'] = auth()->id();
            $post = Post::create($postData);

            $this->syncMeta($post, $validated);
            $this->syncPrinterProperties($post);
            $this->syncPrinterUrlProperty($post);

            $this->syncTranslations($post, $primaryLocale);

            $this->uploadMedia($post);

            $this->cleanupRemovedWysiwygMedia($oldWysiwygIds);

            Flux::toast(__('Printer created successfully.'), variant: 'success');

            $this->reset(['title', 'content', 'excerpt', 'slug', 'status', 'template', 'subtitle', 'kern', 'label_breedte', 'label_type', 'max_buiten_diameter', 'width', 'druktype', 'buiten_diameter', 'detectie', 'featured', 'printer_url', 'printer_url_value']);
            $this->status = 'draft';
            $this->post_type = 'printer';
            $this->template = 'default';
            $this->featured = false;
        } else {
            $this->post->update($postData);
            $this->syncTranslations($this->post, $primaryLocale);

            $this->syncMeta($this->post, $validated);
            $this->syncPrinterProperties($this->post);
            $this->syncPrinterUrlProperty($this->post);
            $this->cleanupRemovedWysiwygMedia($oldWysiwygIds);
            $this->uploadMedia($this->post);

            Flux::toast(__('Printer updated successfully.'), variant: 'success');
        }
    }

    protected function syncMeta(Post $post, array $validated)
    {
        $metaKeys = ['subtitle', 'kern', 'label_breedte', 'label_type', 'max_buiten_diameter', 'width', 'druktype', 'buiten_diameter', 'detectie', 'featured', 'printer_url'];

        foreach ($metaKeys as $key) {
            $value = $validated[$key] ?? null;

            // Convert boolean to string for storing
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $post->meta()->updateOrCreate(['meta_key' => $key], ['meta_value' => $value]);
        }
    }

    protected function uploadMedia($model): void
    {
        if ($this->main_image) {
            $model->clearMediaCollection('main');
            $model
                ->addMedia($this->main_image->getRealPath())
                ->usingName($this->main_image->getClientOriginalName())
                ->usingFileName($this->main_image->getClientOriginalName())
                ->toMediaCollection('main');
        }
    }

    protected function syncPrinterProperties(Post $printer): void
    {
        if ($this->printer_properties === []) {
            return;
        }

        $managedPropertyIds = collect(array_keys($this->printer_properties))
            ->map(fn($propertyId) => (int) $propertyId)
            ->filter()
            ->values();

        if ($managedPropertyIds->isEmpty()) {
            return;
        }

        $properties = Property::query()
            ->whereIn('id', $managedPropertyIds)
            ->get()
            ->keyBy('id');

        $selectedPropertyValueIds = [];

        foreach ($this->printer_properties as $propertyId => $values) {
            $property = $properties->get((int) $propertyId);

            if (! $property) {
                continue;
            }

            foreach ($this->normalizeSelectedPropertyValues($values) as $value) {
                $selectedPropertyValueIds[] = PropertyValue::firstOrCreate(
                    [
                        'property_id' => $property->id,
                        'value' => $value,
                    ],
                    ['title' => $value]
                )->id;
            }
        }

        $printer->propertyValues()->detach(
            PropertyValue::query()
                ->whereIn('property_id', $managedPropertyIds)
                ->pluck('id')
                ->all()
        );

        if ($selectedPropertyValueIds !== []) {
            $printer->propertyValues()->syncWithoutDetaching(array_values(array_unique($selectedPropertyValueIds)));
        }

        app(PrinterProductCompatibilitySyncService::class)->syncPrinter($printer);
    }

    /**
     * Persist the "Printer Url" product link as a single property value.
     */
    protected function syncPrinterUrlProperty(Post $printer): void
    {
        $property = $this->all_properties->firstWhere('slug', 'printer-url');

        if (! $property) {
            return;
        }

        // Clear any previously linked printer-url values for this printer.
        $printer->propertyValues()->detach(
            PropertyValue::query()->where('property_id', $property->id)->pluck('id')->all()
        );

        $value = trim((string) $this->printer_url_value);

        if ($value === '') {
            return;
        }

        $propertyValue = PropertyValue::firstOrCreate(
            ['property_id' => $property->id, 'value' => $value],
            ['title' => $value]
        );

        $printer->propertyValues()->syncWithoutDetaching([$propertyValue->id]);
    }

    protected function normalizeSelectedPropertyValues(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->map(fn($value) => trim((string) $value))
            ->filter(fn(string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    #[Computed()]
    public function existingMainMedia()
    {
        if (!$this->editMode || !$this->post) {
            return collect();
        }

        $model = $this->post;

        return $model->getMedia('main');
    }

    /**
     * Catalog products used as options for the "Printer Url" dropdown.
     * Each option shows the product title and stores its storefront URL.
     */
    #[Computed()]
    public function productOptions()
    {
        return Product::query()
            ->orderBy('title')
            ->limit(1000)
            ->get(['id', 'title', 'name', 'slug'])
            ->map(function ($product) {
                // Use the raw column values; the translated accessor is blank
                // when a product has no translation for the current locale.
                $title = trim((string) ($product->getRawOriginal('title') ?: $product->getRawOriginal('name')));

                return [
                    'url' => $this->productUrl($product->getRawOriginal('slug')),
                    'label' => $title !== '' ? $title : __('Product') . ' #' . $product->id,
                ];
            })
            ->filter(fn ($option) => $option['url'] !== '')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    protected function productUrl(?string $slug): string
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return '';
        }

        return rtrim((string) config('app.frontend_url'), '/') . '/product/' . $slug . '/';
    }

    protected function wysiwygFieldValues(): array
    {
        return [$this->content];
    }

    protected function usesMainLocale(): bool
    {
        return app()->getLocale() === $this->mainLocale();
    }

    protected function translatableFields(): array
    {
        return ['title', 'slug', 'subtitle', 'excerpt', 'content'];
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

    protected function storeFieldsToTranslations(?string $locale = null): void
    {
        $locale ??= $this->selectedLocale;

        foreach ($this->translatableFields() as $field) {
            $this->translations[$locale][$field] = $this->{$field};
        }
    }

    protected function loadFieldsFromTranslations(string $locale): void
    {
        foreach ($this->translatableFields() as $field) {
            $this->{$field} = $this->translations[$locale][$field] ?? '';
        }
    }

    protected function originalLocalizedContent(Post $post): string
    {
        if ($this->usesMainLocale()) {
            return (string) $post->content;
        }

        $translation = Translation::findByModel($post, app()->getLocale());
        $fields = is_array($translation?->fields) ? $translation->fields : [];

        return (string) ($fields['content'] ?? $post->content);
    }

    protected function syncTranslations(Post $post, string $primaryLocale): void
    {
        foreach ($this->otherLocales() as $locale) {
            if ($locale === $primaryLocale) {
                continue;
            }

            $payload = $this->translations[$locale] ?? [];
            $title = trim((string) ($payload['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            if (blank($payload['slug'] ?? null)) {
                $payload['slug'] = Str::slug($title);
            }

            $this->syncTranslationForLocale($post, $payload, $locale);
        }
    }

    protected function syncTranslationForLocale(Post $post, array $payload, string $locale): void
    {
        $payload['name'] = $payload['title'] ?? null;

        $data = [
            'name' => $payload['name'],
            'slug' => $payload['slug'] ?? null,
            'fields' => collect($payload)->except(['name'])->toArray(),
        ];

        $translation = Translation::findByModel($post, $locale);

        if ($translation) {
            $translation->update($data);

            return;
        }

        Translation::createForModel($post, $locale, $payload);
    }
};
?>

<div>
    <x-scroll-to-error />
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $post ? __('Edit Printer') : __('Create Printer') }}</flux:heading>
                <flux:subheading>{{ $post ? __('Update your printer details.') : __('Add a new printer.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">
            <div class="md:col-span-2 space-y-6">
                <!-- Basic Information -->
                <flux:card class="space-y-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Basic Information') }}</flux:heading>
                            <flux:subheading>{{ __('Printer title, content, and general details.') }}</flux:subheading>
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
                            <flux:input wire:model="title" label="{{ __('Title') }}" placeholder="Enter title" />
                        </div>
                        <div class="flex-1">
                            <x-slug-input model="slug" placeholder="Enter slug" />
                        </div>
                    </div>

                    <flux:input wire:model="subtitle" label="{{ __('Subtitle') }}"
                        placeholder="Short subtitle for the printer" />

                    <flux:textarea wire:model="excerpt" label="{{ __('Excerpt') }}" placeholder="Short summary..."
                        rows="2" />

                    <x-wysiwyg-editor wire:model="content" :defer-delete="$post !== null" label="{{ __('Content') }}"
                        placeholder="Detailed content..." />
                </flux:card>

                <!-- Technical Details -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Technical Details') }}</flux:heading>
                        <flux:subheading>{{ __('Specific technical properties for this printer.') }}</flux:subheading>
                    </div>

                    @php($technicalProperties = $all_properties->reject(fn ($property) => $property->slug === 'printer-url')->values())

                    <div class="space-y-2">
                        <flux:select wire:model="printer_url_value" variant="listbox" searchable clearable
                            label="{{ __('Printer URL') }}"
                            placeholder="{{ __('Search and select the linked product...') }}">
                            @foreach ($this->productOptions as $option)
                                <flux:select.option value="{{ $option['url'] }}" wire:key="printer-url-opt-{{ $loop->index }}">
                                    {{ $option['label'] }}
                                </flux:select.option>
                            @endforeach
                            @if ($printer_url_value && ! $this->productOptions->contains('url', $printer_url_value))
                                <flux:select.option value="{{ $printer_url_value }}" wire:key="printer-url-current">
                                    {{ $printer_url_value }}
                                </flux:select.option>
                            @endif
                        </flux:select>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Connects this product finder entry to the actual product page. The selected product URL is sent to the storefront.') }}
                        </flux:text>
                        <flux:error name="printer_url_value" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach ($technicalProperties->take(8) as $property)
                            <flux:pillbox size="sm" variant="combobox" multiple
                                placeholder="{{ __('Select :property', ['property' => __($property->name)]) }}"
                                wire:model="printer_properties.{{ $property->id }}" label="{{ __($property->name) }}">
                                @forelse($property['propertyValues'] as $value)
                                    <flux:pillbox.option value="{{ $value->value }}" wire:key="printer-property-value-{{ $value->id }}">
                                        {{ __($value->title ?: $value->value) }}
                                    </flux:pillbox.option>
                                @empty
                                    <flux:pillbox.option value="" disabled>{{ __('No values') }}</flux:pillbox.option>
                                @endforelse
                            </flux:pillbox>
                        @endforeach
                    </div>

                    @if ($technicalProperties->count() > 8)
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            @foreach ($technicalProperties->skip(8) as $property)
                                <flux:pillbox size="sm" variant="combobox" multiple
                                    placeholder="{{ __('Select :property', ['property' => __($property->name)]) }}"
                                    wire:model="printer_properties.{{ $property->id }}" label="{{ __($property->name) }}">
                                    @forelse($property['propertyValues'] as $value)
                                        <flux:pillbox.option value="{{ $value->value }}" wire:key="printer-property-value-{{ $value->id }}">
                                            {{ __($value->title ?: $value->value) }}
                                        </flux:pillbox.option>
                                    @empty
                                        <flux:pillbox.option value="" disabled>{{ __('No values') }}</flux:pillbox.option>
                                    @endforelse
                                </flux:pillbox>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">
                <!-- Status & Visibility -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Visibility & Status') }}</flux:heading>
                    </div>

                    <flux:switch wire:model="featured" label="{{ __('Featured') }}"
                        description="{{ __('Highlight this printer.') }}" />

                    <flux:select wire:model="status" label="{{ __('Status') }}">
                        <flux:select.option value="draft">{{ __('Draft') }}</flux:select.option>
                        <flux:select.option value="published">{{ __('Published') }}</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="template" label="{{ __('Template') }}" placeholder="default" />

                    <div class="flex space-x-2 items-end">
                        <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                    </div>
                </flux:card>

                <!-- Media -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Product Media') }}</flux:heading>
                        <flux:subheading>{{ __('Upload primary product image and gallery.') }}</flux:subheading>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Main Image') }}</flux:label>
                        <x-file-pond wire:model="main_image" accept="image/*" :uploads="$this->existingMainMedia" />
                        <flux:error name="main_image" />
                    </flux:field>
                </flux:card>
            </div>
        </div>
    </form>
</div>

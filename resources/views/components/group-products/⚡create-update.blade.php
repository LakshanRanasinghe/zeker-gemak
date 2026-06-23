<?php

use App\Concerns\HandlesWysiwygMedia;
use App\Models\AiSetting;
use App\Models\DiscountGroup;
use App\Models\GroupProduct;

use App\Models\Product;
use App\Services\ProductContentGenerator;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Vanilo\Properties\Models\Property;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Translation\Models\Translation;

new class extends Component {
    use HandlesWysiwygMedia;
    use WithFileUploads;

    // Core fields
    public ?GroupProduct $groupProduct = null;

    public bool $editMode = false;

    public string $name = '';

    public string $title = '';

    public string $subtitle = '';

    public string $sku = '';

    public string $article_number = '';

    public string $slug = '';

    public int $stock = 0;

    // Pricing
    public $price = 0.0;

    public $original_price = 0.0;

    // Content
    public string $excerpt = '';

    public string $description = '';

    public string $content = '';

    public string $product_information = '';

    // SEO
    public string $meta_title = '';

    public string $meta_description = '';

    // Configuration
    public string $product_template = 'label';

    public string $state = 'active';

    // Physical dimensions
    public $weight = 0.0;

    public $width = 0.0;

    public $height = 0.0;

    public $length = 0.0;

    // Additional info
    public string $make = '';

    public string $material_information = '';

    public ?int $packaging_unit = null;

    public ?int $jeritech_stock = null;

    public ?int $delivery_dates_no_stock = null;

    public ?int $delivery_dates_in_stock = null;

    public ?int $packing_group = null;

    // Foreign keys
    public ?int $tax_category_id = null;



    public ?int $discount_group_id = null;

    public ?float $discount = 0;

    public ?float $finalPrice = 0;

    // Media
    public $main_image;

    public array $gallery_images = [];

    // Component products
    public array $items = [];

    public string $product_search = '';

    public array $search_results = [];

    // Properties
    public Collection $all_properties;

    public array $product_properties = [];

    // Discount
    public $discount_search = '';

    public $discounts = [];

    // Locale
    public string $selectedLocale = '';

    public array $translations = [];

    // AI generation state
    public bool $isGeneratingContent = false;

    public bool $showGenerateModal = false;

    /** Shape: ['en' => ['content', ...], 'nl' => [...]] */
    public array $aiSelections = ['en' => [], 'nl' => []];

    /** Shape: ['en' => ['content' => 'pending'|'success'|'error', ...], 'nl' => [...]] */
    public array $aiRowStatus = ['en' => [], 'nl' => []];

    /** Shape: ['en' => ['content' => 'message', ...], 'nl' => [...]] */
    public array $aiRowErrors = ['en' => [], 'nl' => []];

    public string $aiModalError = '';

    public bool $hasRevertSnapshot = false;

    /** Pre-computed per-field has-content map; set when the generate modal opens. */
    public array $aiHasContent = [];

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:500|unique:group_products,slug' . ($this->groupProduct ? ',' . $this->groupProduct->id : ''),
            'subtitle' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:255|unique:group_products,sku' . ($this->groupProduct ? ',' . $this->groupProduct->id : ''),
            'article_number' => 'nullable|string|max:255|unique:group_products,article_number' . ($this->groupProduct ? ',' . $this->groupProduct->id : ''),
            'price' => 'nullable|numeric|gte:0|max:99999999999',
            'original_price' => 'nullable|numeric|gte:0|max:99999999999',
            'excerpt' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'product_information' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'product_template' => 'nullable|string',
            'state' => 'required|string|in:draft,active,unavailable',
            'weight' => 'nullable|numeric|gte:0',
            'width' => 'nullable|numeric|gte:0',
            'height' => 'nullable|numeric|gte:0',
            'length' => 'nullable|numeric|gte:0',
            'make' => 'nullable|string|max:255',
            'material_information' => 'nullable|string|max:255',
            'packaging_unit' => 'nullable|integer|min:0',
            'delivery_dates_no_stock' => 'nullable|integer|min:0',
            'delivery_dates_in_stock' => 'nullable|integer|min:0',
            'packing_group' => 'nullable|integer|min:0',
            'tax_category_id' => 'nullable|exists:tax_categories,id',

            'discount_group_id' => 'nullable|exists:discount_groups,id',
            'discount' => 'nullable|numeric|gte:0|max:100',
            'main_image' => 'nullable|image|max:10240',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'image|max:10240',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }

    public function mount(?GroupProduct $groupProduct = null): void
    {
        $this->selectedLocale = $this->mainLocale();
        $this->initTranslations();

        $allProps = Property::with('propertyValues')->get();
        $this->all_properties = $allProps;

        if ($groupProduct && $groupProduct->exists) {
            $this->editMode = true;
            $this->groupProduct = $groupProduct;

            $this->name = (string) $groupProduct->name;
            $this->title = (string) $groupProduct->title;
            $this->subtitle = (string) $groupProduct->subtitle;
            $this->sku = (string) $groupProduct->sku;
            $this->article_number = (string) $groupProduct->article_number;
            $this->slug = (string) $groupProduct->slug;
            $this->price = (float) $groupProduct->price;
            $this->original_price = (float) $groupProduct->original_price;
            $this->excerpt = (string) $groupProduct->excerpt;
            $this->description = (string) $groupProduct->description;
            $this->content = (string) $groupProduct->content;
            $this->product_information = (string) $groupProduct->product_information;
            $this->meta_title = (string) $groupProduct->meta_title;
            $this->meta_description = (string) $groupProduct->meta_description;
            $this->product_template = (string) $groupProduct->product_template;
            $this->state = (string) $groupProduct->state;
            $this->weight = (float) $groupProduct->weight;
            $this->width = (float) $groupProduct->width;
            $this->height = (float) $groupProduct->height;
            $this->length = (float) $groupProduct->length;
            $this->make = (string) $groupProduct->make;
            $this->material_information = (string) $groupProduct->material_information;
            $this->packaging_unit = $groupProduct->packaging_unit;
            $this->jeritech_stock = $groupProduct->jeritech_stock;
            $this->delivery_dates_no_stock = $groupProduct->delivery_dates_no_stock;
            $this->delivery_dates_in_stock = $groupProduct->delivery_dates_in_stock;
            $this->packing_group = $groupProduct->packing_group;
            $this->tax_category_id = $groupProduct->tax_category_id;

            $this->discount_group_id = $groupProduct->discount_group_id;
            $this->discount = (float) $groupProduct->discount;

            if ($this->discount_group_id) {
                $this->discounts = DiscountGroup::find($this->discount_group_id)?->discounts ?? [];
            }

            foreach ($allProps as $property) {
                $this->product_properties[$property->id] = null;
            }

            $this->items = $groupProduct
                ->items()
                ->with('product')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product ? $item->product->name : 'Unknown Product',
                        'product_stock' => $item->product ? (int) $item->product->stock : 0,
                        'quantity' => $item->quantity,
                        'available_sets' => $item->availableSets(),
                        'price' => (float) ($item->product->price ?? 0)
                    ];
                })
                ->toArray();

            $this->recalculatePrices();

            $this->storeFieldsToTranslations($this->mainLocale());

            foreach ($this->otherLocales() as $locale) {
                $translation = Translation::findByModel($groupProduct, $locale);
                if ($translation) {
                    $fields = is_array($translation->fields) ? $translation->fields : [];
                    $this->translations[$locale] = collect($fields)->only($this->translatableFields())->toArray();
                }
            }
        }
    }

    public function updatedProductSearch(): void
    {
        if (strlen($this->product_search) > 1) {
            $this->search_results = Product::where('name', 'like', '%' . $this->product_search . '%')
                ->orWhere('sku', 'like', '%' . $this->product_search . '%')
                ->take(10)
                ->get()
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'stock' => (int) $p->stock,
                        'price' => (float) $p->price,
                    ];
                })
                ->toArray();
        } else {
            $this->search_results = [];
        }
    }

    public function addProduct($productId): void
    {
        $product = Product::find($productId);

        if (!$product) {
            Flux::toast(__('Product not found.'), variant: 'danger');

            return;
        }

        $exists = collect($this->items)->firstWhere('product_id', $productId);
        if ($exists) {
            Flux::toast(__('Product is already in the group.'), variant: 'warning');

            return;
        }

        $this->items[] = [
            'id' => null,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_stock' => (int) $product->stock,
            'quantity' => 1,
            'available_sets' => (int) $product->stock,
            'price' => (float) $product->price
        ];

        $this->product_search = '';
        $this->search_results = [];
        $this->recalculatePrices();
    }

    public function removeItem($index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->recalculatePrices();
    }

    public function updatedItems(): void
    {
        // Recalculate available sets when quantities change
        foreach ($this->items as $index => $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $stock = (int) ($item['product_stock'] ?? 0);
            $this->items[$index]['available_sets'] = (int) floor($stock / $qty);
        }
        $this->recalculatePrices();
    }

    public function updatedDiscount(): void
    {
        $this->recalculatePrices();
    }

    public function recalculatePrices(): void
    {
        $basePrice = 0;
        foreach ($this->items as $item) {
            $basePrice += (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
        }
        
        $this->original_price = $basePrice;
        $this->price = $basePrice - ($basePrice * ((float) ($this->discount ?? 0) / 100));
        $this->finalPrice = $this->price; // Keep finalPrice for UI if used
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

    public function save(): void
    {
        $this->validate();

        $this->slug = $this->slug ?: Str::slug($this->title);

        $data = [
            'name' => $this->name,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'sku' => $this->sku,
            'article_number' => $this->article_number,
            'slug' => $this->slug,
            'price' => $this->price,
            'original_price' => $this->original_price,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'content' => $this->content,
            'product_information' => $this->product_information,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'product_template' => $this->product_template,
            'state' => $this->state,
            'weight' => $this->weight,
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'make' => $this->make,
            'material_information' => $this->material_information,
            'packaging_unit' => $this->packaging_unit,
            'jeritech_stock' => $this->jeritech_stock,
            'delivery_dates_no_stock' => $this->delivery_dates_no_stock,
            'delivery_dates_in_stock' => $this->delivery_dates_in_stock,
            'packing_group' => $this->packing_group,
            'tax_category_id' => $this->tax_category_id,

            'discount_group_id' => $this->discount_group_id,
            'discount' => $this->discount,
        ];

        if (!$this->groupProduct) {
            $this->groupProduct = GroupProduct::create($data);
            $message = __('Group Product created successfully.');
        } else {
            $this->groupProduct->update($data);
            $message = __('Group Product updated successfully.');
        }

        // Sync component items
        $existingItemIds = [];
        foreach ($this->items as $itemData) {
            if (isset($itemData['id']) && $itemData['id'] !== null) {
                $item = $this->groupProduct->items()->find($itemData['id']);
                if ($item) {
                    $item->update(['quantity' => $itemData['quantity']]);
                    $existingItemIds[] = $item->id;
                }
            } else {
                $newItem = $this->groupProduct->items()->create([
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                ]);
                $existingItemIds[] = $newItem->id;
            }
        }

        $this->groupProduct->items()->whereNotIn('id', $existingItemIds)->delete();

        // Upload media
        if ($this->main_image) {
            $this->groupProduct->clearMediaCollection('main');
            $this->groupProduct
                ->addMedia($this->main_image->getRealPath())
                ->usingName($this->main_image->getClientOriginalName())
                ->toMediaCollection('main');
        }

        if (!empty($this->gallery_images)) {
            foreach ($this->gallery_images as $image) {
                $this->groupProduct->addMedia($image->getRealPath())->usingName($image->getClientOriginalName())->toMediaCollection('gallery');
            }
        }

        // Save translations
        $this->storeFieldsToTranslations();
        foreach ($this->translations as $locale => $fields) {
            $this->syncTranslationForLocale($this->groupProduct, $fields, $locale);
        }

        Flux::toast($message, variant: 'success');
        $this->redirect(route('group-products.index'), navigate: true);
    }

    protected function initTranslations(): void
    {
        foreach (array_keys($this->locales()) as $locale) {
            $this->translations[$locale] = [];
        }
    }

    protected function storeFieldsToTranslations(?string $locale = null): void
    {
        $locale ??= $this->selectedLocale;
        $this->translations[$locale] = [
            'name' => $this->name,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'content' => $this->content,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
        ];
    }

    protected function loadFieldsFromTranslations(string $locale): void
    {
        $fields = $this->translations[$locale] ?? [];
        $this->name = $fields['name'] ?? '';
        $this->title = $fields['title'] ?? '';
        $this->subtitle = $fields['subtitle'] ?? '';
        $this->slug = $fields['slug'] ?? '';
        $this->excerpt = $fields['excerpt'] ?? '';
        $this->description = $fields['description'] ?? '';
        $this->content = $fields['content'] ?? '';
        $this->meta_title = $fields['meta_title'] ?? '';
        $this->meta_description = $fields['meta_description'] ?? '';
    }

    /**
     * Maps an AI field key (e.g. 'short_description') to the form's internal property name.
     * Trix "Short Description" field is internally `description`.
     */
    protected function aiFieldToFormField(string $aiField): string
    {
        return $aiField === 'short_description' ? 'description' : $aiField;
    }

    public function aiGeneratableFields(): array
    {
        return AiSetting::generatableFieldsForScope(AiSetting::SCOPE_GROUP_PRODUCT);
    }

    public function aiFieldLabels(): array
    {
        return [
            'slug' => __('Slug'),
            'subtitle' => __('Subtitle'),
            'excerpt' => __('Excerpt'),
            'short_description' => __('Short Description'),
            'content' => __('Content'),
            'meta_title' => __('Meta Title'),
            'meta_description' => __('Meta Description'),
        ];
    }

    public function aiHasContentMap(): array
    {
        $map = [];
        foreach (array_keys($this->locales()) as $loc) {
            $map[$loc] = [];
            foreach ($this->aiGeneratableFields() as $field) {
                $formField = $this->aiFieldToFormField($field);
                $value = (string) ($this->translations[$loc][$formField] ?? '');
                $map[$loc][$field] = trim(strip_tags($value)) !== '';
            }
        }

        return $map;
    }

    public function openGenerateModal(): void
    {
        $this->storeFieldsToTranslations();

        $this->aiModalError = '';
        $this->aiRowStatus = ['en' => [], 'nl' => []];
        $this->aiRowErrors = ['en' => [], 'nl' => []];

        $defaults = AiSetting::defaults(AiSetting::SCOPE_GROUP_PRODUCT);
        $allowed = $this->aiGeneratableFields();

        $this->aiSelections = [
            'en' => array_values(array_intersect((array) ($defaults['default_fields_en'] ?? []), $allowed)),
            'nl' => array_values(array_intersect((array) ($defaults['default_fields_nl'] ?? []), $allowed)),
        ];

        $this->aiHasContent = $this->aiHasContentMap();

        $this->showGenerateModal = true;
    }

    public function closeGenerateModal(): void
    {
        $this->showGenerateModal = false;
    }

    public function aiSelectAll(string $locale): void
    {
        if (!array_key_exists($locale, $this->aiSelections)) {
            return;
        }
        $this->aiSelections[$locale] = $this->aiGeneratableFields();
    }

    public function aiDeselectAll(string $locale): void
    {
        if (!array_key_exists($locale, $this->aiSelections)) {
            return;
        }
        $this->aiSelections[$locale] = [];
    }

    /**
     * Build the bundle context for a single-locale API call.
     */
    protected function aiBuildContext(string $locale): array
    {
        $title = trim((string) ($this->translations[$locale]['title'] ?? ''));

        $components = [];
        foreach ($this->items as $item) {
            $name = trim((string) ($item['product_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $components[] = [
                'name' => $name,
                'quantity' => (int) ($item['quantity'] ?? 1),
            ];
        }

        return [
            'title' => $title,
            'components' => $components,
        ];
    }

    public function generateAiContent(): void
    {
        $this->aiModalError = '';
        $this->aiRowErrors = ['en' => [], 'nl' => []];
        $this->aiRowStatus = ['en' => [], 'nl' => []];

        $this->storeFieldsToTranslations();

        $hasAny = collect($this->aiSelections)->flatten()->isNotEmpty();
        if (!$hasAny) {
            $this->aiModalError = __('Select at least one field to generate.');

            return;
        }

        $titleErrors = [];
        foreach ($this->aiSelections as $loc => $fields) {
            if (!empty($fields)) {
                $locTitle = trim((string) ($this->translations[$loc]['title'] ?? ''));
                if ($locTitle === '') {
                    $lang = strtoupper($loc);
                    $titleErrors[] = __('The :lang title is required to generate :lang content.', ['lang' => $lang]);
                }
            }
        }

        if (!empty($titleErrors)) {
            $this->aiModalError = implode(' ', $titleErrors);

            return;
        }

        $effectiveSelections = $this->aiSelections;

        // Snapshot every (locale, field) about to be overwritten — for revert
        $snapshot = [];
        foreach ($effectiveSelections as $loc => $fields) {
            foreach ($fields as $field) {
                $formField = $this->aiFieldToFormField($field);
                $key = $formField . '_' . $loc;
                $snapshot[$key] = (string) ($this->translations[$loc][$formField] ?? '');
            }
        }
        $this->dispatch('ai-snapshot', snapshot: $snapshot);
        $this->hasRevertSnapshot = !empty($snapshot);

        $this->isGeneratingContent = true;

        try {
            // Each locale fires its own Anthropic call (up to ~90s). PHP's default
            // max_execution_time (30s) would otherwise terminate mid-request.
            @set_time_limit(0);

            $generator = app(ProductContentGenerator::class);
            $allowedFields = $this->aiGeneratableFields();

            foreach ($effectiveSelections as $locale => $rawFields) {
                // Reset the timer before each per-locale API call.
                @set_time_limit(0);
                $fields = array_values(array_unique(array_intersect($rawFields, $allowedFields)));
                if (empty($fields)) {
                    continue;
                }

                foreach ($fields as $f) {
                    $this->aiRowStatus[$locale][$f] = 'pending';
                }

                // 'slug' is computed client-side; it is never sent to the AI.
                $apiFields = array_values(array_filter($fields, fn($f) => $f !== 'slug'));

                $result = [];
                if (!empty($apiFields)) {
                    try {
                        $context = $this->aiBuildContext($locale);
                        $result = $generator->generateFields($apiFields, $locale, $context, AiSetting::SCOPE_GROUP_PRODUCT);
                    } catch (\Throwable $e) {
                        Log::error('AI generation failed for locale', ['locale' => $locale, 'error' => $e->getMessage()]);
                        $msg = __('Generation failed for :locale.', ['locale' => strtoupper($locale)]);
                        foreach ($apiFields as $f) {
                            $this->aiRowStatus[$locale][$f] = 'error';
                            $this->aiRowErrors[$locale][$f] = $msg;
                        }
                        if (in_array('slug', $fields, true)) {
                            $this->aiRowStatus[$locale]['slug'] = 'error';
                            $this->aiRowErrors[$locale]['slug'] = $msg;
                        }

                        continue;
                    }
                }

                foreach ($apiFields as $f) {
                    if (!array_key_exists($f, $result) || trim((string) $result[$f]) === '') {
                        $this->aiRowStatus[$locale][$f] = 'error';
                        $this->aiRowErrors[$locale][$f] = __('No content returned for this field.');

                        continue;
                    }
                    $formField = $this->aiFieldToFormField($f);
                    $this->translations[$locale][$formField] = (string) $result[$f];
                    $this->aiRowStatus[$locale][$f] = 'success';
                }

                if (in_array('slug', $fields, true)) {
                    $sourceTitle = (string) ($this->translations[$locale]['title'] ?? '');
                    if (trim($sourceTitle) === '') {
                        $this->aiRowStatus[$locale]['slug'] = 'error';
                        $this->aiRowErrors[$locale]['slug'] = __('Need a title to generate the slug.');
                    } else {
                        $this->translations[$locale]['slug'] = Str::slug($sourceTitle);
                        $this->aiRowStatus[$locale]['slug'] = 'success';
                    }
                }
            }

            $this->loadFieldsFromTranslations($this->selectedLocale);
            $this->dispatch('wysiwyg-reload');

            $hasError = false;
            foreach ($this->aiRowStatus as $loc => $rows) {
                foreach ($rows as $st) {
                    if ($st === 'error') {
                        $hasError = true;
                        break 2;
                    }
                }
            }

            if (!$hasError) {
                Flux::toast(__('Content generated successfully.'), variant: 'success');
            }
        } catch (\Throwable $e) {
            Log::error('Group product content generation failed', ['error' => $e->getMessage()]);
            $this->aiModalError = __('Generation failed — unexpected error. Please try again.');
        } finally {
            $this->isGeneratingContent = false;
        }
    }

    public function applyRevert(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            $pos = strrpos($key, '_');
            if ($pos === false) {
                continue;
            }
            $field = substr($key, 0, $pos);
            $locale = substr($key, $pos + 1);
            if (!array_key_exists($locale, $this->translations)) {
                continue;
            }
            if (!in_array($field, $this->translatableFields(), true)) {
                continue;
            }
            $this->translations[$locale][$field] = (string) $value;
        }

        $this->loadFieldsFromTranslations($this->selectedLocale);
        $this->dispatch('wysiwyg-reload');
        $this->hasRevertSnapshot = false;

        Flux::toast(__('Reverted last AI generation.'), variant: 'success');
    }

    protected function syncTranslationForLocale($model, array $translatableData, string $locale): void
    {
        if ($locale === $this->mainLocale()) {
            $model->update($translatableData);

            return;
        }

        $translation = Translation::findByModel($model, $locale);
        if ($translation) {
            $translation->update(['fields' => $translatableData]);
        } else {
            Translation::create([
                'translatable_type' => get_class($model),
                'translatable_id' => $model->id,
                'language' => $locale,
                'fields' => $translatableData,
            ]);
        }
    }

    protected function translatableFields(): array
    {
        return ['name', 'title', 'subtitle', 'slug', 'excerpt', 'description', 'content', 'meta_title', 'meta_description'];
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

    protected function usesMainLocale(): bool
    {
        return $this->selectedLocale === $this->mainLocale();
    }

    #[Computed]
    public function computedStock(): int
    {
        if (empty($this->items)) {
            return 0;
        }

        $minSets = PHP_INT_MAX;
        foreach ($this->items as $item) {
            $minSets = min($minSets, (int) ($item['available_sets'] ?? 0));
        }

        return max(0, $minSets === PHP_INT_MAX ? 0 : $minSets);
    }

    #[Computed]
    public function taxCategories()
    {
        return TaxCategory::where('is_active', true)->orderBy('name')->get();
    }



    #[Computed]
    public function discount_groups()
    {
        return DiscountGroup::orderBy('name')->get();
    }

    public function updatedDiscountGroupId($value)
    {
        if ($value) {
            $this->discounts = DiscountGroup::find($value)?->discounts ?? [];
        } else {
            $this->discounts = [];
        }
    }

    #[Computed]
    public function existingMainMedia()
    {
        if (!$this->groupProduct) {
            return [];
        }

        $media = $this->groupProduct->getFirstMedia('main');

        return $media ? [$media] : [];
    }

    #[Computed]
    public function existingGalleryMedia()
    {
        if (!$this->groupProduct) {
            return [];
        }

        return $this->groupProduct->getMedia('gallery')->all();
    }

    #[On('remove-upload')]
    public function handleRemoveUpload(array $params): void
    {
        if (!$this->groupProduct || !isset($params['source'])) {
            return;
        }

        $media = $this->groupProduct->getMedia()->first(fn($m) => $m->getUrl() === $params['source']);
        if ($media) {
            $media->delete();
        }
    }

    protected function wysiwygFieldValues(): array
    {
        return [$this->description, $this->content, $this->product_information];
    }
};
?>

<div x-data="{
    uploads: 0,
    snapshotKey() {
        const id = @js($groupProduct?->id ?? 'new');
        return 'ai_revert_group_' + id;
    },
    revertAi() {
        const raw = sessionStorage.getItem(this.snapshotKey());
        if (!raw) return;
        try {
            const snap = JSON.parse(raw);
            $wire.applyRevert(snap);
            sessionStorage.removeItem(this.snapshotKey());
        } catch (e) {
            console.error('Revert failed:', e);
        }
    },
    init() {
        document.addEventListener('FilePond:addfile', (e) => {
            if (e.detail.file.getMetadata('old') === true) return;
            this.uploads++;
        });
        document.addEventListener('FilePond:processfile', () => this.uploads = Math.max(0, this.uploads - 1));
        document.addEventListener('FilePond:processfileabort', () => this.uploads = Math.max(0, this.uploads - 1));
        document.addEventListener('FilePond:error', (e) => {
            if (e.detail?.file) this.uploads = Math.max(0, this.uploads - 1);
        });
        Livewire.on('ai-snapshot', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            const snap = data && data.snapshot ? data.snapshot : data;
            try {
                sessionStorage.setItem(this.snapshotKey(), JSON.stringify(snap || {}));
            } catch (e) {
                console.error('Snapshot save failed:', e);
            }
        });
        Livewire.on('ai-clear-snapshot', () => {
            sessionStorage.removeItem(this.snapshotKey());
        });
    }
}">
    <x-scroll-to-error />
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $editMode ? __('Edit Group Product') : __('Create Group Product') }}
                </flux:heading>
                <flux:subheading>
                    {{ $editMode ? __('Update your group product details.') : __('Add a new product bundle to your catalog.') }}
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('group-products.index') }}"
                    x-bind:class="{ 'pointer-events-none opacity-50': uploads > 0 }"
                    wire:loading.class="pointer-events-none opacity-50" wire:target="save">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" x-bind:disabled="uploads > 0" wire:loading.attr="disabled"
                    wire:target="save">
                    {{ $editMode ? __('Update Group Product') : __('Save Group Product') }}
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">
            <div class="md:col-span-2 space-y-6">
                <!-- Basic Information -->
                <flux:card class="space-y-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Basic Information') }}</flux:heading>
                            <flux:subheading>{{ __('Group product title, description and general details.') }}
                            </flux:subheading>
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
                            <flux:input wire:model="title" label="{{ __('Title') }}" placeholder="Kitchen Set Bundle" />
                        </div>
                        <div class="flex-1">
                            <x-slug-input model="slug" />
                        </div>
                    </div>

                    <flux:input wire:model="subtitle" label="{{ __('Subtitle') }}"
                        placeholder="Group product subtitle..." />

                    <flux:textarea wire:model="excerpt" label="{{ __('Excerpt') }}"
                        placeholder="Short summary of the product bundle..." rows="2" />

                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="md">{{ __('Short Description & Content') }}</flux:heading>
                        </div>
                        <flux:button variant="primary" size="sm" icon="sparkles" wire:click.prevent="openGenerateModal"
                            wire:loading.attr="disabled" wire:target="openGenerateModal"
                            :disabled="$isGeneratingContent">
                            <span wire:loading.remove wire:target="openGenerateModal">
                                {{ __('Generate') }}
                            </span>
                            <span wire:loading wire:target="openGenerateModal">
                                {{ __('Generating...') }}
                            </span>
                        </flux:button>
                    </div>

                    <x-wysiwyg-editor wire:model="description" :defer-delete="$editMode"
                        label="{{ __('Short Description') }}" placeholder="Brief bundle overview..." />

                    <x-wysiwyg-editor wire:model="content" :defer-delete="$editMode" label="{{ __('Content') }}"
                        placeholder="Detailed bundle content..." />
                </flux:card>

                {{-- <!-- Product Details -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Product Details') }}</flux:heading>
                        <flux:subheading>{{ __('Template, materials and manufacturing details.') }}</flux:subheading>
                    </div>

                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <flux:select wire:model.live="product_template" label="{{ __('Product Template') }}">
                                @foreach (config('products.product_template', []) as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="flex-1">
                            <flux:input wire:model="make" label="{{ __('Make') }}" placeholder="Bundle make..." />
                        </div>
                    </div>

                    <x-wysiwyg-editor wire:model="product_information" :defer-delete="$editMode"
                        label="{{ __('Product Information') }}" placeholder="Detailed bundle information..." />

                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <flux:input wire:model="material_information" label="{{ __('Material Information') }}"
                                placeholder="Material details..." />
                        </div>
                        <div class="flex-1">
                            <flux:select wire:model="material_id" label="{{ __('Primary Material') }}">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($this->materials as $material)
                                <option value="{{ $material->id }}">{{ $material->title }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </flux:card> --}}

                <!-- Pricing & Inventory -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Pricing & Inventory') }}</flux:heading>
                        <flux:subheading>{{ __('Manage pricing, stock limits, and SKU.') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:input wire:model="sku" label="{{ __('SKU') }}" placeholder="GRP-KIT-001" />
                        <flux:input wire:model="article_number" label="{{ __('Article Number') }}"
                            placeholder="ART-GRP-001" />
                    </div>

                    <div x-data="{
                        sanitize(event) {
                            let value = event.target.value;
                            value = value.replace(/[^0-9.]/g, '');
                            const parts = value.split('.');
                            if (parts.length > 2) {
                                value = parts[0] + '.' + parts.slice(1).join('');
                            }
                            const decimalParts = value.split('.');
                            if (decimalParts.length === 2) {
                                decimalParts[1] = decimalParts[1].slice(0, 2);
                                value = decimalParts.join('.');
                            }
                            event.target.value = value;
                        }
                    }" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                        <flux:field class="flex flex-col gap-1">
                            <flux:label>{{ __('Price') }}</flux:label>
                            <div @class([
                                'flex w-full rounded-lg border shadow-xs overflow-hidden bg-zinc-50 dark:bg-zinc-800/50',
                                'border-zinc-200 dark:border-white/10' => !$errors->has('price'),
                                'border-red-500 dark:border-red-500' => $errors->has('price'),
                            ])>
                                <span
                                    class="flex items-center px-4 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400 bg-zinc-800/5 dark:bg-white/20 border-r border-zinc-200 dark:border-white/10">
                                    {{ config('app.currency_symbol') }}
                                </span>
                                <input wire:model="price" type="text" inputmode="decimal" placeholder="299.95"
                                    disabled
                                    class="flex-1 min-w-0 px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400 placeholder-zinc-400 focus:outline-none cursor-not-allowed" />
                            </div>
                            <flux:subheading size="xs">{{ __('Derived from component products and discount.') }}</flux:subheading>
                            <flux:error name="price" />
                        </flux:field>

                        <flux:field class="flex flex-col gap-1">
                            <flux:label>{{ __('Original Price') }}</flux:label>
                            <div @class([
                                'flex w-full rounded-lg border shadow-xs overflow-hidden bg-zinc-50 dark:bg-zinc-800/50',
                                'border-zinc-200 dark:border-white/10' => !$errors->has('original_price'),
                                'border-red-500 dark:border-red-500' => $errors->has('original_price'),
                            ])>
                                <span
                                    class="flex items-center px-4 text-sm whitespace-nowrap text-zinc-500 dark:text-zinc-400 bg-zinc-800/5 dark:bg-white/20 border-r border-zinc-200 dark:border-white/10">
                                    {{ config('app.currency_symbol') }}
                                </span>
                                <input wire:model="original_price" type="text" inputmode="decimal" placeholder="349.95"
                                    disabled
                                    class="flex-1 min-w-0 px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400 placeholder-zinc-400 focus:outline-none cursor-not-allowed" />
                            </div>
                            <flux:subheading size="xs">{{ __('Sum of all component product prices.') }}</flux:subheading>
                            <flux:error name="original_price" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:select wire:model="tax_category_id" label="{{ __('Tax Category') }}">
                            <option value="">{{ __('— None —') }}</option>
                            @foreach ($this->taxCategories as $taxCat)
                                <option value="{{ $taxCat->id }}">{{ $taxCat->name }}</option>
                            @endforeach
                        </flux:select>


                        <flux:input wire:model.live="discount" type="number" inputmode="decimal" placeholder="50"
                            label="{{ __('Discount Percentage') }}" icon:trailing="percent-badge"
                            x-on:input="sanitize($event)" x-on:paste="setTimeout(() => sanitize($event), 0)" />
                    </div>


                </flux:card>

                {{-- <!-- Technical Details -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Technical Details') }}</flux:heading>
                        <flux:subheading>{{ __('Specific technical properties for this bundle.') }}</flux:subheading>
                    </div>
                    <div class="grid grid-cols-3 gap-6">
                        @foreach ($all_properties as $index => $property)
                        <flux:select size="sm" placeholder="{{ $property->name }}"
                            wire:model="product_properties.{{ $property->id }}" label="{{ $property->name }}">
                            @forelse ($property['propertyValues'] as $value)
                            <flux:select.option value="{{ $value->value }}" wire:key="{{ $value->id }}">
                                {{ $value->value }}
                            </flux:select.option>
                            @empty
                            <flux:select.option value="" disabled>{{ __('No values') }}
                            </flux:select.option>
                            @endforelse
                        </flux:select>
                        @endforeach
                    </div>
                </flux:card> --}}

                <!-- Shipping & Dimensions -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Shipping & Dimensions') }}</flux:heading>
                        <flux:subheading>{{ __('Physical attributes for calculating shipping.') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                        <flux:input wire:model="weight" label="{{ __('Weight (kg)') }}" type="number" min="0"
                            step="0.01" placeholder="2.5" />
                        <flux:input wire:model="length" label="{{ __('Length (cm)') }}" type="number" min="0"
                            step="0.01" placeholder="60" />
                        <flux:input wire:model="width" label="{{ __('Width (cm)') }}" type="number" min="0" step="0.01"
                            placeholder="40" />
                        <flux:input wire:model="height" label="{{ __('Height (cm)') }}" type="number" min="0"
                            step="0.01" placeholder="30" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <flux:input type="number" wire:model="packaging_unit" label="{{ __('Packaging Unit') }}" />
                        <flux:input type="number" wire:model="delivery_dates_no_stock"
                            label="{{ __('Delivery Days (No Stock)') }}" />
                        <flux:input type="number" wire:model="delivery_dates_in_stock"
                            label="{{ __('Delivery Days (In Stock)') }}" />
                    </div>
                </flux:card>

                <!-- SEO Metadata -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('SEO Metadata') }}</flux:heading>
                        <flux:subheading>{{ __('Optimize search engine visibility.') }}</flux:subheading>
                    </div>

                    <flux:input wire:model="meta_title" label="{{ __('Meta Title') }}"
                        placeholder="{{ __('Custom title for search engines') }}" />

                    <flux:textarea wire:model="meta_description" label="{{ __('Meta Description') }}"
                        placeholder="{{ __('Brief description for search engine results') }}" rows="3" />
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">

                <!-- Component Products -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Component Products') }}</flux:heading>
                        <flux:subheading>{{ __('Products included in this bundle.') }}</flux:subheading>
                    </div>

                    <div class="relative">
                        <flux:input wire:model.live.debounce.300ms="product_search"
                            placeholder="{{ __('Search products...') }}" icon="magnifying-glass" />

                        @if (!empty($search_results))
                            <div
                                class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                @foreach ($search_results as $product)
                                    <button type="button" wire:click="addProduct({{ $product['id'] }})"
                                        class="w-full px-4 py-2 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors">
                                        <div class="font-medium text-sm">{{ $product['name'] }}</div>
                                        <div class="text-xs text-zinc-500">{{ $product['sku'] }} •
                                            {{ __('Stock') }}: {{ $product['stock'] }}
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if (count($items) === 0)
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-zinc-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="mt-2 text-sm text-zinc-500">{{ __('No products added yet.') }}</p>
                            <p class="text-xs text-zinc-400">{{ __('Search and add products above') }}</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($items as $index => $item)
                                <div
                                    class="p-3 border rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm">{{ $item['product_name'] }}</div>
                                            <div class="text-xs text-zinc-500 mt-1">
                                                {{ __('Stock') }}: {{ $item['product_stock'] }} •
                                                {{ __('Available sets') }}: {{ $item['available_sets'] }}
                                            </div>
                                        </div>
                                        <flux:button wire:click="removeItem({{ $index }})" icon="trash" variant="danger"
                                            size="xs" />
                                    </div>

                                    <div class="flex items-end gap-2">
                                        <div class="flex-1">
                                            <flux:input type="number" wire:model.live="items.{{ $index }}.quantity"
                                                label="{{ __('Quantity') }}" min="1" size="sm" />
                                        </div>
                                        <div class="pb-2">
                                            <flux:badge :color="$item['available_sets'] > 0 ? 'green' : 'red'" size="sm">
                                                {{ $item['available_sets'] }} {{ __('sets') }}
                                            </flux:badge>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-4 border-t border-zinc-200 gap-4 dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-sm">{{ __('Total Available Sets') }}</span>
                                <flux:badge size="lg" :color="$this->computedStock > 0 ? 'blue' : 'red'">
                                    {{ $this->computedStock }}
                                </flux:badge>
                            </div>
                            <div class="flex items-center justify-between mt-4">
                                <span class="font-medium text-sm">{{ __('Final Price') }}</span>
                                {{ format_price($this->finalPrice, config('app.currency_symbol')) }}
                            </div>
                        </div>
                    @endif
                </flux:card>

                <!-- Status -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Visibility & Status') }}</flux:heading>
                    </div>

                    <flux:select wire:model="state" label="{{ __('State') }}">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="unavailable">{{ __('Archived') }}</option>
                    </flux:select>

                    @if ($editMode && $groupProduct)
                        <div
                            class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                        {{ __('Available Stock') }}
                                    </div>
                                    <div class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                        {{ __('Computed from components') }}
                                    </div>
                                </div>
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    {{ $this->computedStock }}
                                </div>
                            </div>
                        </div>
                    @endif
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

                    <flux:field>
                        <flux:label>{{ __('Gallery Images') }}</flux:label>
                        <x-file-pond wire:model="gallery_images" multiple accept="image/*"
                            :uploads="$this->existingGalleryMedia" />
                        <flux:error name="gallery_images" />
                        <flux:error name="gallery_images.*" />
                    </flux:field>
                </flux:card>
            </div>
        </div>
    </form>

    <flux:modal wire:model.self="showGenerateModal" class="md:w-2xl" :dismissible="!$isGeneratingContent">
        @php
            $aiFields = $this->aiGeneratableFields();
            $aiLabels = $this->aiFieldLabels();
            $aiLocales = array_keys(config('app.available_locales'));
        @endphp

        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Generate Content') }}</flux:heading>
                <flux:subheading>
                    {{ __('Pick which fields to generate per language. The dot indicates fields that already have content.') }}
                </flux:subheading>
            </div>

            @if ($aiModalError)
                <div
                    class="rounded-md border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                    {{ $aiModalError }}
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @foreach ($aiLocales as $loc)
                    <flux:button type="button" size="xs" variant="subtle" wire:click="aiSelectAll('{{ $loc }}')">
                        {{ __('Select all :loc', ['loc' => strtoupper($loc)]) }}
                    </flux:button>
                    <flux:button type="button" size="xs" variant="ghost" wire:click="aiDeselectAll('{{ $loc }}')">
                        {{ __('Deselect all :loc', ['loc' => strtoupper($loc)]) }}
                    </flux:button>
                @endforeach
            </div>

            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr>
                            <th class="text-left font-medium px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                {{ __('Field') }}
                            </th>
                            @foreach ($aiLocales as $loc)
                                <th class="text-left font-medium px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ strtoupper($loc) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($aiFields as $field)
                            <tr>
                                <td class="px-3 py-2 align-middle text-zinc-700 dark:text-zinc-200">
                                    {{ $aiLabels[$field] ?? $field }}
                                </td>
                                @foreach ($aiLocales as $loc)
                                    @php
                                        $status = $aiRowStatus[$loc][$field] ?? null;
                                        $errorMsg = $aiRowErrors[$loc][$field] ?? null;
                                    @endphp
                                    <td class="px-3 py-2 align-middle">
                                        <div class="flex items-center gap-2">
                                            @if ($status === 'pending')
                                                <flux:icon.arrow-path class="size-4 text-blue-500 animate-spin shrink-0" />
                                            @elseif ($status === 'success')
                                                <flux:icon.check class="size-4 text-emerald-600 shrink-0" />
                                            @elseif ($status === 'error')
                                                <flux:icon.x-mark class="size-4 text-red-600 shrink-0" />
                                            @else
                                                <flux:checkbox wire:model="aiSelections.{{ $loc }}" value="{{ $field }}" />
                                            @endif

                                            @if (!empty($aiHasContent[$loc][$field]))
                                                <span title="{{ __('This field already has content') }}"
                                                    class="size-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                            @endif

                                            @if ($status === 'error' && $errorMsg)
                                                <span class="text-xs text-red-600">{{ $errorMsg }}</span>
                                            @endif
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between gap-2 pt-2">
                <div>
                    @if ($hasRevertSnapshot)
                        <flux:button type="button" variant="ghost" icon="arrow-uturn-left"
                            x-on:click="revertAi(); $wire.set('showGenerateModal', false)">
                            {{ __('Revert last generation') }}
                        </flux:button>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeGenerateModal"
                        :disabled="$isGeneratingContent">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="button" variant="primary" icon="sparkles" wire:click="generateAiContent"
                        wire:loading.attr="disabled" wire:target="generateAiContent">
                        <span wire:loading.remove wire:target="generateAiContent">
                            {{ $hasRevertSnapshot ? __('Regenerate') : __('Proceed') }}
                        </span>
                        <span wire:loading wire:target="generateAiContent">
                            {{ __('Generating...') }}
                        </span>
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <x-file-upload-loader />
</div>
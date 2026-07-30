<?php

use App\Concerns\HandlesWysiwygMedia;
use App\Models\AiSetting;
use App\Models\DiscountGroup;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\Taxon;
use App\Models\WarrantyGroup;
use App\Services\ProductContentGenerator;
use App\Services\SkuGenerator;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;
use Vanilo\Taxes\Models\TaxCategory;
use Vanilo\Translation\Models\Translation;

new class extends Component
{
    use HandlesWysiwygMedia;
    use WithFileUploads;

    // Product type
    public string $product_type = 'simple';

    // Variable product fields
    public array $product_attributes = [];

    // Product Properties
    public Collection $all_properties;

    public array $product_properties = [];

    public ?Product $product = null;

    public array $variations = [];

    // Direct table fields
    public string $name = '';

    public string $title = '';

    public string $subtitle = '';

    public string $sku = '';

    public string $article_number = '';

    public int $stock = 0;

    public $price = 0.0;

    public $original_price = 0.0;

    public $weight = 0.0;

    public $width = 0.0;

    public $height = 0.0;

    public $length = 0.0;

    public string $slug = '';

    public string $excerpt = '';

    public string $content = '';

    public string $description = '';

    public string $meta_title_nl = '';

    public string $meta_title_en = '';

    public string $meta_description_nl = '';

    public string $meta_description_en = '';

    public ?int $packaging_unit = null;

    public ?int $delivery_dates_no_stock = null;

    public ?int $delivery_dates_in_stock = null;

    public ?int $packing_group = null;

    public ?bool $allow_singulars = false;

    public string $state = 'active';

    public $main_image;

    public array $gallery_images = [];

    public array $selected_taxons = [];

    public array $selected_brand_taxons = [];


    // Edit mode
    public ?int $productId = null;

    public bool $editMode = false;

    public string $originalProductType = 'simple';

    public ?string $tax_category_id = null;

    public array $up_sell_ids = [];

    public array $cross_sell_ids = [];

    public $discount_search = '';

    public $discount_group_id = null;

    public $discounts = [];



    // Locale switching
    public string $selectedLocale = '';

    public array $translations = [];

    // AI generation state
    public bool $isGeneratingContent = false;

    public bool $showGenerateModal = false;

    /**
     * Field+locale checkbox selections in the modal.
     * Shape: ['en' => ['title', 'content'], 'nl' => ['title']]
     */
    public array $aiSelections = ['en' => [], 'nl' => []];

    /**
     * Per-row state during/after generation.
     * Shape: ['en' => ['title' => 'pending'|'success'|'error', ...], 'nl' => [...]]
     */
    public array $aiRowStatus = ['en' => [], 'nl' => []];

    /**
     * Per-row error messages.
     * Shape: ['en' => ['title' => 'message', ...], 'nl' => [...]]
     */
    public array $aiRowErrors = ['en' => [], 'nl' => []];

    public string $aiModalError = '';

    public bool $hasRevertSnapshot = false;

    /** Pre-computed per-field has-content map; set when the generate modal opens. */
    public array $aiHasContent = [];

    protected function initTranslations(): void
    {
        foreach (array_keys($this->locales()) as $locale) {
            $this->translations[$locale] = array_fill_keys($this->translatableFields(), '');
            $this->translations[$locale]['meta_title'] = '';
            $this->translations[$locale]['meta_description'] = '';
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

    protected function resolveProduct(string $productKey): array
    {
        if (! str_contains($productKey, '_')) {
            abort(400, __('Invalid product key format.'));
        }

        [$type, $id] = explode('_', $productKey, 2);
        $type = trim($type);
        $id = trim($id);

        if ($type === 'simple') {
            $model = Product::with(['propertyValues.property', 'metas', 'taxons.taxonomy'])->findOrFail($id);

            return [$model, 'simple'];
        }

        abort(400, __('Unknown product type.'));
    }

    protected function getTranslatedModel($model)
    {
        if (! $model) {
            return null;
        }

        if ($this->usesMainLocale()) {
            return $model;
        }

        $translation = Translation::findByModel($model, app()->getLocale());

        if (! $translation) {
            return $model;
        }

        $class = get_class($model);
        $translatedModel = new $class;

        $fields = is_array($translation->fields) ? $translation->fields : [];
        $filteredData = collect($fields)->only($this->translatableFields())->toArray();

        $finalAttributes = array_merge($model->getAttributes(), $filteredData);

        $translatedModel->forceFill($finalAttributes);
        $translatedModel->exists = true;

        // Preserve loaded relations
        foreach ($model->getRelations() as $name => $relation) {
            $translatedModel->setRelation($name, $relation);
        }

        return $translatedModel;
    }

    protected function populateAttributesAndVariations($model): void
    {
        $properties = [];
        foreach ($model->variants as $variant) {
            foreach ($variant->propertyValues as $pv) {
                $properties[$pv->property->name][] = $pv->title;
            }
        }

        $this->product_attributes = [];
        foreach ($properties as $name => $values) {
            $this->product_attributes[] = [
                'name' => (string) $name,
                'values' => implode(', ', array_unique($values)),
            ];
        }

        $this->variations = [];
        foreach ($model->variants as $variant) {
            $props = [];
            foreach ($variant->propertyValues as $pv) {
                $props[(string) $pv->property->name] = (string) $pv->title;
            }

            $this->variations[] = [
                'id' => (int) $variant->id,
                'sku' => (string) ($variant->sku ?? ''),
                'price' => (float) ($variant->price ?? 0),
                'stock' => (float) ($variant->stock ?? 0),
                'properties' => $props,
            ];
        }
    }

    public function mount($productKey = null)
    {
        $this->selectedLocale = $this->mainLocale();
        $this->initTranslations();

        $allProps = Property::with('propertyValues')->get();
        $this->all_properties = $allProps;

        if (! $productKey) {
            $defaultCategory = TaxCategory::where('is_default', true)->first();
            if ($defaultCategory) {
                $this->tax_category_id = (string) $defaultCategory->id;
            }
            return;
        }

        [$model, $type] = $this->resolveProduct($productKey);

        $this->product = $model;

        $this->editMode = (bool) true;
        $this->productId = (int) $model->id;
        $this->product_type = (string) $model->product_type;
        $this->originalProductType = $this->product_type;
        $this->title = (string) $model->title;
        $this->subtitle = (string) $model->subtitle;
        $this->sku = (string) $model->sku;
        $this->article_number = (string) $model->article_number;
        $this->stock = (int) ($model->stock ?? 0);
        $this->price = (float) $model->price;
        $this->original_price = (float) $model->original_price;
        $this->weight = (float) $model->weight;
        $this->width = (float) $model->width;
        $this->height = (float) $model->height;
        $this->length = (float) $model->length;
        $this->slug = (string) $model->slug;
        $this->excerpt = (string) $model->excerpt;
        $this->content = (string) $model->content;
        $this->description = (string) $model->description;
        $this->meta_title_nl = $this->localizedSeoValue($model, 'nl', 'meta_title');
        $this->meta_title_en = $this->localizedSeoValue($model, 'en', 'meta_title');
        $this->meta_description_nl = $this->localizedSeoValue($model, 'nl', 'meta_description');
        $this->meta_description_en = $this->localizedSeoValue($model, 'en', 'meta_description');
        $this->packaging_unit = $model->packaging_unit !== null ? (int) $model->packaging_unit : null;
        $this->delivery_dates_no_stock = $model->delivery_dates_no_stock !== null ? (int) $model->delivery_dates_no_stock : null;
        $this->delivery_dates_in_stock = $model->delivery_dates_in_stock !== null ? (int) $model->delivery_dates_in_stock : null;
        $this->packing_group = $model->packing_group !== null ? (int) $model->packing_group : null;
        $this->allow_singulars = $model->allow_singulars !== null ? (bool) $model->allow_singulars : false;

        $this->tax_category_id = $model->tax_category_id ? (string) $model->tax_category_id : null;
        $this->state = (string) $model->state->value();
        $this->discount_group_id = (int) $model->discount_group_id;

        if ($this->discount_group_id) {
            $this->updatedDiscountGroupId($this->discount_group_id);
        }

        foreach ($model->metas as $meta) {
            if (property_exists($this, $meta->meta_key)) {
                $propType = gettype($this->{$meta->meta_key});
                $this->{$meta->meta_key} = $propType == 'double' ? (float) $meta->meta_value : (string) $meta->meta_value;
            }
        }

        foreach ($allProps as $property) {
            $this->product_properties[$property->id] = $model->propertyValues
                ->where('property_id', $property->id)
                ->pluck('value')
                ->map(fn ($value) => (string) $value)
                ->unique()
                ->values()
                ->all();
        }

        $this->selected_taxons = $model->taxons
            ->filter(fn ($taxon) => $this->taxonBelongsTo($taxon, 'Category'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->selected_brand_taxons = $this->selectedBrandTaxonIdsForModel($model);

        $this->up_sell_ids = $this->loadRelatedIds($model, ProductRelation::TYPE_UPSELL);
        $this->cross_sell_ids = $this->loadRelatedIds($model, ProductRelation::TYPE_CROSSSELL);

        // Store main locale fields into translations array
        $this->storeFieldsToTranslations($this->mainLocale());

        // Load translations for other locales from DB
        foreach ($this->otherLocales() as $locale) {
            $translation = Translation::findByModel($model, $locale);
            if ($translation) {
                // Determine the fields array, handling nested structure if present
                $fields = is_array($translation->fields) ? $translation->fields : [];
                if (isset($fields['fields']) && is_array($fields['fields'])) {
                    $fields = array_merge($fields, $fields['fields']);
                }

                foreach ($this->translatableFields() as $field) {
                    // 1. Try direct translation attribute (e.g. name, slug)
                    // 2. Try the fields array
                    $value = $translation->{$field} ?? ($fields[$field] ?? '');

                    // Fallback for title/name mapping if one is missing
                    if (empty($value)) {
                        if ($field === 'title') {
                            $value = $translation->name ?? ($fields['name'] ?? '');
                        } elseif ($field === 'name') {
                            $value = $fields['title'] ?? '';
                        }
                    }

                    $this->translations[$locale][$field] = (string) $value;
                }

                foreach (['meta_title', 'meta_description'] as $field) {
                    $this->translations[$locale][$field] = (string) ($fields[$field] ?? $translation->{$field} ?? $this->translations[$locale][$field] ?? '');
                }
            }
        }

        $this->translations['nl']['meta_title'] = $this->meta_title_nl;
        $this->translations['en']['meta_title'] = $this->meta_title_en;
        $this->translations['nl']['meta_description'] = $this->meta_description_nl;
        $this->translations['en']['meta_description'] = $this->meta_description_en;
    }

    protected function localizedSeoValue($model, string $locale, string $field): string
    {
        $localizedColumn = "{$field}_{$locale}";
        $localizedValue = (string) ($model->{$localizedColumn} ?? '');

        if (trim($localizedValue) !== '') {
            return $localizedValue;
        }

        if ($locale === $this->mainLocale()) {
            return (string) ($model->getRawOriginal($field) ?? $model->{$field} ?? '');
        }

        $translation = Translation::findByModel($model, $locale);

        if (! $translation) {
            return '';
        }

        $fields = is_array($translation->fields) ? $translation->fields : [];

        if (isset($fields['fields']) && is_array($fields['fields'])) {
            $fields = array_merge($fields, $fields['fields']);
        }

        return (string) ($fields[$field] ?? $translation->{$field} ?? '');
    }

    protected function rules()
    {
        $sameType = $this->editMode && $this->product_type === $this->originalProductType;
        $articleRule = Rule::unique('products', 'article_number');
        $slugRule = Rule::unique('products', 'slug');
        $skuRule = Rule::unique('products', 'sku');

        if ($sameType) {
            $articleRule->ignore($this->productId);
            $slugRule->ignore($this->productId);
            $skuRule->ignore($this->productId);
        }

        return [
            'product_type' => 'required|string|in:simple',
            'state' => 'required|string|in:active,draft,unavailable',
            'title' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:500', $slugRule],
            'subtitle' => 'nullable|string|max:255',
            'sku' => ['required', 'string', 'max:255', $skuRule],
            'article_number' => ['required', 'string', 'max:255', $articleRule],
            'price' => 'nullable|numeric|gt:0|max:99999999999',
            'original_price' => 'required|numeric|gt:0|max:99999999999',
            'stock' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|gte:0',
            'width' => 'nullable|numeric|gte:0',
            'height' => 'nullable|numeric|gte:0',
            'length' => 'nullable|numeric|gte:0',
            'excerpt' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'meta_title_nl' => 'nullable|string|max:255',
            'meta_title_en' => 'nullable|string|max:255',
            'meta_description_nl' => 'nullable|string|max:500',
            'meta_description_en' => 'nullable|string|max:500',
            'packaging_unit' => 'nullable|integer|min:0',
            'delivery_dates_no_stock' => 'nullable|integer|min:0',
            'delivery_dates_in_stock' => 'nullable|integer|min:0',
            'packing_group' => 'nullable|integer|min:0',
            'allow_singulars' => 'nullable|boolean',
            'main_image' => 'nullable|image|max:10240',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'image|max:10240',
            'selected_taxons' => 'nullable|array',
            'selected_taxons.*' => 'exists:taxons,id',
            'selected_brand_taxons' => 'nullable|array',
            'selected_brand_taxons.*' => 'exists:taxons,id',

            'tax_category_id' => 'nullable|exists:tax_categories,id',
            'discount_group_id' => 'nullable|integer|in:0,'.implode(',', DB::table('discount_groups')->pluck('id')->toArray()),
            'up_sell_ids' => 'nullable|array',
            'up_sell_ids.*' => 'integer|exists:products,id',
            'cross_sell_ids' => 'nullable|array',
            'cross_sell_ids.*' => 'integer|exists:products,id',
            'product_properties' => 'nullable|array',
            'product_properties.*' => 'nullable|array',
            'product_properties.*.*' => 'nullable|string|max:255',
        ];
    }

    protected function messages()
    {
        return [
            'title.required' => __('Enter a product title.'),
            'sku.required' => __('Enter a SKU.'),
            'sku.unique' => __('This SKU already exists.'),
            'article_number.required' => __('Enter an article number.'),
            'article_number.unique' => __('This article number already exists.'),
            'stock.required' => __('Enter stock quantity.'),
            'stock.integer' => __('Stock must be a whole number.'),
            'price.gt' => __('Price must be above 0.'),
            'price.max' => __('Price value is too large.'),
            'original_price.gt' => __('Original Price must be above 0.'),
            'original_price.max' => __('Original Price value is too large.'),
            'original_price.required' => __('Enter an original price.'),
            'weight.gte' => __('Must be 0 or above.'),
            'width.gte' => __('Must be 0 or above.'),
            'height.gte' => __('Must be 0 or above.'),
            'length.gte' => __('Must be 0 or above.'),
            'slug.unique' => __('This slug already exists.'),
            'main_image.image' => __('Must be an image.'),
            'main_image.max' => __('Max 10MB.'),
            'gallery_images.*.image' => __('Each file must be an image.'),
            'gallery_images.*.max' => __('Each image max 10MB.'),
            'selected_taxons.required' => __('Select at least one category.'),
            'packaging_unit.integer' => __('Must be a whole number.'),
            'delivery_dates_no_stock.integer' => __('Must be a whole number.'),
            'delivery_dates_in_stock.integer' => __('Must be a whole number.'),
            'packing_group.integer' => __('Must be a whole number.'),
            'discount_group_id.exists' => __('Discount group not found.'),
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'meta_title_nl' => __('Dutch Meta Title'),
            'meta_title_en' => __('English Meta Title'),
            'meta_description_nl' => __('Dutch Meta Description'),
            'meta_description_en' => __('English Meta Description'),
        ];
    }

    #[Computed]
    public function taxCategories()
    {
        return TaxCategory::where('is_active', true)->orderBy('name')->get();
    }

    public function updatedProductType($value)
    {
        if ($value === 'variable' && empty($this->product_attributes)) {
            $this->addAttribute();
        }
    }

    public function addAttribute()
    {
        $this->product_attributes[] = ['name' => '', 'values' => ''];
    }

    public function removeAttributeVariations($index)
    {
        $removedName = $this->product_attributes[$index]['name'] ?? null;

        unset($this->product_attributes[$index]);
        $this->product_attributes = array_values($this->product_attributes);

        if ($removedName) {
            $this->variations = array_values(array_filter($this->variations, fn ($v) => ! array_key_exists($removedName, $v['properties'] ?? [])));
        }
    }

    public function resetAttributeVariations($index)
    {
        $removedName = $this->product_attributes[$index]['name'] ?? null;

        $this->product_attributes[$index]['name'] = '';
        $this->product_attributes[$index]['values'] = '';

        if ($removedName) {
            $this->variations = array_values(array_filter($this->variations, fn ($v) => ! array_key_exists($removedName, $v['properties'] ?? [])));
        }
    }

    public function generateVariations()
    {
        $parsedAttributes = [];
        foreach ($this->product_attributes as $attr) {
            if (! empty($attr['name']) && ! empty($attr['values'])) {
                $vals = array_filter(array_map('trim', explode(',', $attr['values'])));
                if (! empty($vals)) {
                    $parsedAttributes[$attr['name']] = $vals;
                }
            }
        }

        if (empty($parsedAttributes)) {
            return;
        }

        $combinations = [[]];
        foreach ($parsedAttributes as $attrName => $attrValues) {
            $newCombinations = [];
            foreach ($combinations as $combination) {
                foreach ($attrValues as $value) {
                    $newCombinations[] = array_merge($combination, [$attrName => $value]);
                }
            }
            $combinations = $newCombinations;
        }

        foreach ($combinations as $combo) {
            $exists = collect($this->variations)->contains(function ($v) use ($combo) {
                return $v['properties'] === $combo;
            });

            if (! $exists) {
                $this->variations[] = [
                    'sku' => '',
                    'price' => '',
                    'stock' => 0,
                    'properties' => $combo,
                ];
            }
        }
    }

    public function removeVariation($index)
    {
        unset($this->variations[$index]);
        $this->variations = array_values($this->variations);
    }

    private function validateAttributesVariations(): void
    {
        if ($this->product_type !== 'variable') {
            return;
        }

        $hasFilledAttribute = false;
        $hasPartialAttribute = false;

        foreach ($this->product_attributes as $index => $attr) {
            $nameEmpty = empty(trim($attr['name'] ?? ''));
            $valuesEmpty = empty(trim($attr['values'] ?? ''));

            if (! $nameEmpty && ! $valuesEmpty) {
                $hasFilledAttribute = true;
            } elseif (! $nameEmpty || ! $valuesEmpty) {
                $hasPartialAttribute = true;

                if ($nameEmpty) {
                    $this->addError("product_attributes.{$index}.name", __('The attribute name is required.'));
                }

                if ($valuesEmpty) {
                    $this->addError("product_attributes.{$index}.values", __('The attribute values are required.'));
                }
            }
        }

        if (! $hasFilledAttribute && ! $hasPartialAttribute) {
            $this->addError('attributes_required', __('At least one attribute must have both name and values filled.'));
        }

        foreach ($this->variations as $index => $variation) {
            $sku = trim($variation['sku'] ?? '');
            $price = trim((string) ($variation['price'] ?? ''));
            $stock = $variation['stock'] ?? null;

            if ($sku === '') {
                $this->addError("variations.{$index}.sku", __('The SKU is required.'));
            }

            if ($price === '') {
                $this->addError("variations.{$index}.price", __('The price is required.'));
            } elseif (! is_numeric($price) || $price < 0) {
                $this->addError("variations.{$index}.price", __('The price must be a valid positive number.'));
            } elseif ($price > 99999999.99) {
                $this->addError("variations.{$index}.price", __('The price is too large.'));
            }

            if ($stock === null || $stock === '') {
                $this->addError("variations.{$index}.stock", __('The stock is required.'));
            } elseif (! is_numeric($stock) || (int) $stock < 0) {
                $this->addError("variations.{$index}.stock", __('The stock must be a valid non-negative number.'));
            }
        }
    }

    public function generateSku(): void
    {
        $this->storeFieldsToTranslations();

        $mainTitle = $this->translations[$this->mainLocale()]['title'] ?? '';

        if (empty(trim($mainTitle))) {
            $this->addError('sku', __('Enter a product title first to generate a SKU.'));

            return;
        }

        $this->sku = app(SkuGenerator::class)->fromTitle($mainTitle);
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
        return AiSetting::GENERATABLE_FIELDS;
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

    /**
     * Returns whether each (field, locale) currently has non-empty content.
     * Shape: ['en' => ['slug' => true, ...], 'nl' => [...]]
     * Does NOT call storeFieldsToTranslations — that is done in openGenerateModal().
     */
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

        $this->resetErrorBag('content_generation');
        $this->aiModalError = '';
        $this->aiRowStatus = ['en' => [], 'nl' => []];
        $this->aiRowErrors = ['en' => [], 'nl' => []];

        $defaults = AiSetting::defaults();

        $this->aiSelections = [
            'en' => array_values(array_intersect((array) ($defaults['default_fields_en'] ?? []), $this->aiGeneratableFields())),
            'nl' => array_values(array_intersect((array) ($defaults['default_fields_nl'] ?? []), $this->aiGeneratableFields())),
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
        if (! array_key_exists($locale, $this->aiSelections)) {
            return;
        }
        $this->aiSelections[$locale] = $this->aiGeneratableFields();
    }

    public function aiDeselectAll(string $locale): void
    {
        if (! array_key_exists($locale, $this->aiSelections)) {
            return;
        }
        $this->aiSelections[$locale] = [];
    }

    /**
     * Build the product context for a single-locale API call.
     * Each locale uses only its own title and its own AI settings.
     */
    protected function aiBuildContext(string $locale): array
    {
        $title = trim((string) ($this->translations[$locale]['title'] ?? ''));

        $category = '';
        if (! empty($this->selected_taxons)) {
            $taxonNames = Taxonomy::with('taxons')->get()->flatMap(fn ($tx) => $tx->taxons)->whereIn('id', $this->selected_taxons)->pluck('name')->filter()->implode(', ');
            $category = (string) $taxonNames;
        }

        $attributes = [];
        foreach (config('products.meta_fields', []) as $key) {
            $value = $this->{$key} ?? null;
            if (is_string($value) && trim($value) !== '') {
                $attributes[$key] = $value;
            } elseif (is_numeric($value)) {
                $attributes[$key] = (string) $value;
            }
        }

        return [
            'title' => $title,
            'brand' => $this->selectedBrandNamesForContext(),
            'category' => $category,
            'attributes' => $attributes,
        ];
    }

    public function generateAiContent(): void
    {
        $this->aiModalError = '';
        $this->aiRowErrors = ['en' => [], 'nl' => []];
        $this->aiRowStatus = ['en' => [], 'nl' => []];

        $this->storeFieldsToTranslations();

        $hasAny = collect($this->aiSelections)->flatten()->isNotEmpty();
        if (! $hasAny) {
            $this->aiModalError = __('Select at least one field to generate.');

            return;
        }

        // Validate: each selected locale must have its own title filled
        $titleErrors = [];
        foreach ($this->aiSelections as $loc => $fields) {
            if (! empty($fields)) {
                $locTitle = trim((string) ($this->translations[$loc]['title'] ?? ''));
                if ($locTitle === '') {
                    $lang = strtoupper($loc);
                    $titleErrors[] = __('The :lang title is required to generate :lang content.', ['lang' => $lang]);
                }
            }
        }

        if (! empty($titleErrors)) {
            $this->aiModalError = implode(' ', $titleErrors);

            return;
        }

        // Auto-fill SKU for simple products so the product is identifiable
        if ($this->product_type === 'simple' && empty(trim($this->sku))) {
            foreach ($this->aiSelections as $loc => $fields) {
                if (! empty($fields)) {
                    $sourceTitle = trim((string) ($this->translations[$loc]['title'] ?? ''));
                    if ($sourceTitle !== '') {
                        $this->sku = app(SkuGenerator::class)->fromTitle($sourceTitle);
                        break;
                    }
                }
            }
        }

        $effectiveSelections = $this->aiSelections;

        // Snapshot every (locale, field) about to be overwritten — for revert
        $snapshot = [];
        foreach ($effectiveSelections as $loc => $fields) {
            foreach ($fields as $field) {
                $formField = $this->aiFieldToFormField($field);
                $key = $formField.'_'.$loc;
                $snapshot[$key] = (string) ($this->translations[$loc][$formField] ?? '');
            }
        }
        $this->dispatch('ai-snapshot', snapshot: $snapshot);
        $this->hasRevertSnapshot = ! empty($snapshot);

        $this->isGeneratingContent = true;

        try {
            // Each locale fires its own Anthropic call (up to ~90s). PHP's default
            // max_execution_time (30s) would otherwise terminate mid-request.
            @set_time_limit(0);

            $generator = app(ProductContentGenerator::class);

            // Allow 'title' through even though it is not in GENERATABLE_FIELDS —
            // it may have been auto-added to $effectiveSelections above.
            $allowedFields = array_merge($this->aiGeneratableFields(), ['title']);

            foreach ($effectiveSelections as $locale => $rawFields) {
                // Reset the timer before each per-locale API call.
                @set_time_limit(0);
                $fields = array_values(array_unique(array_intersect($rawFields, $allowedFields)));
                if (empty($fields)) {
                    continue;
                }

                // Mark all rows for this locale as pending
                foreach ($fields as $f) {
                    $this->aiRowStatus[$locale][$f] = 'pending';
                }

                // 'slug' is computed client-side; it is never sent to the AI.
                $apiFields = array_values(array_filter($fields, fn ($f) => $f !== 'slug'));

                $result = [];
                if (! empty($apiFields)) {
                    try {
                        $context = $this->aiBuildContext($locale);
                        $result = $generator->generateFields($apiFields, $locale, $context);
                    } catch (Throwable $e) {
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

                // Write returned fields into translations
                foreach ($apiFields as $f) {
                    if (! array_key_exists($f, $result) || trim((string) $result[$f]) === '') {
                        $this->aiRowStatus[$locale][$f] = 'error';
                        $this->aiRowErrors[$locale][$f] = __('No content returned for this field.');

                        continue;
                    }
                    $formField = $this->aiFieldToFormField($f);
                    $this->translations[$locale][$formField] = (string) $result[$f];
                    $this->aiRowStatus[$locale][$f] = 'success';
                }

                // Slug: compute client-side from the (possibly new) title
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

            // Reload the active locale's fields into the form
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

            if (! $hasError) {
                Flux::toast(__('Content generated successfully.'), variant: 'success');
            }
        } catch (Throwable $e) {
            Log::error('Product content generation failed', ['error' => $e->getMessage()]);
            $this->aiModalError = __('Generation failed — unexpected error. Please try again.');
        } finally {
            $this->isGeneratingContent = false;
        }
    }

    public function applyRevert(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            // key is like "description_en" — split off the trailing locale code
            $pos = strrpos($key, '_');
            if ($pos === false) {
                continue;
            }
            $field = substr($key, 0, $pos);
            $locale = substr($key, $pos + 1);
            if (! array_key_exists($locale, $this->translations)) {
                continue;
            }
            if (! in_array($field, $this->translatableFields(), true)) {
                continue;
            }
            $this->translations[$locale][$field] = (string) $value;
        }

        $this->loadFieldsFromTranslations($this->selectedLocale);
        $this->dispatch('wysiwyg-reload');
        $this->hasRevertSnapshot = false;

        Flux::toast(__('Reverted last AI generation.'), variant: 'success');
    }

    public function save()
    {
        $this->resetErrorBag();

        $this->validateAttributesVariations();

        // Store current locale's translatable fields before saving
        $this->storeFieldsToTranslations();

        // Pick a locale whose title will fill the product's main columns.
        // Prefer the configured main locale; otherwise fall back to any locale that has a title.
        $mainLocale = $this->mainLocale();
        $primaryLocale = $mainLocale;
        if (empty(trim((string) ($this->translations[$mainLocale]['title'] ?? '')))) {
            foreach (array_keys($this->locales()) as $loc) {
                if (! empty(trim((string) ($this->translations[$loc]['title'] ?? '')))) {
                    $primaryLocale = $loc;
                    break;
                }
            }
        }

        if (empty(trim((string) ($this->translations[$primaryLocale]['title'] ?? '')))) {
            $this->addError('title', __('Enter a product title in at least one language.'));
            $this->dispatch('scroll-to-error');

            return;
        }

        // Load the chosen locale's fields into top-level state for validation & saving
        $this->loadFieldsFromTranslations($primaryLocale);

        if ($this->product_type !== 'simple') {
            $this->warrantyAvailable = false;
            $this->warranty_group_id = null;
        }

        $this->slug ??= Str::slug($this->title);

        $validator = Validator::make($this->all(), $this->rules(), $this->messages(), $this->validationAttributes());

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

        $validated = $validator->validated();
        $validated['name'] = $validated['title'];

        $metaData = collect($validated)->only(config('products.meta_fields'))->toArray();

        $isUpdate = $this->editMode;

        [$oldModel, $oldWysiwygIds] = $this->captureOldModelState();

        $productToAttachMedia = $this->saveSimpleProduct($validated, $metaData, $isUpdate);

        // Sync translations for all non-main locales
        foreach ($this->otherLocales() as $locale) {
            // Skip the locale already saved into the product's main columns
            if ($locale === $primaryLocale) {
                continue;
            }

            $localeData = $this->translations[$locale] ?? [];

            // Check if any translatable field has content
            $hasAnyData = false;
            foreach ($this->translatableFields() as $field) {
                if (trim((string) ($localeData[$field] ?? '')) !== '') {
                    $hasAnyData = true;
                    break;
                }
            }

            if (! $hasAnyData) {
                continue;
            }

            $title = trim((string) ($localeData['title'] ?? ''));
            // Fallback to main locale's title if this locale's title is empty
            if ($title === '') {
                $title = trim((string) ($this->translations[$primaryLocale]['title'] ?? ''));
                $localeData['title'] = $title;
            }

            $localeData['name'] = $title;
            if (empty($localeData['slug'])) {
                $localeData['slug'] = Str::slug($title);
            }
            $this->syncTranslationForLocale($productToAttachMedia, $localeData, $locale);
        }

        $this->uploadMedia($productToAttachMedia);

        $managedTaxonomyIds = Taxonomy::query()
            ->whereIn('name', ['Category', 'Brands'])
            ->pluck('id');
        $retainedTaxonIds = $productToAttachMedia->taxons()
            ->whereNotIn('taxonomy_id', $managedTaxonomyIds)
            ->pluck('taxons.id');
        $productToAttachMedia->taxons()->sync(
            $retainedTaxonIds
                ->merge($this->selected_taxons)
                ->merge($this->selected_brand_taxons)
                ->unique()
        );


        $this->syncProductRelations($productToAttachMedia);

        $this->syncProductProperties($productToAttachMedia);

        $this->syncProductBrandProperties($productToAttachMedia);



        $productToAttachMedia->searchable();

        $this->cleanupRemovedWysiwygMedia($oldWysiwygIds);

        $this->dispatch('ai-clear-snapshot');
        $this->hasRevertSnapshot = false;

        Flux::toast($this->editMode ? __('Product updated successfully.') : __('Product created successfully.'), variant: 'success');

        return $this->redirect(route('products.index'), navigate: true);
    }

    protected function syncProductProperties(Product $product): void
    {
        if ($this->product_properties === []) {
            return;
        }

        $managedPropertyIds = collect(array_keys($this->product_properties))
            ->map(fn ($propertyId) => (int) $propertyId)
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

        foreach ($this->product_properties as $propertyId => $values) {
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

        $product->propertyValues()->detach(
            PropertyValue::query()
                ->whereIn('property_id', $managedPropertyIds)
                ->pluck('id')
                ->all()
        );

        if ($selectedPropertyValueIds !== []) {
            $product->propertyValues()->syncWithoutDetaching(array_values(array_unique($selectedPropertyValueIds)));
        }
    }

    protected function syncProductBrandProperties(Product $product): void
    {
        $brandProperty = Property::query()->firstOrCreate(
            ['slug' => 'brand'],
            ['name' => 'Brand', 'type' => 'text']
        );

        $brandPropertyIds = Property::query()
            ->whereIn('slug', $this->brandPropertySlugs())
            ->pluck('id')
            ->all();

        $product->propertyValues()->detach(
            PropertyValue::query()
                ->whereIn('property_id', $brandPropertyIds)
                ->pluck('id')
                ->all()
        );

        $brandTaxons = $this->selectedBrandTaxons();

        if ($brandTaxons->isEmpty()) {
            return;
        }

        $propertyValueIds = $brandTaxons
            ->map(fn (Taxon $taxon): int => PropertyValue::query()->firstOrCreate(
                [
                    'property_id' => $brandProperty->id,
                    'value' => (string) $taxon->slug,
                ],
                ['title' => (string) $taxon->name]
            )->id)
            ->values()
            ->all();

        $product->propertyValues()->syncWithoutDetaching($propertyValueIds);
    }

    protected function selectedBrandTaxons(): Collection
    {
        $ids = collect($this->selected_brand_taxons)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return Taxon::query()
            ->whereIn('id', $ids)
            ->whereHas('taxonomy', fn ($query) => $query->where('name', 'Brands'))
            ->orderBy('name')
            ->get();
    }

    protected function selectedBrandNamesForContext(): string
    {
        return $this->selectedBrandTaxons()
            ->pluck('name')
            ->filter()
            ->implode(', ');
    }

    protected function selectedBrandTaxonIdsForModel(Product $model): array
    {
        $assignedIds = $model->taxons
            ->filter(fn ($taxon) => $this->taxonBelongsTo($taxon, 'Brands'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $brandValues = $model->propertyValues
            ->filter(fn ($propertyValue) => $propertyValue->property && in_array((string) $propertyValue->property->slug, $this->brandPropertySlugs(), true))
            ->flatMap(fn ($propertyValue) => [(string) $propertyValue->value, (string) $propertyValue->title])
            ->merge(
                $model->metas
                    ->where('meta_key', 'brand')
                    ->pluck('meta_value')
                    ->map(fn ($value) => (string) $value)
            )
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->unique(fn (string $value) => Str::lower($value))
            ->values();

        if ($brandValues->isEmpty()) {
            return $assignedIds->unique()->values()->all();
        }

        $brandLookup = $brandValues
            ->flatMap(fn (string $value) => [
                Str::lower($value),
                Str::lower(Str::slug($value)),
            ])
            ->unique()
            ->all();

        $propertyMatchedIds = Taxon::query()
            ->whereHas('taxonomy', fn ($query) => $query->where('name', 'Brands'))
            ->get(['id', 'name', 'slug', 'taxonomy_id'])
            ->filter(fn (Taxon $taxon) => in_array(Str::lower((string) $taxon->slug), $brandLookup, true)
                || in_array(Str::lower((string) $taxon->name), $brandLookup, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $assignedIds
            ->merge($propertyMatchedIds)
            ->unique()
            ->values()
            ->all();
    }

    protected function taxonBelongsTo($taxon, string $taxonomyName): bool
    {
        $taxonomy = $taxon->relationLoaded('taxonomy') ? $taxon->taxonomy : $taxon->taxonomy()->first();

        return $taxonomy && (string) $taxonomy->name === $taxonomyName;
    }

    protected function brandPropertySlugs(): array
    {
        return ['brand', 'merk', 'product-brand', 'product_brand'];
    }

    protected function normalizeSelectedPropertyValues(mixed $values): array
    {
        return collect(is_array($values) ? $values : [$values])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }



    protected function localizedTaxonTree($taxon)
    {
        $localizedTaxon = $this->getTranslatedModel($taxon);

        if ($taxon->relationLoaded('children')) {
            $localizedTaxon->setRelation(
                'children',
                $taxon->children
                    ->map(fn ($child) => $this->localizedTaxonTree($child))
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values(),
            );
        }

        return $localizedTaxon;
    }

    #[Computed]
    public function existingMainMedia()
    {
        if (! $this->editMode || ! $this->productId) {
            return collect();
        }

        $model = $this->resolveEditingModel();

        return $model->getMedia('main');
    }

    #[Computed]
    public function existingGalleryMedia()
    {
        if (! $this->editMode || ! $this->productId) {
            return collect();
        }

        $model = $this->resolveEditingModel();

        return $model->getMedia('gallery');
    }

    public function updatedProductTemplate(): void
    {
        // wire:model.live batches all pending model values from the browser, which can
        // include stale main-locale content from wysiwyg editors (wire:ignore skips
        // morphdom so their entangled value may lag). Re-load from the translations
        // array to restore the correct locale's content.
        if ($this->selectedLocale !== $this->mainLocale()) {
            $this->loadFieldsFromTranslations($this->selectedLocale);
        }
    }

    protected function loadRelatedIds($model, string $type): array
    {
        if (! $model instanceof Product) {
            return [];
        }

        return $model->productRelations()->where('relation_type', $type)->orderBy('position')->pluck('related_product_id')->map(fn ($id) => (int) $id)->all();
    }

    protected function syncProductRelations($model): void
    {
        if (! $model instanceof Product) {
            return;
        }

        $allowed = $this->relatableProducts->pluck('id')->all();
        $upsells = array_values(array_intersect(array_map('intval', $this->up_sell_ids), $allowed));
        $crosssells = array_values(array_intersect(array_map('intval', $this->cross_sell_ids), $allowed));
        $model->syncProductRelations(ProductRelation::TYPE_UPSELL, $upsells);
        $model->syncProductRelations(ProductRelation::TYPE_CROSSSELL, $crosssells);
    }

    #[Computed]
    public function relatableProducts()
    {
        return Product::query()
            ->when($this->editMode && $this->originalProductType === 'simple' && $this->productId, fn ($q) => $q->where('id', '!=', $this->productId))
            ->orderBy('title')
            ->limit(1000)
            ->get(['id', 'title', 'name'])
            ->map(function ($p) {
                // Use the raw column values; the translated accessor is blank
                // when a product has no translation for the current locale.
                $title = trim((string) ($p->getRawOriginal('title') ?: $p->getRawOriginal('name')));

                return [
                    'id' => (int) $p->id,
                    'label' => $title !== '' ? $title : __('Product').' #'.$p->id,
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    #[On('remove-upload')]
    public function handleRemoveUpload(array $params): void
    {
        $mediaId = $params['id'] ?? null;

        if (! $mediaId || ! $this->editMode || ! $this->productId) {
            return;
        }

        $model = $this->resolveEditingModel();
        $model->media()->where('id', $mediaId)->first()?->delete();

        unset($this->existingMainMedia, $this->existingGalleryMedia);
    }

    protected function captureOldModelState(): array
    {
        if (! $this->editMode) {
            return [null, []];
        }

        $oldModel = $this->resolveEditingModel();

        $oldWysiwygIds = $this->extractWysiwygMediaIds($oldModel->description, $oldModel->content);

        return [$oldModel, $oldWysiwygIds];
    }

    protected function translatableFields(): array
    {
        return ['name', 'title', 'subtitle', 'slug', 'excerpt', 'description', 'content'];
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
        return app()->getLocale() === $this->mainLocale();
    }

    protected function storeFieldsToTranslations(?string $locale = null): void
    {
        $locale ??= $this->selectedLocale;
        foreach ($this->translatableFields() as $field) {
            $this->translations[$locale][$field] = $this->{$field};
        }

        $this->translations['nl']['meta_title'] = $this->meta_title_nl;
        $this->translations['en']['meta_title'] = $this->meta_title_en;
        $this->translations['nl']['meta_description'] = $this->meta_description_nl;
        $this->translations['en']['meta_description'] = $this->meta_description_en;
    }

    protected function loadFieldsFromTranslations(string $locale): void
    {
        Log::info("Loading fields from translations for locale: {$locale}");
        foreach ($this->translatableFields() as $field) {
            $value = $this->translations[$locale][$field] ?? null;
            if ($value !== null) {
                $this->{$field} = $value;
            } else {
                $this->{$field} = '';
            }
        }

        if (isset($this->translations['nl']['meta_title'])) {
            $this->meta_title_nl = $this->translations['nl']['meta_title'];
        }
        if (isset($this->translations['en']['meta_title'])) {
            $this->meta_title_en = $this->translations['en']['meta_title'];
        }
        if (isset($this->translations['nl']['meta_description'])) {
            $this->meta_description_nl = $this->translations['nl']['meta_description'];
        }
        if (isset($this->translations['en']['meta_description'])) {
            $this->meta_description_en = $this->translations['en']['meta_description'];
        }
    }

    protected function syncTranslationForLocale($model, array $translatableData, string $locale): void
    {
        $translation = Translation::findByModel($model, $locale);

        $data = [
            'name' => (string) ($translatableData['name'] ?? ($translatableData['title'] ?? '')),
            'slug' => (string) ($translatableData['slug'] ?? ''),
            'fields' => $translatableData,
        ];

        if ($translation) {
            $translation->update($data);
        } else {
            Translation::create(
                array_merge(
                    [
                        'translatable_type' => morph_type_of($model),
                        'translatable_id' => $model->getKey(),
                        'language' => $locale,
                    ],
                    $data,
                ),
            );
        }
    }

    protected function saveSimpleProduct(array $validated, array $metaData, bool $isUpdate): Product
    {
        if ($isUpdate) {
            $product = Product::findOrFail($this->productId);
            $product->update($validated);
        } else {
            $product = Product::create($validated);
        }

        $this->syncMeta($product, $metaData);

        return $product;
    }

    protected function migrateMedia($from, $to): void
    {
        if (! $this->main_image) {
            foreach ($from->getMedia('main') as $media) {
                $media->copy($to, 'main');
            }
        }

        if (empty($this->gallery_images)) {
            foreach ($from->getMedia('gallery') as $media) {
                $media->copy($to, 'gallery');
            }
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

        if (! empty($this->gallery_images)) {
            foreach ($this->gallery_images as $image) {
                $model->addMedia($image->getRealPath())->usingName($image->getClientOriginalName())->usingFileName($image->getClientOriginalName())->toMediaCollection('gallery');
            }
        }
    }

    protected function deleteOldProduct($oldModel): void
    {
        $oldModel->metas()->delete();

        $oldModel->taxons()->detach();
        $oldModel->clearMediaCollection('main');
        $oldModel->clearMediaCollection('gallery');

        $oldModel->description = '';
        $oldModel->content = '';
        $oldModel->delete();
    }

    protected function wysiwygFieldValues(): array
    {
        return [$this->description, $this->content];
    }

    protected function resolveEditingModel(): Product
    {
        return Product::findOrFail($this->productId);
    }

    protected function syncMeta($model, array $metaData): void
    {
        foreach ($metaData as $key => $value) {
            if (blank($value)) {
                $model->metas()->where('meta_key', $key)->delete();

                continue;
            }

            $model->metas()->updateOrCreate(['meta_key' => $key], ['meta_value' => $value]);
        }
    }

    #[Computed(cache: false)]
    public function discount_groups()
    {
        return DiscountGroup::query()->when($this->discount_search, fn ($query) => $query->where('name', 'like', '%'.$this->discount_search.'%'))->limit(20)->get();
    }

    public function updatedDiscountGroupId($value)
    {
        Log::info('Discount Group Updated');
        if ($value) {
            $discountGroup = DiscountGroup::find($value);
            $this->discounts = json_decode($discountGroup->discounts, true);
        }
    }


};
?>

<div x-data="{
    uploads: 0,
    snapshotKey() {
        const id = @js($productId ?? 'new');
        return 'ai_revert_' + id;
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
                <flux:heading size="xl">{{ $editMode ? __('Edit Product') : __('Create Product') }}</flux:heading>
                <flux:subheading>
                    {{ $editMode ? __('Update your product details.') : __('Add a new product to your catalog.') }}
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('products.index') }}"
                    x-bind:class="{ 'pointer-events-none opacity-50': uploads > 0 }"
                    wire:loading.class="pointer-events-none opacity-50" wire:target="save">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" x-bind:disabled="uploads > 0" wire:loading.attr="disabled"
                    wire:target="save">
                    {{ $editMode ? __('Update Product') : __('Save Product') }}
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
                            <flux:subheading>{{ __('Product title, description and general details.') }}
                            </flux:subheading>
                        </div>
                        @if (count(config('app.available_locales')) > 1)
                            <div class="flex items-center gap-1">
                                @foreach (config('app.available_locales') as $localeCode => $localeName)
                                    <button type="button" wire:click="switchLocale('{{ $localeCode }}')"
                                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-sm font-medium transition-colors {{ $selectedLocale === $localeCode ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
                                        <span class="text-base leading-none">{{ $localeCode === 'en' ? '🇬🇧' : '🇳🇱' }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <flux:input wire:model="title" label="{{ __('Title') }}"
                                placeholder="{{ __('e.g. Maxi Baxi 2000') }}" />
                        </div>
                        <div class="flex-1">
                            <x-slug-input model="slug" />
                        </div>
                    </div>

                    @if ($product_type === 'variable')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input wire:model="article_number" label="{{ __('Article Number') }}"
                                placeholder="ART-001" />
                            <flux:select wire:model="tax_category_id" label="{{ __('Tax Category') }}">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($this->taxCategories as $taxCat)
                                    <option value="{{ $taxCat->id }}">{{ __($taxCat->name) }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif

                    <flux:input wire:model="subtitle" label="{{ __('Subtitle') }}"
                        placeholder="{{ __('Product subtitle...') }}" />

                    <flux:textarea wire:model="excerpt" label="{{ __('Excerpt') }}"
                        placeholder="{{ __('Short summary of the product...') }}" rows="2" />

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
                    <flux:error name="content_generation" />

                    <x-wysiwyg-editor wire:model="description" :defer-delete="$editMode"
                        label="{{ __('Short Description') }}" placeholder="{{ __('Brief product overview...') }}" />

                    <x-wysiwyg-editor wire:model="content" :defer-delete="$editMode" label="{{ __('Content') }}"
                        placeholder="{{ __('Detailed product content...') }}" />
                </flux:card>

                <!-- Stock & Delivery -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Stock & Delivery') }}</flux:heading>
                        <flux:subheading>{{ __('Packaging, stock levels and delivery timelines.') }}
                        </flux:subheading>
                    </div>

                    <div class="flex flex-col md:flex-row gap-6">
                        @if ($product_type === 'simple')
                            <div class="flex-1">
                                <flux:input wire:model="stock" label="{{ __('Stock') }}" type="number" min="0" step="1"
                                    placeholder="{{ __('0') }}" />
                            </div>
                        @endif
                        <div class="flex-1">
                            <flux:input wire:model="delivery_dates_no_stock"
                                label="{{ __('Delivery Days (No Stock)') }}" type="number" min="0" step="1"
                                placeholder="{{ __('0') }}" />
                        </div>
                        <div class="flex-1">
                            <flux:input wire:model="delivery_dates_in_stock"
                                label="{{ __('Delivery Days (In Stock)') }}" type="number" min="0" step="1"
                                placeholder="{{ __('0') }}" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <flux:input wire:model="packing_group" label="{{ __('Packing Group') }}" type="number" min="0"
                            step="1" placeholder="{{ __('0') }}" />

                        <div class="h-2"></div>
                        <flux:field variant="inline">
                            <flux:checkbox wire:model="allow_singulars" />
                            <flux:label>{{__('Allow singular quantities until the Packing Group')}}</flux:label>
                            <flux:error name="allow_singulars" />
                        </flux:field>

                        <flux:input wire:model="packaging_unit" label="{{ __('Packaging Unit') }}" type="number" min="0"
                            step="1" placeholder="{{ __('0') }}" />
                    </div>
                </flux:card>

                <!-- Pricing & Inventory -->
                @if ($product_type === 'simple')
                    <flux:card class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Pricing & Inventory') }}</flux:heading>
                            <flux:subheading>{{ __('Manage pricing, stock limits, and SKU.') }}</flux:subheading>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:input wire:model="sku" label="{{ __('SKU') }}" placeholder="{{ __('e.g. MXB-2000') }}" />
                            <flux:input wire:model="article_number" label="{{ __('Article Number') }}"
                                placeholder="{{ __('e.g. ART-001') }}" />
                        </div>

                        <div x-data="{
                                                                                                                sanitize(event) {
                                                                                                                    let value = event.target.value;

                                                                                                                    // keep only numbers and dots
                                                                                                                    value = value.replace(/[^0-9.]/g, '');

                                                                                                                    // allow only one dot
                                                                                                                    const parts = value.split('.');
                                                                                                                    if (parts.length > 2) {
                                                                                                                        value = parts[0] + '.' + parts.slice(1).join('');
                                                                                                                    }

                                                                                                                    // limit decimal places to 2
                                                                                                                    const decimalParts = value.split('.');
                                                                                                                    if (decimalParts.length === 2) {
                                                                                                                        decimalParts[1] = decimalParts[1].slice(0, 2);
                                                                                                                        value = decimalParts.join('.');
                                                                                                                    }

                                                                                                                    event.target.value = value;
                                                                                                                }
                                                                                                            }"
                            class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                            <flux:field class="flex flex-col gap-1">
                                <flux:label>{{ __('Price') }}</flux:label>

                                <div @class([
                                    'flex w-full rounded-lg border shadow-xs overflow-hidden',
                                    'border-zinc-200 dark:border-white/10' => !$errors->has('price'),
                                    'border-red-500 dark:border-red-500' => $errors->has('price'),
                                ])>
                                    <span
                                        class="flex items-center px-4 text-sm whitespace-nowrap text-zinc-800 dark:text-zinc-200 bg-zinc-800/5 dark:bg-white/20 border-r border-zinc-200 dark:border-white/10">
                                        {{ config('app.currency_symbol') }}
                                    </span>

                                    <input wire:model="price" type="text" inputmode="decimal"
                                        placeholder="{{ __('1999.95') }}" x-on:input="sanitize($event)"
                                        x-on:paste="setTimeout(() => sanitize($event), 0)"
                                        class="flex-1 min-w-0 px-3 py-2 text-sm bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 focus:outline-none" />
                                </div>

                                <flux:error name="price" />
                            </flux:field>

                            <flux:field class="flex flex-col gap-1">
                                <flux:label>{{ __('Original Price') }}</flux:label>

                                <div @class([
                                    'flex w-full rounded-lg border shadow-xs overflow-hidden',
                                    'border-zinc-200 dark:border-white/10' => !$errors->has('original_price'),
                                    'border-red-500 dark:border-red-500' => $errors->has('original_price'),
                                ])>
                                    <span
                                        class="flex items-center px-4 text-sm whitespace-nowrap text-zinc-800 dark:text-zinc-200 bg-zinc-800/5 dark:bg-white/20 border-r border-zinc-200 dark:border-white/10">
                                        {{ config('app.currency_symbol') }}
                                    </span>

                                    <input wire:model="original_price" type="text" inputmode="decimal"
                                        placeholder="{{ __('2499.95') }}" x-on:input="sanitize($event)"
                                        x-on:paste="setTimeout(() => sanitize($event), 0)"
                                        class="flex-1 min-w-0 px-3 py-2 text-sm bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 focus:outline-none" />
                                </div>

                                <flux:error name="original_price" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:select wire:model="tax_category_id" label="{{ __('Tax Category') }}">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($this->taxCategories as $taxCat)
                                    <option value="{{ $taxCat->id }}">{{ __($taxCat->name) }}</option>
                                @endforeach
                            </flux:select>


                            <flux:select wire:model="discount_group_id" label="{{ __('Discount Group') }}" clearable>
                                <flux:select.option value="0">{{ __('None') }}</flux:select.option>
                                @foreach ($this->discount_groups as $discount_group)
                                    <flux:select.option value="{{ $discount_group->id }}" wire:key="{{ $discount_group->id }}">
                                        {{ $discount_group->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            @if ($this->discount_group_id)
                                <flux:card class="space-y-2 mt-6 max-w-lg">
                                    <flux:table>
                                        <flux:table.columns>
                                            <flux:table.column>{{ __('Quantity') }}</flux:table.column>
                                            <flux:table.column>{{ __('Discount') }}</flux:table.column>
                                        </flux:table.columns>

                                        <flux:table.rows>
                                            @foreach ($this->discounts as $discount)
                                                <flux:table.row>
                                                    <flux:table.cell>
                                                        {{ $discount['quantity'] }}
                                                    </flux:table.cell>
                                                    <flux:table.cell>
                                                        {{ $discount['discount'] }}
                                                    </flux:table.cell>
                                                </flux:table.row>
                                            @endforeach
                                        </flux:table.rows>
                                    </flux:table>
                                </flux:card>
                            @endif
                        </div>
                    </flux:card>
                @endif

                @if ($product_type === 'variable')
                    <!-- Attributes & Variations -->
                    <flux:card class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:heading size="lg">{{ __('Attributes & Variations') }}</flux:heading>
                                <flux:subheading>
                                    {{ __('Define attributes like Size or Color, then generate variations.') }}
                                </flux:subheading>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <flux:heading size="md">{{ __('Attributes') }}</flux:heading>
                            <flux:error name="attributes_required" />

                            @foreach ($product_attributes as $index => $attr)
                                <div
                                    class="flex items-start space-x-4 border border-zinc-200 dark:border-zinc-700 p-4 rounded-xl">
                                    <div class="flex-1 space-y-4">
                                        <flux:input wire:model="product_attributes.{{ $index }}.name" label="{{ __('Name') }}"
                                            placeholder="{{ __('e.g. Size') }}" />
                                        <flux:input wire:model="product_attributes.{{ $index }}.values"
                                            label="{{ __('Values') }}"
                                            placeholder="{{ __('e.g. Small, Medium, Large (comma separated)') }}" />
                                    </div>

                                    <div class="pt-8">
                                        <div x-data="{ hover: false }" @mouseenter="hover = true" @mouseleave="hover = false">
                                            @if (!$loop->first)
                                                <template x-if="!hover">
                                                    <flux:icon.trash class="size-6 text-red-500 cursor-pointer"
                                                        wire:click="removeAttributeVariations({{ $index }})" />
                                                </template>
                                                <template x-if="hover">
                                                    <flux:icon.trash class="size-6 text-red-700 cursor-pointer" variant="solid"
                                                        wire:click="removeAttributeVariations({{ $index }})" />
                                                </template>
                                            @else
                                                <template x-if="!hover">
                                                    <flux:icon.arrow-uturn-left class="size-6 text-blue-500 cursor-pointer"
                                                        wire:click="resetAttributeVariations({{ $index }})" />
                                                </template>
                                                <template x-if="hover">
                                                    <flux:icon.arrow-uturn-left class="size-6 text-blue-700 cursor-pointer"
                                                        variant="solid" wire:click="resetAttributeVariations({{ $index }})" />
                                                </template>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex space-x-2">
                                <flux:button variant="subtle" size="sm" icon="plus" wire:click="addAttribute">
                                    {{ __('Add Attribute') }}
                                </flux:button>
                                @if (count($product_attributes) > 0)
                                    <flux:button variant="subtle" size="sm" icon="sparkles" wire:click="generateVariations">
                                        {{ __('Generate Variations') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        @if (count($variations) > 0)
                            <div class="space-y-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                <flux:heading size="md">{{ __('Variations') }}</flux:heading>
                                <flux:error name="variations_required" />

                                <div class="space-y-4">
                                    @foreach ($variations as $index => $variation)
                                        <div class="border border-zinc-200 dark:border-zinc-700 p-4 rounded-xl space-y-4"
                                            wire:key="variant-{{ $index }}">
                                            <div
                                                class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                                                <div class="flex space-x-2">
                                                    @foreach ($variation['properties'] as $propName => $propValue)
                                                        <flux:badge size="sm" color="zinc">
                                                            {{ $propName }}:
                                                            {{ $propValue }}
                                                        </flux:badge>
                                                    @endforeach
                                                </div>
                                                <flux:button variant="ghost" size="sm" icon="x-mark"
                                                    wire:click="removeVariation({{ $index }})"
                                                    class="text-zinc-400 hover:text-red-500!" />
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                                                <flux:input wire:model="variations.{{ $index }}.sku" label="{{ __('SKU') }}"
                                                    placeholder="{{ __('Variant SKU') }}" />

                                                <div>
                                                    <flux:field>
                                                        <flux:label>{{ __('Price') }}</flux:label>
                                                        <flux:input.group>
                                                            <flux:input.group.prefix>
                                                                {{ config('app.currency_symbol') }}
                                                            </flux:input.group.prefix>
                                                            <flux:input wire:model="variations.{{ $index }}.price" type="number"
                                                                min="0" placeholder="{{ __('Empty = Master Price') }}" />
                                                        </flux:input.group>
                                                    </flux:field>

                                                    <flux:error name="variations.{{ $index }}.price" />
                                                </div>

                                                <div>
                                                    <flux:input wire:model="variations.{{ $index }}.stock" label="{{ __('Stock') }}"
                                                        type="number" min="0" step="1" />
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="h-6"></div>

                        <flux:select wire:model="discount_group_id" label="{{ __('Discount Group') }}">
                            @foreach ($this->discount_groups as $discount_group)
                                <flux:select.option value="{{ $discount_group->id }}" wire:key="{{ $discount_group->id }}">
                                    {{ __($discount_group->name) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                    </flux:card>
                @endif





                <!-- Linked Products -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Linked Products') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Recommend up-sells (premium alternatives) and cross-sells (complementary items) to drive revenue.') }}
                        </flux:subheading>
                    </div>

                    <div class="space-y-2">
                        <flux:select wire:model="up_sell_ids" label="{{ __('Up-sells') }}"
                            placeholder="{{ __('Search and select up-sell products...') }}" variant="listbox" multiple
                            searchable clearable indicator="checkbox" selected-suffix="{{ __('selected') }}">
                            @foreach ($this->relatableProducts as $option)
                                <flux:select.option value="{{ $option['id'] }}" wire:key="us-{{ $option['id'] }}">
                                    {{ $option['label'] }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Higher-value products shown to customers as upgrades on the product page.') }}
                        </flux:text>
                        <flux:error name="up_sell_ids" />
                        <flux:error name="up_sell_ids.*" />
                    </div>

                    <div class="space-y-2">
                        <flux:select wire:model="cross_sell_ids" label="{{ __('Cross-sells') }}"
                            placeholder="{{ __('Search and select cross-sell products...') }}" variant="listbox"
                            multiple searchable clearable indicator="checkbox" selected-suffix="{{ __('selected') }}">
                            @foreach ($this->relatableProducts as $option)
                                <flux:select.option value="{{ $option['id'] }}" wire:key="cs-{{ $option['id'] }}">
                                    {{ $option['label'] }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('Complementary products promoted in the cart and checkout.') }}
                        </flux:text>
                        <flux:error name="cross_sell_ids" />
                        <flux:error name="cross_sell_ids.*" />
                    </div>

                </flux:card>



                <!-- Shipping / Dimensions -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Shipping & Dimensions') }}</flux:heading>
                        <flux:subheading>{{ __('Physical attributes for calculating shipping.') }}
                        </flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                        <flux:input wire:model="weight" label="{{ __('Weight (kg)') }}" type="number" min="0"
                            step="0.01" placeholder="{{ __('1.3') }}" />
                        <flux:input wire:model="length" label="{{ __('Length (cm)') }}" type="number" min="0"
                            step="0.01" placeholder="{{ __('60') }}" />
                        <flux:input wire:model="width" label="{{ __('Width (cm)') }}" type="number" min="0" step="0.01"
                            placeholder="{{ __('27') }}" />
                        <flux:input wire:model="height" label="{{ __('Height (cm)') }}" type="number" min="0"
                            step="0.01" placeholder="{{ __('21') }}" />
                    </div>
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">
                <!-- Status -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Visibility & Status') }}</flux:heading>
                    </div>

                    <!-- <div>
                        <flux:radio.group wire:model.live="product_type" label="{{ __('Product Type') }}"
                            variant="segmented">
                            <flux:radio value="simple" label="{{ __('Simple') }}" />
                            <flux:radio value="variable" label="{{ __('Variable') }}" />
                        </flux:radio.group>
                    </div> -->

                    <flux:select wire:model="state" label="{{ __('State') }}">
                        <option value="active">{{ __('Active') }}</option>
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="unavailable">{{ __('Archived') }}</option>
                    </flux:select>
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

                <!-- Categorization -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Categorization') }}</flux:heading>
                        <flux:subheading>{{ __('Organize product by categories.') }}</flux:subheading>
                    </div>

                    <livewire:category-tree taxonomy-name="Category" wire:model="selected_taxons" />
                </flux:card>

                <!-- Brand Assignment -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Brands') }}</flux:heading>
                        <flux:subheading>{{ __('Assign product brands from the Brands taxonomy.') }}</flux:subheading>
                    </div>

                    <livewire:category-tree taxonomy-name="Brands" item-name="Brand" item-name-plural="brands"
                        wire:model="selected_brand_taxons" />

                    <flux:error name="selected_brand_taxons" />
                    <flux:error name="selected_brand_taxons.*" />
                </flux:card>

                <!-- SEO Metadata -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('SEO Metadata') }}</flux:heading>
                        <flux:subheading>{{ __('Optimize search engine visibility for both languages.') }}</flux:subheading>
                    </div>

                    <div class="grid grid-cols-1 gap-6">
                        <!-- Dutch SEO -->
                        <div class="space-y-4 p-4 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/30 border border-zinc-200/50 dark:border-zinc-700/50">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-lg">🇳🇱</span>
                                <flux:heading size="sm" class="font-semibold">{{ __('Dutch (NL)') }}</flux:heading>
                            </div>

                            <flux:input wire:model="meta_title_nl" label="{{ __('Meta Title') }}"
                                placeholder="{{ __('Custom title for Dutch search engines') }}" />

                            <flux:textarea wire:model="meta_description_nl" label="{{ __('Meta Description') }}"
                                placeholder="{{ __('Brief description for Dutch search engine results') }}" rows="3" />
                        </div>

                        <!-- English SEO -->
                        <div class="space-y-4 p-4 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/30 border border-zinc-200/50 dark:border-zinc-700/50">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-lg">🇬🇧</span>
                                <flux:heading size="sm" class="font-semibold">{{ __('English (EN)') }}</flux:heading>
                            </div>

                            <flux:input wire:model="meta_title_en" label="{{ __('Meta Title') }}"
                                placeholder="{{ __('Custom title for English search engines') }}" />

                            <flux:textarea wire:model="meta_description_en" label="{{ __('Meta Description') }}"
                                placeholder="{{ __('Brief description for English search engine results') }}" rows="3" />
                        </div>
                    </div>
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

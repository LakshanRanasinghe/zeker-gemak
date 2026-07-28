<?php

use App\Jobs\ExportProductsJob;
use App\Models\AiSetting;
use App\Models\Export;
use App\Models\Product;
use App\Services\ProductContentGenerator;
use App\Services\SkuGenerator;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Vanilo\Translation\Models\Translation;

new class extends Component {
    public ?Export $activeExport = null;

    public bool $showExportModal = false;

    public ?array $pendingExportIds = null;

    public bool $pendingExportAll = false;

    public string $exportLocale = 'en';

    public string $exportHeadingLocale = 'en';

    public bool $showBulkGenerateModal = false;

    /**
     * When true: a single set of EN/NL field checkboxes applies to every row.
     * When false: each row keeps its own selection.
     */
    public bool $useGlobalSelection = true;

    /**
     * Global field selections used when $useGlobalSelection = true.
     * Shape: ['en' => [...field keys], 'nl' => [...field keys]]
     */
    public array $globalSelections = ['en' => [], 'nl' => []];

    /**
     * Per-row state. Each row carries title/slug per locale, a shared SKU,
     * per-locale selections, generated content, generation status & errors.
     */
    public array $bulkItems = [];

    public function mount(): void
    {
        $this->loadActiveExport();
    }

    #[On('showExportModal')]
    public function openExportModal(?array $ids = null, bool $all = false): void
    {
        $this->pendingExportIds = $ids;
        $this->pendingExportAll = $all;
        $this->exportLocale = 'en';
        $this->exportHeadingLocale = 'en';
        $this->showExportModal = true;
    }

    public function confirmExport(): void
    {
        $this->showExportModal = false;
        $headingLocale = $this->exportLocale === 'both' ? $this->exportHeadingLocale : $this->exportLocale;
        $this->startExport($this->pendingExportIds, $this->pendingExportAll, $this->exportLocale, $headingLocale);
    }

    public function startExport(?array $ids = null, bool $all = false, string $locale = 'en', string $headingLocale = 'en'): void
    {
        if ($this->activeExport && $this->activeExport->isInProgress()) {
            Flux::toast(__('An export is already in progress.'), variant: 'warning');

            return;
        }

        if (!$all && $ids !== null) {
            // Validate that the composite row IDs resolve to actual products
            $hasProducts = false;
            foreach ($ids as $rowId) {
                [$type, $id] = explode('_', $rowId, 2);
                if ($type === 'simple' && Product::whereNull('deleted_at')->where('id', (int) $id)->exists()) {
                    $hasProducts = true;
                    break;
                }
            }

            if (!$hasProducts) {
                Flux::toast(__('No products to export.'), variant: 'warning');

                return;
            }
        } elseif ($all) {
            $count = Product::whereNull('deleted_at')->count();
            if ($count === 0) {
                Flux::toast(__('No products to export.'), variant: 'warning');

                return;
            }
        }

        $filters = $all ? ['locale' => $locale, 'heading_locale' => $headingLocale] : ['ids' => $ids, 'locale' => $locale, 'heading_locale' => $headingLocale];

        $export = Export::create([
            'user_id' => auth()->id(),
            'type' => 'products',
            'status' => 'pending',
            'filters' => $filters,
        ]);

        ExportProductsJob::dispatch($export);

        $this->activeExport = $export;

        Flux::toast(__('Export started. You can navigate away — it will continue in the background.'), variant: 'success');
    }

    public function retryExport(): void
    {
        if (!$this->activeExport) {
            return;
        }

        $filters = $this->activeExport->filters;
        $ids = $filters['ids'] ?? null;
        $all = !isset($filters['ids']);
        $locale = $filters['locale'] ?? 'en';
        $headingLocale = $filters['heading_locale'] ?? 'en';

        $this->activeExport->delete();
        $this->activeExport = null;

        $this->startExport($ids, $all, $locale, $headingLocale);
    }

    public function pollExport(): void
    {
        $this->loadActiveExport();
    }

    public function dismissExport(): void
    {
        if ($this->activeExport && ($this->activeExport->isCompleted() || $this->activeExport->isFailed())) {
            $this->activeExport->delete();
            $this->activeExport = null;
        }
    }

    protected function loadActiveExport(): void
    {
        $this->activeExport = Export::where('user_id', auth()->id())
            ->where('type', 'products')
            ->whereIn('status', ['pending', 'processing', 'completed', 'failed'])
            ->latest()
            ->first();

        // Auto-clear old completed/failed exports (older than 1 hour)
        if ($this->activeExport && !$this->activeExport->isInProgress() && $this->activeExport->updated_at->lt(now()->subHour())) {
            $this->activeExport->delete();
            $this->activeExport = null;
        }
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
            'product_information' => __('Product Information'),
            'meta_title' => __('Meta Title'),
            'meta_description' => __('Meta Description'),
        ];
    }

    public function bulkLocales(): array
    {
        return array_keys(config('app.available_locales'));
    }

    protected function bulkMainLocale(): string
    {
        return config('app.main_locale');
    }

    protected function makeEmptyBulkItem(): array
    {
        $emptyFields = array_fill_keys($this->aiGeneratableFields(), '');

        return [
            '_uid' => (string) Str::uuid(),
            'title_en' => '',
            'title_nl' => '',
            'slug_en' => '',
            'slug_nl' => '',
            'sku' => '',
            'selections_en' => array_values($this->globalSelections['en'] ?? []),
            'selections_nl' => array_values($this->globalSelections['nl'] ?? []),
            'fields_en' => $emptyFields,
            'fields_nl' => $emptyFields,
            'status_en' => [],
            'status_nl' => [],
            'errors_en' => [],
            'errors_nl' => [],
            'generated' => false,
            'expanded' => true,
            'created' => false,
            'rowError' => '',
        ];
    }

    public function openBulkGenerateModal(): void
    {
        $defaults = AiSetting::defaults();
        $this->globalSelections = [
            'en' => array_values(array_intersect((array) ($defaults['default_fields_en'] ?? []), $this->aiGeneratableFields())),
            'nl' => array_values(array_intersect((array) ($defaults['default_fields_nl'] ?? []), $this->aiGeneratableFields())),
        ];
        $this->useGlobalSelection = true;
        $this->bulkItems = [$this->makeEmptyBulkItem()];
        $this->showBulkGenerateModal = true;
    }

    public function addBulkItem(): void
    {
        $this->bulkItems[] = $this->makeEmptyBulkItem();
    }

    public function removeBulkItem(int $index): void
    {
        if (!isset($this->bulkItems[$index])) {
            return;
        }
        unset($this->bulkItems[$index]);
        $this->bulkItems = array_values($this->bulkItems);
        if (empty($this->bulkItems)) {
            $this->bulkItems = [$this->makeEmptyBulkItem()];
        }
    }

    public function resetBulkItem(int $index): void
    {
        if (!isset($this->bulkItems[$index])) {
            return;
        }

        $emptyFields = array_fill_keys($this->aiGeneratableFields(), '');

        // Reset everything AI-generated, but keep title / slug / sku / selections.
        $this->bulkItems[$index]['fields_en'] = $emptyFields;
        $this->bulkItems[$index]['fields_nl'] = $emptyFields;
        $this->bulkItems[$index]['status_en'] = [];
        $this->bulkItems[$index]['status_nl'] = [];
        $this->bulkItems[$index]['errors_en'] = [];
        $this->bulkItems[$index]['errors_nl'] = [];
        $this->bulkItems[$index]['generated'] = false;
        $this->bulkItems[$index]['rowError'] = '';
    }

    public function toggleRowExpanded(int $index): void
    {
        if (!isset($this->bulkItems[$index])) {
            return;
        }
        $this->bulkItems[$index]['expanded'] = !($this->bulkItems[$index]['expanded'] ?? true);
    }

    public function bulkGlobalSelectAll(string $locale): void
    {
        if (!in_array($locale, $this->bulkLocales(), true)) {
            return;
        }
        $this->globalSelections[$locale] = $this->aiGeneratableFields();
    }

    public function bulkGlobalDeselectAll(string $locale): void
    {
        if (!in_array($locale, $this->bulkLocales(), true)) {
            return;
        }
        $this->globalSelections[$locale] = [];
    }

    public function bulkRowSelectAll(int $index, string $locale): void
    {
        if (!isset($this->bulkItems[$index]) || !in_array($locale, $this->bulkLocales(), true)) {
            return;
        }
        $this->bulkItems[$index]['selections_' . $locale] = $this->aiGeneratableFields();
    }

    public function bulkRowDeselectAll(int $index, string $locale): void
    {
        if (!isset($this->bulkItems[$index]) || !in_array($locale, $this->bulkLocales(), true)) {
            return;
        }
        $this->bulkItems[$index]['selections_' . $locale] = [];
    }

    protected function effectiveSelections(int $index): array
    {
        if ($this->useGlobalSelection) {
            return [
                'en' => array_values($this->globalSelections['en'] ?? []),
                'nl' => array_values($this->globalSelections['nl'] ?? []),
            ];
        }

        return [
            'en' => array_values($this->bulkItems[$index]['selections_en'] ?? []),
            'nl' => array_values($this->bulkItems[$index]['selections_nl'] ?? []),
        ];
    }

    public function generateBulkItem(int $index): void
    {
        if (!isset($this->bulkItems[$index])) {
            return;
        }

        $row = $this->bulkItems[$index];
        $row['rowError'] = '';
        $row['status_en'] = [];
        $row['status_nl'] = [];
        $row['errors_en'] = [];
        $row['errors_nl'] = [];

        $selections = $this->effectiveSelections($index);

        if (collect($selections)->flatten()->isEmpty()) {
            $row['rowError'] = __('Select at least one field to generate.');
            $this->bulkItems[$index] = $row;

            return;
        }

        // Every selected locale must have its own title filled.
        $titleErrors = [];
        foreach ($selections as $loc => $fields) {
            if (!empty($fields) && trim((string) ($row['title_' . $loc] ?? '')) === '') {
                $titleErrors[] = __('The :lang title is required to generate :lang content.', ['lang' => strtoupper($loc)]);
            }
        }
        if (!empty($titleErrors)) {
            $row['rowError'] = implode(' ', $titleErrors);
            $this->bulkItems[$index] = $row;

            return;
        }

        // Auto-fill SKU from the first available title if missing.
        if (trim((string) $row['sku']) === '') {
            foreach ($selections as $loc => $fields) {
                if (!empty($fields)) {
                    $sourceTitle = trim((string) ($row['title_' . $loc] ?? ''));
                    if ($sourceTitle !== '') {
                        $row['sku'] = app(SkuGenerator::class)->fromTitle($sourceTitle);
                        break;
                    }
                }
            }
        }

        // Each locale fires its own Anthropic call (up to ~90s). PHP's default
        // max_execution_time (30s) would otherwise terminate mid-request.
        @set_time_limit(0);

        $generator = app(ProductContentGenerator::class);
        $allowedFields = $this->aiGeneratableFields();

        foreach ($selections as $locale => $rawFields) {
            $fields = array_values(array_unique(array_intersect($rawFields, $allowedFields)));
            if (empty($fields)) {
                continue;
            }

            foreach ($fields as $f) {
                $row['status_' . $locale][$f] = 'pending';
            }

            // 'slug' is computed locally; everything else goes to the API.
            $apiFields = array_values(array_filter($fields, fn($f) => $f !== 'slug'));

            $result = [];
            if (!empty($apiFields)) {
                try {
                    // Reset the timer before each per-locale API call.
                    @set_time_limit(0);

                    $context = [
                        'title' => trim((string) ($row['title_' . $locale] ?? '')),
                        'brand' => '',
                        'category' => '',
                        'attributes' => [],
                    ];
                    $result = $generator->generateFields($apiFields, $locale, $context);
                } catch (Throwable $e) {
                    Log::error('Bulk AI generation failed', [
                        'locale' => $locale,
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    $msg = __('Generation failed for :locale.', ['locale' => strtoupper($locale)]);
                    foreach ($apiFields as $f) {
                        $row['status_' . $locale][$f] = 'error';
                        $row['errors_' . $locale][$f] = $msg;
                    }
                    if (in_array('slug', $fields, true)) {
                        $row['status_' . $locale]['slug'] = 'error';
                        $row['errors_' . $locale]['slug'] = $msg;
                    }

                    continue;
                }
            }

            foreach ($apiFields as $f) {
                if (!array_key_exists($f, $result) || trim((string) $result[$f]) === '') {
                    $row['status_' . $locale][$f] = 'error';
                    $row['errors_' . $locale][$f] = __('No content returned for this field.');

                    continue;
                }
                $row['fields_' . $locale][$f] = (string) $result[$f];
                $row['status_' . $locale][$f] = 'success';
            }

            if (in_array('slug', $fields, true)) {
                $sourceTitle = (string) ($row['title_' . $locale] ?? '');
                if (trim($sourceTitle) === '') {
                    $row['status_' . $locale]['slug'] = 'error';
                    $row['errors_' . $locale]['slug'] = __('Need a title to generate the slug.');
                } else {
                    $row['slug_' . $locale] = Str::slug($sourceTitle);
                    $row['status_' . $locale]['slug'] = 'success';
                }
            }
        }

        $row['generated'] = true;
        $row['expanded'] = true;
        $this->bulkItems[$index] = $row;
    }

    public function generateAllBulkItems(): void
    {
        // Disable PHP's execution time limit — this iterates rows × locales,
        // each firing an Anthropic call that can run for tens of seconds.
        @set_time_limit(0);

        foreach (array_keys($this->bulkItems) as $i) {
            if (!($this->bulkItems[$i]['created'] ?? false)) {
                $this->generateBulkItem($i);
            }
        }
    }

    protected function uniqueProductSlug(string $base): string
    {
        $base = Str::slug($base);
        if ($base === '') {
            $base = 'product-' . Str::random(6);
        }
        $slug = $base;
        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    protected function uniqueTranslationSlug(string $base, string $locale): string
    {
        $base = Str::slug($base);
        if ($base === '') {
            $base = 'product-' . Str::random(6);
        }
        $slug = $base;
        $i = 1;
        $type = morph_type_of(new Product());
        while (Translation::where('translatable_type', $type)->where('language', $locale)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Validate a single row for creation. Returns error string or null when valid.
     * Pass $batchSkus to enforce uniqueness within the current batch.
     */
    protected function validateBulkRow(array $row, array &$batchSkus = []): ?string
    {
        $titleEn = trim((string) ($row['title_en'] ?? ''));
        $titleNl = trim((string) ($row['title_nl'] ?? ''));
        $sku = trim((string) ($row['sku'] ?? ''));

        if ($titleEn === '' && $titleNl === '') {
            return __('Title is required in at least one language.');
        }
        if ($sku === '') {
            return __('SKU is required.');
        }
        if (isset($batchSkus[strtolower($sku)])) {
            return __('Duplicate SKU within this batch.');
        }
        if (Product::where('sku', $sku)->exists()) {
            return __('SKU already exists.');
        }

        $batchSkus[strtolower($sku)] = true;

        return null;
    }

    protected function persistBulkRow(array &$row): void
    {
        $mainLocale = $this->bulkMainLocale();
        $otherLocale = $mainLocale === 'nl' ? 'en' : 'nl';

        // Primary locale = main locale if it has a title; otherwise the other one.
        $primary = trim((string) $row['title_' . $mainLocale]) !== '' ? $mainLocale : $otherLocale;
        $secondary = $primary === 'en' ? 'nl' : 'en';

        $primaryTitle = trim((string) $row['title_' . $primary]);
        $primarySlug = trim((string) $row['slug_' . $primary]);
        $primarySlug = $this->uniqueProductSlug($primarySlug !== '' ? $primarySlug : $primaryTitle);

        $primaryFields = $row['fields_' . $primary];

        $product = Product::create([
            'name' => $primaryTitle,
            'title' => $primaryTitle,
            'sku' => trim((string) $row['sku']),
            'slug' => $primarySlug,
            'state' => 'draft',
            'subtitle' => (string) ($primaryFields['subtitle'] ?? ''),
            'excerpt' => (string) ($primaryFields['excerpt'] ?? ''),
            'description' => (string) ($primaryFields['short_description'] ?? ''),
            'content' => (string) ($primaryFields['content'] ?? ''),
            'product_information' => (string) ($primaryFields['product_information'] ?? ''),
            'meta_title' => (string) ($primaryFields['meta_title'] ?? ''),
            'meta_description' => (string) ($primaryFields['meta_description'] ?? ''),
        ]);

        // Write the secondary locale as a translation when it has a title.
        $secondaryTitle = trim((string) $row['title_' . $secondary]);
        if ($secondaryTitle !== '') {
            $secondaryFields = $row['fields_' . $secondary];
            $secondarySlugRaw = trim((string) $row['slug_' . $secondary]);
            $secondarySlug = $this->uniqueTranslationSlug($secondarySlugRaw !== '' ? $secondarySlugRaw : $secondaryTitle, $secondary);

            $translatableData = [
                'name' => $secondaryTitle,
                'title' => $secondaryTitle,
                'subtitle' => (string) ($secondaryFields['subtitle'] ?? ''),
                'slug' => $secondarySlug,
                'excerpt' => (string) ($secondaryFields['excerpt'] ?? ''),
                'description' => (string) ($secondaryFields['short_description'] ?? ''),
                'content' => (string) ($secondaryFields['content'] ?? ''),
                'product_information' => (string) ($secondaryFields['product_information'] ?? ''),
                'meta_title' => (string) ($secondaryFields['meta_title'] ?? ''),
                'meta_description' => (string) ($secondaryFields['meta_description'] ?? ''),
            ];

            Translation::create([
                'translatable_type' => morph_type_of($product),
                'translatable_id' => $product->getKey(),
                'language' => $secondary,
                'name' => $secondaryTitle,
                'slug' => $secondarySlug,
                'fields' => $translatableData,
            ]);
        }

        $row['created'] = true;
        $row['rowError'] = '';
    }

    public function createBulkItem(int $index): void
    {
        if (!isset($this->bulkItems[$index]) || ($this->bulkItems[$index]['created'] ?? false)) {
            return;
        }

        $row = $this->bulkItems[$index];

        $batchSkus = [];
        if ($error = $this->validateBulkRow($row, $batchSkus)) {
            $row['rowError'] = $error;
            $this->bulkItems[$index] = $row;
            Flux::toast($error, variant: 'warning');

            return;
        }

        $this->persistBulkRow($row);
        $this->bulkItems[$index] = $row;

        $this->dispatch('pg:eventRefresh-product-table');
        Flux::toast(__('Product created as draft.'), variant: 'success');
    }

    public function bulkCreateAll(): void
    {
        $batchSkus = [];
        $created = 0;
        $skipped = 0;

        foreach ($this->bulkItems as $i => $row) {
            if ($row['created'] ?? false) {
                continue;
            }

            $err = $this->validateBulkRow($row, $batchSkus);
            if ($err !== null) {
                $this->bulkItems[$i]['rowError'] = $err;
                $skipped++;

                continue;
            }

            try {
                $this->persistBulkRow($row);
                $this->bulkItems[$i] = $row;
                $created++;
            } catch (Throwable $e) {
                Log::error('Bulk product creation failed', ['index' => $i, 'error' => $e->getMessage()]);
                $this->bulkItems[$i]['rowError'] = __('Could not create product.');
                $skipped++;
            }
        }

        if ($created > 0) {
            $this->dispatch('pg:eventRefresh-product-table');
            Flux::toast(trans_choice(':count product created as draft.|:count products created as drafts.', $created, ['count' => $created]), variant: 'success');
        }
        if ($skipped > 0 && $created === 0) {
            Flux::toast(__('No products were created. Check row errors.'), variant: 'warning');
        }

        $remaining = collect($this->bulkItems)->filter(fn($r) => !($r['created'] ?? false))->count();
        if ($remaining === 0) {
            $this->showBulkGenerateModal = false;
        }
    }
};
?>

<div class="flow-root" @if ($activeExport && $activeExport->isInProgress()) wire:poll.2s="pollExport" @endif>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Products') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage your simple and variable products.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-4">
            <flux:modal.trigger name="product-import-modal">
                <flux:button icon="document-arrow-down" />
            </flux:modal.trigger>
            <flux:button icon="squares-plus" wire:click="openBulkGenerateModal">
                {{ __('Bulk Generate') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" href="{{ route('products.create') }}" wire:navigate>
                {{ __('New Product') }}
            </flux:button>
        </div>
    </div>

    @if ($activeExport)
        <div class="mb-6">
            @if ($activeExport->isInProgress())
                <flux:card class="p-4!">
                    <div class="flex items-center gap-4">
                        <div class="shrink-0">
                            <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Exporting products...') }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('You can navigate away — the export will continue in the background.') }}
                            </p>
                        </div>
                    </div>
                </flux:card>
            @elseif ($activeExport->isCompleted())
                <flux:card class="p-4!">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 text-green-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                {{ __('Export completed!') }}
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">({{ $activeExport->total_rows }}
                                    {{ __('rows') }})</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" icon="arrow-down-tray"
                                href="{{ $activeExport->downloadUrl() }}">
                                {{ __('Download') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="dismissExport">
                                {{ __('Dismiss') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @elseif ($activeExport->isFailed())
                <flux:card class="p-4! border-red-200 dark:border-red-800">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="shrink-0 text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">
                                    {{ __('Export failed.') }}
                                </p>
                                @if ($activeExport->error)
                                    <p class="text-xs text-red-500 dark:text-red-400 mt-0.5">
                                        {{ Str::limit($activeExport->error, 100) }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" wire:click="retryExport">{{ __('Retry') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="dismissExport">{{ __('Dismiss') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endif
        </div>
    @endif

    <livewire:product-table />

    <livewire:products.bulk-edit-products-component />

    <livewire:products.import-modal />

    <flux:modal wire:model="showBulkGenerateModal" class="max-w-5xl! w-full">
        @php
            $aiFields = $this->aiGeneratableFields();
            $aiLabels = $this->aiFieldLabels();
            $aiLocales = $this->bulkLocales();
        @endphp

        <div class="space-y-6">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Bulk Generate Products') }}</flux:heading>
                <flux:subheading>
                    {{ __('Create multiple draft products with optional AI-generated content for English and Dutch.') }}
                </flux:subheading>
            </div>

            {{-- Selection mode + global field selections --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50/60 dark:bg-zinc-900/40">
                <div class="p-5">
                    <flux:checkbox wire:model.live="useGlobalSelection"
                        label="{{ __('Apply field selection to all rows') }}"
                        description="{{ __('Turn off to pick AI fields per row instead of a single shared set.') }}" />
                </div>

                @if ($useGlobalSelection)
                    <div
                        class="border-t border-zinc-200 dark:border-zinc-700 grid grid-cols-1 md:grid-cols-2 md:divide-x md:divide-zinc-200 dark:md:divide-zinc-700">
                        @foreach ($aiLocales as $loc)
                            <div class="p-5 space-y-3">
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-200 uppercase tracking-wider">
                                        {{ strtoupper($loc) }} {{ __('Fields') }}
                                    </p>
                                    <div class="flex items-center gap-1">
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="bulkGlobalSelectAll('{{ $loc }}')">
                                            {{ __('All') }}</flux:button>
                                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                        <flux:button type="button" size="xs" variant="ghost"
                                            wire:click="bulkGlobalDeselectAll('{{ $loc }}')">
                                            {{ __('None') }}</flux:button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                    @foreach ($aiFields as $field)
                                        <label
                                            class="inline-flex items-center gap-2 text-sm cursor-pointer text-zinc-700 dark:text-zinc-200">
                                            <flux:checkbox wire:model="globalSelections.{{ $loc }}"
                                                value="{{ $field }}" />
                                            <span class="truncate">{{ $aiLabels[$field] ?? $field }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Bulk item rows --}}
            <div class="space-y-3">
                @foreach ($bulkItems as $index => $item)
                    <div wire:key="bulk-item-{{ $item['_uid'] ?? $index }}"
                        class="rounded-xl border transition-colors
                            @if ($item['created'] ?? false) border-emerald-300 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20
                            @else border-zinc-200 dark:border-zinc-700 @endif">

                        {{-- Header: index badge, titles, SKU, delete --}}
                        <div class="p-5">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex-shrink-0 mt-7 size-7 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 text-sm font-semibold flex items-center justify-center tabular-nums">
                                    {{ $index + 1 }}
                                </div>

                                <div class="flex-1 grid grid-cols-1 md:grid-cols-[1fr_1fr_10rem] gap-3">
                                    <div class="space-y-1">
                                        <label
                                            class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-semibold">
                                            EN {{ __('Title') }}
                                        </label>
                                        <flux:input wire:model="bulkItems.{{ $index }}.title_en"
                                            placeholder="{{ __('English title') }}"
                                            :disabled="$item['created'] ?? false" />
                                    </div>

                                    <div class="space-y-1">
                                        <label
                                            class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-semibold">
                                            NL {{ __('Title') }}
                                        </label>
                                        <flux:input wire:model="bulkItems.{{ $index }}.title_nl"
                                            placeholder="{{ __('Dutch title') }}"
                                            :disabled="$item['created'] ?? false" />
                                    </div>

                                    <div class="space-y-1">
                                        <label
                                            class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-semibold">
                                            {{ __('SKU') }}
                                        </label>
                                        <flux:input wire:model="bulkItems.{{ $index }}.sku"
                                            placeholder="SKU-001" :disabled="$item['created'] ?? false" />
                                    </div>
                                </div>

                                <div class="flex-shrink-0 mt-6">
                                    @if (count($bulkItems) > 1 && !($item['created'] ?? false))
                                        <flux:button variant="ghost" size="sm" icon="trash"
                                            wire:click="removeBulkItem({{ $index }})"
                                            class="text-zinc-400 hover:text-red-500!" />
                                    @endif
                                </div>
                            </div>

                            {{-- Row error --}}
                            @if (!empty($item['rowError']))
                                <div class="mt-3 ml-11 text-xs text-red-600 dark:text-red-400">
                                    {{ $item['rowError'] }}
                                </div>
                            @endif
                        </div>

                        {{-- Per-row field selection (only when not using global) --}}
                        @if (!$useGlobalSelection && !($item['created'] ?? false))
                            <div
                                class="border-t border-zinc-200 dark:border-zinc-700 grid grid-cols-1 md:grid-cols-2 md:divide-x md:divide-zinc-200 dark:md:divide-zinc-700">
                                @foreach ($aiLocales as $loc)
                                    <div class="p-5 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <p
                                                class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-200 uppercase tracking-wider">
                                                {{ strtoupper($loc) }} {{ __('Fields') }}
                                            </p>
                                            <div class="flex items-center gap-1">
                                                <flux:button type="button" size="xs" variant="ghost"
                                                    wire:click="bulkRowSelectAll({{ $index }}, '{{ $loc }}')">
                                                    {{ __('All') }}</flux:button>
                                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                                <flux:button type="button" size="xs" variant="ghost"
                                                    wire:click="bulkRowDeselectAll({{ $index }}, '{{ $loc }}')">
                                                    {{ __('None') }}</flux:button>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                            @foreach ($aiFields as $field)
                                                @php $st = $item['status_' . $loc][$field] ?? null; @endphp
                                                <label
                                                    class="inline-flex items-center gap-2 text-sm cursor-pointer text-zinc-700 dark:text-zinc-200">
                                                    <flux:checkbox
                                                        wire:model="bulkItems.{{ $index }}.selections_{{ $loc }}"
                                                        value="{{ $field }}" />
                                                    <span class="truncate">{{ $aiLabels[$field] ?? $field }}</span>
                                                    @if ($st === 'pending')
                                                        <flux:icon.arrow-path
                                                            class="size-3 text-blue-500 animate-spin shrink-0" />
                                                    @elseif ($st === 'success')
                                                        <flux:icon.check class="size-3 text-emerald-600 shrink-0" />
                                                    @elseif ($st === 'error')
                                                        <flux:icon.x-mark class="size-3 text-red-600 shrink-0" />
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Generated content preview / edit area --}}
                        @if (($item['generated'] ?? false) && !($item['created'] ?? false))
                            <div class="border-t border-zinc-200 dark:border-zinc-700 p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <p
                                        class="text-[11px] font-semibold text-zinc-700 dark:text-zinc-200 uppercase tracking-wider">
                                        {{ __('Generated Content') }}
                                    </p>
                                    <flux:button type="button" size="xs" variant="ghost"
                                        icon="{{ $item['expanded'] ?? true ? 'chevron-up' : 'chevron-down' }}"
                                        wire:click="toggleRowExpanded({{ $index }})">
                                        {{ $item['expanded'] ?? true ? __('Hide') : __('Show') }}
                                    </flux:button>
                                </div>

                                @if ($item['expanded'] ?? true)
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                                        @foreach ($aiLocales as $loc)
                                            @php
                                                $hasContent =
                                                    !empty($item['slug_' . $loc]) ||
                                                    collect($item['fields_' . $loc] ?? [])->contains(
                                                        fn($v) => trim((string) $v) !== '',
                                                    ) ||
                                                    !empty($item['status_' . $loc]);
                                            @endphp
                                            @if ($hasContent)
                                                <div class="space-y-3">
                                                    <p
                                                        class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">
                                                        {{ strtoupper($loc) }}
                                                    </p>

                                                    {{-- Slug --}}
                                                    @php $slugStatus = $item['status_' . $loc]['slug'] ?? null; @endphp
                                                    @if ($slugStatus === 'error')
                                                        <div class="space-y-1">
                                                            <label
                                                                class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-semibold flex items-center gap-1">
                                                                {{ __('Slug') }}
                                                                <flux:icon.x-mark class="size-3 text-red-600" />
                                                            </label>
                                                            <p class="text-xs text-red-600">
                                                                {{ $item['errors_' . $loc]['slug'] ?? '' }}</p>
                                                        </div>
                                                    @elseif (!empty($item['slug_' . $loc]) || $slugStatus === 'success')
                                                        <div class="space-y-1">
                                                            <label
                                                                class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-semibold flex items-center gap-1">
                                                                {{ __('Slug') }}
                                                                @if ($slugStatus === 'success')
                                                                    <flux:icon.check class="size-3 text-emerald-600" />
                                                                @endif
                                                            </label>
                                                            <flux:input
                                                                wire:model="bulkItems.{{ $index }}.slug_{{ $loc }}" />
                                                        </div>
                                                    @endif

                                                    {{-- Other AI fields --}}
                                                    @foreach ($aiFields as $field)
                                                        @if ($field === 'slug')
                                                            @continue
                                                        @endif
                                                        @php
                                                            $st = $item['status_' . $loc][$field] ?? null;
                                                            $err = $item['errors_' . $loc][$field] ?? null;
                                                            $val = $item['fields_' . $loc][$field] ?? '';
                                                            $show = $st !== null || trim((string) $val) !== '';
                                                        @endphp
                                                        @if ($show)
                                                            <div class="space-y-1">
                                                                <label
                                                                    class="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-semibold flex items-center gap-1">
                                                                    {{ $aiLabels[$field] ?? $field }}
                                                                    @if ($st === 'pending')
                                                                        <flux:icon.arrow-path
                                                                            class="size-3 text-blue-500 animate-spin" />
                                                                    @elseif ($st === 'success')
                                                                        <flux:icon.check
                                                                            class="size-3 text-emerald-600" />
                                                                    @elseif ($st === 'error')
                                                                        <flux:icon.x-mark
                                                                            class="size-3 text-red-600" />
                                                                    @endif
                                                                </label>
                                                                @if ($st === 'error')
                                                                    <p class="text-xs text-red-600">
                                                                        {{ $err }}</p>
                                                                @elseif (in_array($field, ['subtitle', 'meta_title'], true))
                                                                    <flux:input
                                                                        wire:model="bulkItems.{{ $index }}.fields_{{ $loc }}.{{ $field }}" />
                                                                @else
                                                                    <flux:textarea
                                                                        wire:model="bulkItems.{{ $index }}.fields_{{ $loc }}.{{ $field }}"
                                                                        rows="3" />
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Per-row action bar --}}
                        @if (!($item['created'] ?? false))
                            <div
                                class="border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50/40 dark:bg-zinc-900/30 px-5 py-3 flex items-center justify-end gap-2 rounded-b-xl">
                                <flux:button type="button" size="sm" variant="ghost" icon="arrow-uturn-left"
                                    wire:click="resetBulkItem({{ $index }})"
                                    :disabled="!($item['generated'] ?? false)">
                                    {{ __('Reset') }}
                                </flux:button>

                                <flux:button type="button" size="sm" variant="subtle" icon="sparkles"
                                    wire:click="generateBulkItem({{ $index }})" wire:loading.attr="disabled"
                                    wire:target="generateBulkItem({{ $index }}), generateAllBulkItems">
                                    <span wire:loading.remove
                                        wire:target="generateBulkItem({{ $index }}), generateAllBulkItems">
                                        {{ $item['generated'] ?? false ? __('Regenerate') : __('Generate') }}
                                    </span>
                                    <span wire:loading
                                        wire:target="generateBulkItem({{ $index }}), generateAllBulkItems">
                                        {{ __('Generating...') }}
                                    </span>
                                </flux:button>

                                <flux:button type="button" size="sm" variant="primary" icon="plus"
                                    wire:click="createBulkItem({{ $index }})" wire:loading.attr="disabled"
                                    wire:target="createBulkItem({{ $index }}), bulkCreateAll">
                                    {{ __('Create Product') }}
                                </flux:button>
                            </div>
                        @else
                            <div
                                class="border-t border-emerald-200 dark:border-emerald-800 px-5 py-3 text-sm
                                text-emerald-700 dark:text-emerald-400 flex items-center gap-2 rounded-b-xl">
                                <flux:icon.check-circle class="size-4" />
                                {{ __('Product created.') }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div>
                <flux:button variant="ghost" icon="plus" wire:click="addBulkItem" size="sm">
                    {{ __('Add Row') }}
                </flux:button>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between gap-2 pt-5 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showBulkGenerateModal', false)">
                    {{ __('Close') }}</flux:button>

                <div class="flex items-center gap-2">
                    <flux:button type="button" variant="subtle" icon="sparkles" wire:click="generateAllBulkItems"
                        wire:loading.attr="disabled" wire:target="generateAllBulkItems">
                        <span wire:loading.remove wire:target="generateAllBulkItems">
                            {{ __('Generate All') }}
                        </span>
                        <span wire:loading wire:target="generateAllBulkItems">
                            {{ __('Generating...') }}
                        </span>
                    </flux:button>

                    <flux:button type="button" variant="primary" icon="plus" wire:click="bulkCreateAll"
                        wire:loading.attr="disabled" wire:target="bulkCreateAll">
                        {{ __('Bulk Create Products') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showExportModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Export Language') }}</flux:heading>
                <flux:subheading>{{ __('Select the language for the export data.') }}</flux:subheading>
            </div>

            <div class="flex justify-center gap-3">
                <button type="button" wire:click="$set('exportLocale', 'en')"
                    class="flex items-center justify-center w-14 h-14 rounded-lg border-2 text-2xl transition-colors {{ $exportLocale === 'en' ? 'border-zinc-900 bg-zinc-900 dark:border-white dark:bg-white' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700' }}">
                    &#x1F1EC;&#x1F1E7;
                </button>
                <button type="button" wire:click="$set('exportLocale', 'nl')"
                    class="flex items-center justify-center w-14 h-14 rounded-lg border-2 text-2xl transition-colors {{ $exportLocale === 'nl' ? 'border-zinc-900 bg-zinc-900 dark:border-white dark:bg-white' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700' }}">
                    &#x1F1F3;&#x1F1F1;
                </button>
                <button type="button" wire:click="$set('exportLocale', 'both')"
                    class="flex items-center justify-center gap-1 w-14 h-14 rounded-lg border-2 text-lg transition-colors {{ $exportLocale === 'both' ? 'border-zinc-900 bg-zinc-900 dark:border-white dark:bg-white' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700' }}">
                    &#x1F1EC;&#x1F1E7;&#x1F1F3;&#x1F1F1;
                </button>
            </div>

            @if ($exportLocale === 'both')
                <div
                    class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Column headings') }}</span>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('exportHeadingLocale', 'en')"
                            class="flex items-center justify-center w-9 h-9 rounded-md border-2 text-lg transition-colors {{ $exportHeadingLocale === 'en' ? 'border-zinc-900 bg-zinc-900 dark:border-white dark:bg-white' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700' }}">
                            &#x1F1EC;&#x1F1E7;
                        </button>
                        <button type="button" wire:click="$set('exportHeadingLocale', 'nl')"
                            class="flex items-center justify-center w-9 h-9 rounded-md border-2 text-lg transition-colors {{ $exportHeadingLocale === 'nl' ? 'border-zinc-900 bg-zinc-900 dark:border-white dark:bg-white' : 'border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700' }}">
                            &#x1F1F3;&#x1F1F1;
                        </button>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showExportModal', false)">
                    {{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="primary" wire:click="confirmExport">{{ __('Export') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

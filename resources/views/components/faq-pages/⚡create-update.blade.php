<?php

use App\Concerns\HandlesWysiwygMedia;
use App\Models\FaqItem;
use App\Models\FaqPage;
use App\Models\FaqSection;
use App\Services\FaqTranslator;
use App\Support\LocalizedModelValue;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Vanilo\Translation\Models\Translation;

new class extends Component
{
    use HandlesWysiwygMedia;
    use WithFileUploads;

    public ?FaqPage $faqPage = null;

    // Top-level page fields (bound to the currently selected locale).
    public string $title = '';

    public string $slug = '';

    public string $intro = '';

    public string $support_title = '';

    public string $support_text = '';

    public string $meta_title = '';

    public string $meta_description = '';

    public string $status = 'draft';

    public $hero_image;

    public string $selectedLocale = '';

    /** @var array<string, array<string, string>> Per-locale buffer for top-level fields. */
    public array $translations = [];

    /** Shown when the target locale already has content the AI would overwrite. */
    public bool $showTranslateConfirm = false;

    /**
     * The section builder. Each section:
     * ['uid','id','icon','name'=>[locale=>str],'subtitle'=>[locale=>str],'items'=>[['uid','faq_item_id']]]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $sections = [];

    /** Per-section search state, keyed by section uid. */
    public array $itemSearch = [];

    public array $itemResults = [];

    public function mount(?FaqPage $faqPage = null): void
    {
        $this->selectedLocale = $this->mainLocale();
        $this->initTranslations();

        if ($faqPage && $faqPage->exists) {
            $this->faqPage = $faqPage;
            $this->title = (string) $faqPage->title;
            $this->slug = (string) $faqPage->slug;
            $this->intro = (string) $faqPage->intro;
            $this->support_title = (string) $faqPage->support_title;
            $this->support_text = (string) $faqPage->support_text;
            $this->meta_title = (string) $faqPage->meta_title;
            $this->meta_description = (string) $faqPage->meta_description;
            $this->status = $faqPage->status;

            $this->storeFieldsToTranslations($this->mainLocale());

            foreach ($this->otherLocales() as $locale) {
                $translation = Translation::findByModel($faqPage, $locale);
                $fields = $translation && is_array($translation->fields) ? $translation->fields : [];

                foreach ($this->translatableFields() as $field) {
                    $this->translations[$locale][$field] = $field === 'slug'
                        ? (string) ($translation?->slug ?? '')
                        : (string) ($fields[$field] ?? '');
                }
            }

            $this->loadSections($faqPage);
        }
    }

    public function hydrate(): void
    {
        if ($this->selectedLocale) {
            app()->setLocale($this->selectedLocale);
        }
    }

    protected function loadSections(FaqPage $faqPage): void
    {
        $faqPage->load(['sections.items', 'sections.translations']);
        $localeKeys = array_keys($this->locales());

        $this->sections = $faqPage->sections->map(function (FaqSection $section) use ($localeKeys) {
            $name = [];
            $subtitle = [];

            foreach ($localeKeys as $locale) {
                $name[$locale] = (string) (LocalizedModelValue::string($section, 'name', $section->name, $locale) ?? '');
                $subtitle[$locale] = (string) (LocalizedModelValue::string($section, 'subtitle', $section->subtitle, $locale) ?? '');
            }

            return [
                'uid' => 'sec-'.Str::random(8),
                'id' => $section->id,
                'icon' => (string) $section->icon,
                'name' => $name,
                'subtitle' => $subtitle,
                'items' => $section->items->map(fn (FaqItem $item) => [
                    'uid' => 'it-'.Str::random(8),
                    'faq_item_id' => $item->id,
                ])->values()->all(),
            ];
        })->values()->all();

        foreach ($this->sections as $section) {
            $this->itemSearch[$section['uid']] = '';
            $this->refreshResults($section['uid']);
        }
    }

    public function switchLocale(string $locale): void
    {
        if ($locale === $this->selectedLocale || ! array_key_exists($locale, $this->locales())) {
            return;
        }

        $this->storeFieldsToTranslations();
        $this->loadFieldsFromTranslations($locale);
        $this->selectedLocale = $locale;
        app()->setLocale($locale);

        // Suggestions are locale-aware — re-run them for the new language.
        foreach ($this->sections as $section) {
            $this->refreshResults($section['uid']);
        }

        $this->dispatch('wysiwyg-reload');
    }

    /* ---------------------------------------------------------------- */
    /* AI translation */
    /* ---------------------------------------------------------------- */

    /** The locale the translate button writes into (the one not being edited). */
    #[Computed]
    public function translateTarget(): string
    {
        $others = array_values(array_diff(array_keys($this->locales()), [$this->selectedLocale]));

        return $others[0] ?? $this->selectedLocale;
    }

    /**
     * Translate every page field and section from the locale currently
     * being edited into the other locale. Confirms first if that locale
     * already has content the translation would overwrite.
     */
    public function translate(): void
    {
        $this->storeFieldsToTranslations();

        if (trim((string) ($this->translations[$this->selectedLocale]['title'] ?? '')) === '') {
            Flux::toast(__('Add a page title first, then translate.'), variant: 'warning');

            return;
        }

        if ($this->translateTargetHasContent()) {
            $this->showTranslateConfirm = true;

            return;
        }

        $this->runTranslation();
    }

    public function runTranslation(): void
    {
        $this->showTranslateConfirm = false;
        $this->storeFieldsToTranslations();

        $source = $this->selectedLocale;
        $target = $this->translateTarget;

        // Flatten page fields and every section's name/subtitle into one
        // request so the whole page translates in a single API call.
        $fields = [];
        foreach ($this->aiTranslatableFields() as $field) {
            $fields[$field] = (string) ($this->translations[$source][$field] ?? '');
        }
        foreach ($this->sections as $i => $section) {
            $fields["section_{$i}_name"] = (string) ($section['name'][$source] ?? '');
            $fields["section_{$i}_subtitle"] = (string) ($section['subtitle'][$source] ?? '');
        }

        try {
            $translated = app(FaqTranslator::class)->translate($fields, $source, $target, htmlFields: ['intro']);
        } catch (Throwable $e) {
            Log::error('FAQ page translation failed', ['error' => $e->getMessage()]);
            Flux::toast(__('Translation failed. Please try again.'), variant: 'danger');

            return;
        }

        foreach ($this->aiTranslatableFields() as $field) {
            if (isset($translated[$field])) {
                $this->translations[$target][$field] = $translated[$field];
            }
        }
        foreach ($this->sections as $i => $section) {
            foreach (['name', 'subtitle'] as $key) {
                if (isset($translated["section_{$i}_{$key}"])) {
                    $this->sections[$i][$key][$target] = $translated["section_{$i}_{$key}"];
                }
            }
        }

        // Switch to the target locale so every field shows the result.
        $this->loadFieldsFromTranslations($target);
        $this->selectedLocale = $target;
        app()->setLocale($target);
        unset($this->translateTarget);

        foreach ($this->sections as $section) {
            $this->refreshResults($section['uid']);
        }
        $this->dispatch('wysiwyg-reload');

        Flux::toast(
            __('Translated to :locale.', ['locale' => $this->locales()[$target] ?? $target]),
            variant: 'success',
        );
    }

    /** Page fields the AI translates — slug is excluded (it is URL-derived). */
    protected function aiTranslatableFields(): array
    {
        return array_values(array_diff($this->translatableFields(), ['slug']));
    }

    protected function translateTargetHasContent(): bool
    {
        $target = $this->translateTarget;

        foreach ($this->aiTranslatableFields() as $field) {
            if (trim(strip_tags((string) ($this->translations[$target][$field] ?? ''))) !== '') {
                return true;
            }
        }

        foreach ($this->sections as $section) {
            foreach (['name', 'subtitle'] as $key) {
                if (trim((string) ($section[$key][$target] ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /* ---------------------------------------------------------------- */
    /* Section management */
    /* ---------------------------------------------------------------- */

    public function addSection(): void
    {
        $uid = 'sec-'.Str::random(8);

        $this->sections[] = [
            'uid' => $uid,
            'id' => null,
            'icon' => '',
            'name' => array_fill_keys(array_keys($this->locales()), ''),
            'subtitle' => array_fill_keys(array_keys($this->locales()), ''),
            'items' => [],
        ];

        $this->itemSearch[$uid] = '';
        $this->refreshResults($uid);
    }

    public function removeSection(int $index): void
    {
        if (! isset($this->sections[$index])) {
            return;
        }

        $uid = $this->sections[$index]['uid'] ?? null;
        unset($this->sections[$index]);
        $this->sections = array_values($this->sections);

        if ($uid !== null) {
            unset($this->itemSearch[$uid], $this->itemResults[$uid]);
        }

        unset($this->itemLabels);
    }

    public function moveSection($from, $to): void
    {
        $from = (int) $from;
        $to = (int) $to;

        if ($from === $to || ! isset($this->sections[$from])) {
            return;
        }

        $moved = array_splice($this->sections, $from, 1);
        array_splice($this->sections, $to, 0, $moved);
    }

    /* ---------------------------------------------------------------- */
    /* Item management (reusable FAQ items inside a section) */
    /* ---------------------------------------------------------------- */

    /**
     * Live search fires this on every (debounced) keystroke. The Livewire
     * payload for a dynamic-keyed array property is not reliably scalar,
     * so we ignore the supplied arguments entirely: instead we normalise
     * every term to a string and refresh every section's suggestions.
     */
    public function updatedItemSearch(): void
    {
        foreach ($this->sections as $section) {
            $uid = $section['uid'];
            $term = $this->itemSearch[$uid] ?? '';
            $this->itemSearch[$uid] = is_scalar($term) ? (string) $term : '';
            $this->refreshResults($uid);
        }
    }

    /**
     * Populate a section's question suggestions. With a 2+ character term
     * it searches the FAQ bank; without one it autoloads the most recent
     * items so the editor always has something to pick from.
     */
    protected function refreshResults(string $sectionUid): void
    {
        $section = collect($this->sections)->firstWhere('uid', $sectionUid);

        if ($section === null) {
            $this->itemResults[$sectionUid] = [];

            return;
        }

        $term = trim((string) ($this->itemSearch[$sectionUid] ?? ''));
        $excludeIds = collect($section['items'] ?? [])->pluck('faq_item_id')->filter()->all();
        $locale = $this->selectedLocale;

        $query = FaqItem::query()
            ->when($excludeIds, fn ($q) => $q->whereNotIn('id', $excludeIds));

        if (mb_strlen($term) >= 2) {
            $query->where(function ($q) use ($term, $locale) {
                $q->where('question', 'like', "%{$term}%");

                if ($locale !== $this->mainLocale()) {
                    $q->orWhereHas('translations', fn ($t) => $t
                        ->where('language', $locale)
                        ->where('fields->question', 'like', "%{$term}%"));
                }
            });
        } else {
            $query->latest();
        }

        $this->itemResults[$sectionUid] = $query->limit(15)->get()
            ->map(fn (FaqItem $item) => [
                'id' => $item->id,
                'question' => (string) LocalizedModelValue::string($item, 'question', $item->question),
            ])
            ->all();
    }

    public function addItem(int $sectionIndex, int $faqItemId): void
    {
        if (! isset($this->sections[$sectionIndex])) {
            return;
        }

        $already = collect($this->sections[$sectionIndex]['items'])->firstWhere('faq_item_id', $faqItemId);

        if ($already) {
            Flux::toast(__('That question is already in this section.'), variant: 'warning');

            return;
        }

        $this->sections[$sectionIndex]['items'][] = [
            'uid' => 'it-'.Str::random(8),
            'faq_item_id' => $faqItemId,
        ];

        $uid = $this->sections[$sectionIndex]['uid'];
        $this->itemSearch[$uid] = '';
        $this->refreshResults($uid);
        unset($this->itemLabels);
    }

    public function removeItem(int $sectionIndex, int $itemIndex): void
    {
        if (! isset($this->sections[$sectionIndex]['items'][$itemIndex])) {
            return;
        }

        unset($this->sections[$sectionIndex]['items'][$itemIndex]);
        $this->sections[$sectionIndex]['items'] = array_values($this->sections[$sectionIndex]['items']);
        $this->refreshResults($this->sections[$sectionIndex]['uid']);
        unset($this->itemLabels);
    }

    public function moveItem($sectionIndex, $from, $to): void
    {
        $sectionIndex = (int) $sectionIndex;
        $from = (int) $from;
        $to = (int) $to;

        if ($from === $to || ! isset($this->sections[$sectionIndex]['items'][$from])) {
            return;
        }

        $items = $this->sections[$sectionIndex]['items'];
        $moved = array_splice($items, $from, 1);
        array_splice($items, $to, 0, $moved);
        $this->sections[$sectionIndex]['items'] = $items;
    }

    /**
     * Fresh question labels (in the selected locale) for every item
     * currently referenced anywhere in the builder.
     */
    #[Computed]
    public function itemLabels(): array
    {
        $ids = collect($this->sections)
            ->pluck('items')
            ->flatten(1)
            ->pluck('faq_item_id')
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return FaqItem::whereIn('id', $ids)->get()
            ->mapWithKeys(fn (FaqItem $item) => [
                $item->id => LocalizedModelValue::string($item, 'question', $item->question),
            ])
            ->all();
    }

    /* ---------------------------------------------------------------- */
    /* Persistence */
    /* ---------------------------------------------------------------- */

    public function save()
    {
        $this->storeFieldsToTranslations();

        $main = $this->mainLocale();

        $rules = [
            'translations.'.$main.'.title' => 'required|string|max:255',
            'translations.'.$main.'.slug' => 'nullable|string|max:255',
            'status' => 'required|in:draft,published',
            'hero_image' => 'nullable|image|max:10240',
        ];
        $attributes = [
            'translations.'.$main.'.title' => __('title'),
            'translations.'.$main.'.slug' => __('slug'),
        ];

        foreach ($this->sections as $index => $section) {
            $rules["sections.{$index}.name.{$main}"] = 'required|string|max:255';
            $attributes["sections.{$index}.name.{$main}"] = __('section name');
        }

        $this->validate($rules, [], $attributes);

        $oldWysiwygIds = $this->faqPage
            ? $this->extractWysiwygMediaIds(...array_column($this->translations, 'intro'))
            : [];

        $mainData = Arr::only($this->translations[$main], $this->translatableFields());
        $mainData['status'] = $this->status;

        DB::transaction(function () use ($mainData, $oldWysiwygIds) {
            if (! $this->faqPage) {
                $this->faqPage = FaqPage::create($mainData);
            } else {
                $this->faqPage->update($mainData);
            }

            foreach ($this->otherLocales() as $locale) {
                $payload = $this->translations[$locale];

                // Skip creating a blank translation row when nothing is set AND
                // no row exists yet — prevents collisions on the
                // (translatable_type, slug, language) unique index for empty slugs.
                $hasContent = collect($payload)->contains(fn ($value) => (string) $value !== '');

                if (! $hasContent && ! Translation::findByModel($this->faqPage, $locale)) {
                    continue;
                }

                // Auto-derive the slug from the translated title when the user
                // set a title but left the slug blank, matching the main-locale flow.
                if (trim((string) ($payload['title'] ?? '')) !== '' && trim((string) ($payload['slug'] ?? '')) === '') {
                    $payload['slug'] = Str::slug($payload['title']);
                }

                $this->syncTranslation($this->faqPage, $payload, $locale);
            }

            if ($this->hero_image) {
                $this->faqPage->clearMediaCollection('hero');
                $this->faqPage
                    ->addMedia($this->hero_image->getRealPath())
                    ->usingName($this->hero_image->getClientOriginalName())
                    ->usingFileName($this->hero_image->getClientOriginalName())
                    ->toMediaCollection('hero');
                $this->hero_image = null;
            }

            $this->syncSections();
            $this->cleanupRemovedWysiwygMedia($oldWysiwygIds);
        });

        Flux::toast(__('FAQ page saved successfully.'), variant: 'success');

        $this->redirect(route('faq-pages.index'), navigate: true);
    }

    protected function syncSections(): void
    {
        $main = $this->mainLocale();
        $keptIds = [];

        foreach ($this->sections as $index => $section) {
            $payload = [
                'faq_page_id' => $this->faqPage->id,
                'name' => $section['name'][$main] ?? '',
                'subtitle' => $section['subtitle'][$main] ?? '',
                'icon' => $section['icon'] ?? '',
                'sort_order' => $index,
            ];

            $model = ! empty($section['id']) ? FaqSection::find($section['id']) : null;

            if ($model) {
                $model->update($payload);
            } else {
                $model = FaqSection::create($payload);
            }

            $keptIds[] = $model->id;

            foreach ($this->otherLocales() as $locale) {
                $this->syncTranslation($model, [
                    'name' => $section['name'][$locale] ?? '',
                    'subtitle' => $section['subtitle'][$locale] ?? '',
                ], $locale);
            }

            $sync = [];
            foreach (($section['items'] ?? []) as $itemIndex => $item) {
                if (! empty($item['faq_item_id'])) {
                    $sync[(int) $item['faq_item_id']] = ['sort_order' => $itemIndex];
                }
            }
            $model->items()->sync($sync);
        }

        $this->faqPage->sections()
            ->whereNotIn('id', $keptIds ?: [0])
            ->get()
            ->each
            ->delete();
    }

    protected function syncTranslation(Model $model, array $payload, string $locale): void
    {
        $name = $payload['name'] ?? $payload['title'] ?? null;
        $slug = $payload['slug'] ?? null;
        if (is_string($slug) && $slug !== '') {
            $slug = Str::slug($slug);
        }
        // Empty strings collide on the (translatable_type, slug, language) unique
        // index; NULLs do not, so coerce blanks to NULL.
        if ($slug === '') {
            $slug = null;
        }
        $fields = Arr::except($payload, ['name', 'slug']);

        $translation = Translation::findByModel($model, $locale);

        if (! empty($slug)) {
            $slug = $this->uniqueTranslationSlug(
                $slug,
                $model->getMorphClass(),
                $locale,
                $translation?->id
            );
        }

        if ($translation) {
            $translation->update([
                'name' => $name,
                'slug' => $slug,
                'fields' => $fields,
            ]);

            return;
        }

        Translation::createForModel($model, $locale, array_merge($payload, [
            'name' => $name,
            'slug' => $slug,
        ]));
    }

    protected function uniqueTranslationSlug(string $baseSlug, string $translatableType, string $locale, ?int $excludeTranslationId = null): string
    {
        if ($baseSlug === '') {
            return $baseSlug;
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Translation::query()
                ->where('translatable_type', $translatableType)
                ->where('language', $locale)
                ->where('slug', $slug)
                ->when($excludeTranslationId, fn ($q, $id) => $q->where('id', '!=', $id))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    #[On('remove-upload')]
    public function handleRemoveUpload(array $params): void
    {
        if ($this->faqPage && ! empty($params['id'])) {
            $this->faqPage->media()->whereKey($params['id'])->get()->each->delete();
        }
    }

    /* ---------------------------------------------------------------- */
    /* Translation buffer helpers */
    /* ---------------------------------------------------------------- */

    protected function initTranslations(): void
    {
        foreach (array_keys($this->locales()) as $locale) {
            $this->translations[$locale] = array_fill_keys($this->translatableFields(), '');
        }
    }

    protected function storeFieldsToTranslations(?string $locale = null): void
    {
        $locale = $locale ?? $this->selectedLocale;

        foreach ($this->translatableFields() as $field) {
            $this->translations[$locale][$field] = (string) $this->{$field};
        }
    }

    protected function loadFieldsFromTranslations(string $locale): void
    {
        foreach ($this->translatableFields() as $field) {
            $this->{$field} = (string) ($this->translations[$locale][$field] ?? '');
        }
    }

    protected function wysiwygFieldValues(): array
    {
        return array_column($this->translations, 'intro');
    }

    protected function translatableFields(): array
    {
        return FaqPage::TRANSLATABLE_FIELDS;
    }

    protected function locales(): array
    {
        return config('app.available_locales');
    }

    protected function mainLocale(): string
    {
        return config('app.main_locale');
    }

    protected function otherLocales(): array
    {
        return array_diff(array_keys($this->locales()), [$this->mainLocale()]);
    }

    #[Computed]
    public function existingHeroMedia()
    {
        return $this->faqPage ? $this->faqPage->getMedia('hero') : collect();
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $faqPage ? __('Edit FAQ Page') : __('Create FAQ Page') }}</flux:heading>
                <flux:subheading>{{ __('Build a structured FAQ hub from reusable items.') }}</flux:subheading>
            </div>
            <div class="flex items-center gap-3">
                <flux:button type="button" size="sm" variant="filled" icon="language"
                    wire:click="translate" wire:loading.attr="disabled"
                    wire:target="translate,runTranslation">
                    <span wire:loading.remove wire:target="translate,runTranslation">
                        {{ __('Translate to :locale', ['locale' => config('app.available_locales')[$this->translateTarget] ?? $this->translateTarget]) }}
                    </span>
                    <span wire:loading wire:target="translate,runTranslation">
                        {{ __('Translating...') }}
                    </span>
                </flux:button>
                <div class="flex items-center gap-1">
                    @foreach (config('app.available_locales') as $localeCode => $localeName)
                        <button type="button" wire:click="switchLocale('{{ $localeCode }}')"
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-sm font-medium transition-colors {{ $selectedLocale === $localeCode ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
                            <span class="text-base leading-none">{{ $localeCode === 'en' ? '🇬🇧' : '🇳🇱' }}</span>
                        </button>
                    @endforeach
                </div>
                <flux:button variant="ghost" href="{{ route('faq-pages.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">
            <div class="md:col-span-2 space-y-6">
                {{-- Page details --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Page Details') }}</flux:heading>
                        <flux:subheading>{{ __('The big intro area shown above the FAQ sections.') }}</flux:subheading>
                    </div>

                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <flux:input wire:model="title" label="{{ __('Page Title') }}"
                                placeholder="{{ __('e.g. Epson ColorWorks — Frequently Asked Questions') }}" />
                            <flux:error name="translations.{{ $selectedLocale }}.title" />
                        </div>
                        <div class="flex-1">
                            <x-slug-input model="slug" placeholder="epson-colorworks-faq" />
                        </div>
                    </div>

                    <x-wysiwyg-editor wire:model="intro" :defer-delete="$faqPage !== null"
                        label="{{ __('Intro Text') }}"
                        placeholder="{{ __('Introduce the FAQ hub — supports links, lists, images and formatting...') }}" />
                </flux:card>

                {{-- Section builder --}}
                <flux:card class="space-y-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Sections') }}</flux:heading>
                            <flux:subheading>
                                {{ __('Drag to reorder. Each section becomes an entry in the in-page topic navigation.') }}
                            </flux:subheading>
                        </div>
                        <flux:button type="button" size="sm" icon="plus" wire:click="addSection">
                            {{ __('Add Section') }}
                        </flux:button>
                    </div>

                    @if (count($sections) === 0)
                        <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700 p-8 text-center">
                            <flux:text class="text-zinc-500">
                                {{ __('No sections yet. Add a section to start grouping questions.') }}
                            </flux:text>
                        </div>
                    @endif

                    <div x-sort="$wire.moveSection($item, $position)" class="space-y-4">
                        @foreach ($sections as $si => $section)
                            <div wire:key="{{ $section['uid'] }}" x-sort:item="{{ $si }}"
                                class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/60 dark:bg-zinc-800/40">
                                {{-- Section header --}}
                                <div class="flex items-center gap-2 p-3 border-b border-zinc-200 dark:border-zinc-700">
                                    <button type="button" x-sort:handle
                                        class="cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                                        title="{{ __('Drag to reorder') }}">
                                        <flux:icon name="bars-3" class="size-4" />
                                    </button>
                                    <flux:badge size="sm" color="zinc">{{ __('Section') }} {{ $si + 1 }}</flux:badge>
                                    <span class="text-sm text-zinc-500 truncate flex-1">
                                        {{ $section['name'][$selectedLocale] ?? '' ?: __('Untitled section') }}
                                    </span>
                                    <flux:button type="button" size="xs" variant="subtle" icon="trash"
                                        wire:click="removeSection({{ $si }})"
                                        wire:confirm="{{ __('Remove this section? The questions stay in the bank.') }}" />
                                </div>

                                <div class="p-4 space-y-4">
                                    <div class="flex flex-col md:flex-row gap-4">
                                        <div class="flex-1">
                                            <flux:input size="sm"
                                                wire:model="sections.{{ $si }}.name.{{ $selectedLocale }}"
                                                label="{{ __('Section Name') }}"
                                                placeholder="{{ __('e.g. Installation & Setup') }}" />
                                            <flux:error name="sections.{{ $si }}.name.{{ $selectedLocale }}" />
                                        </div>
                                        <div class="flex-1">
                                            <flux:input size="sm"
                                                wire:model="sections.{{ $si }}.subtitle.{{ $selectedLocale }}"
                                                label="{{ __('Subtitle') }} ({{ __('optional') }})"
                                                placeholder="{{ __('Short supporting line') }}" />
                                        </div>
                                        <div class="w-full md:w-48">
                                            <flux:select size="sm" wire:model="sections.{{ $si }}.icon"
                                                label="{{ __('Icon') }} ({{ __('optional') }})">
                                                @foreach (config('faq.section_icons') as $iconKey => $iconLabel)
                                                    <option value="{{ $iconKey }}">{{ $iconLabel }}</option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                    </div>

                                    {{-- Item search & autoloaded suggestions --}}
                                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                        <flux:input size="sm" icon="magnifying-glass"
                                            wire:model.live.debounce.400ms="itemSearch.{{ $section['uid'] }}"
                                            x-on:focus="open = true"
                                            placeholder="{{ __('Search the FAQ bank, or pick from the suggestions...') }}" />

                                        <div x-show="open" style="display: none"
                                            class="absolute z-20 mt-1 w-full rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg divide-y divide-zinc-100 dark:divide-zinc-700 max-h-60 overflow-y-auto">
                                            @forelse ($itemResults[$section['uid']] ?? [] as $result)
                                                <button type="button"
                                                    wire:click="addItem({{ $si }}, {{ $result['id'] }})"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700/60 flex items-center gap-2">
                                                    <flux:icon name="plus" class="size-4 text-zinc-400 shrink-0" />
                                                    <span class="truncate">{{ $result['question'] }}</span>
                                                </button>
                                            @empty
                                                <div class="px-3 py-2 text-sm text-zinc-500">
                                                    {{ __('No FAQ items found. Add questions in the FAQ Items bank first.') }}
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>

                                    {{-- Selected items (sortable) --}}
                                    @if (empty($section['items']))
                                        <flux:text class="text-sm text-zinc-400">
                                            {{ __('No questions in this section yet.') }}
                                        </flux:text>
                                    @else
                                        <div x-sort="$wire.moveItem({{ $si }}, $item, $position)" class="space-y-2">
                                            @foreach ($section['items'] as $ii => $item)
                                                <div wire:key="{{ $item['uid'] }}" x-sort:item="{{ $ii }}"
                                                    class="flex items-center gap-2 rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2">
                                                    <button type="button" x-sort:handle
                                                        class="cursor-grab text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                                        <flux:icon name="bars-3" class="size-4" />
                                                    </button>
                                                    <span class="flex-1 text-sm truncate">
                                                        {{ $this->itemLabels[$item['faq_item_id']] ?? __('(item no longer available)') }}
                                                    </span>
                                                    <flux:button type="button" size="xs" variant="subtle" icon="x-mark"
                                                        wire:click="removeItem({{ $si }}, {{ $ii }})" />
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                {{-- Support / CTA --}}
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Support Area') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Shown at the bottom of the page. The "Contact our experts" button links to the existing contact flow.') }}
                        </flux:subheading>
                    </div>

                    <flux:input wire:model="support_title" label="{{ __('Support Heading') }}"
                        placeholder="{{ __('Still in doubt?') }}" />
                    <flux:textarea wire:model="support_text" label="{{ __('Support Text') }}" rows="2"
                        placeholder="{{ __('Is your question not here? Contact our experts.') }}" />
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">
                <flux:card class="space-y-6">
                    <flux:heading size="lg">{{ __('Visibility') }}</flux:heading>

                    <flux:select wire:model="status" label="{{ __('Status') }}">
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="published">{{ __('Published') }}</option>
                    </flux:select>

                    <div>
                        <flux:label>{{ __('Hero Image') }}</flux:label>
                        <x-file-pond wire:model="hero_image" accept="image/*" :uploads="$this->existingHeroMedia" />
                        <flux:error name="hero_image" />
                    </div>
                </flux:card>

                <flux:card class="space-y-6">
                    <flux:heading size="sm">{{ __('SEO / Meta Data') }}</flux:heading>

                    <flux:input wire:model="meta_title" label="{{ __('Meta Title') }}"
                        placeholder="{{ __('SEO Title') }}" />
                    <flux:textarea wire:model="meta_description" label="{{ __('Meta Description') }}" rows="3"
                        placeholder="{{ __('SEO Description') }}" />
                </flux:card>

                <flux:card>
                    <flux:button variant="primary" type="submit" class="w-full">{{ __('Save FAQ Page') }}</flux:button>
                </flux:card>
            </div>
        </div>
    </form>

    {{-- Confirm before an AI translation overwrites the other locale --}}
    <flux:modal wire:model.self="showTranslateConfirm" class="md:w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Replace existing translation?') }}</flux:heading>
                <flux:subheading>
                    {{ __('The :locale version already has content. Translating will overwrite the page fields and section names.', ['locale' => config('app.available_locales')[$this->translateTarget] ?? $this->translateTarget]) }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showTranslateConfirm', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="danger" icon="language" wire:click="runTranslation"
                    wire:loading.attr="disabled" wire:target="runTranslation">
                    <span wire:loading.remove wire:target="runTranslation">{{ __('Translate & Replace') }}</span>
                    <span wire:loading wire:target="runTranslation">{{ __('Translating...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

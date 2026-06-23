<?php

use App\Services\SearchIndexInvalidator;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Vanilo\Foundation\Models\Taxon;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

new class extends Component
{
    use WithFileUploads;

    public ?Taxonomy $taxonomy = null;

    public string $selectedLocale = '';

    public array $taxonomyData = [];

    public array $taxonsFlat = [];

    public string $newTaxonName = '';

    public ?string $editingTaxonId = null;

    public $main_image;

    public function mount(?Taxonomy $taxonomy = null)
    {
        $this->selectedLocale = $this->mainLocale();
        $this->taxonomyData[$this->mainLocale()] = ['name' => '', 'slug' => ''];

        if ($taxonomy && $taxonomy->exists) {
            $this->taxonomy = $taxonomy;
            $this->loadTaxonomyData($taxonomy);
            $this->loadTaxons();
        }
    }

    // The legacy taxonomy-level language switcher is gone. The taxon tree is
    // edited in the main locale, and per-taxon English values live in the SEO panel.
    public function switchLocale(string $locale): void
    {
        $this->selectedLocale = $this->mainLocale();
    }

    protected function locales(): array
    {
        return config('app.available_locales', []);
    }

    protected function mainLocale(): string
    {
        return (string) config('app.main_locale', '');
    }

    protected function otherLocales(): array
    {
        return array_values(array_diff(array_keys($this->locales()), [$this->mainLocale()]));
    }

    protected function loadTaxonomyData(Taxonomy $taxonomy): void
    {
        $this->taxonomyData[$this->mainLocale()] = [
            'name' => (string) $taxonomy->name,
            'slug' => (string) $taxonomy->slug,
        ];
    }

    public function loadTaxons()
    {
        if (! $this->taxonomy || ! $this->taxonomy->exists) {
            return;
        }

        $this->taxonsFlat = [];

        $allTaxons = $this->taxonomy->taxons()->orderBy('priority')->orderBy('id')->get();

        $itemCounts = DB::table('model_taxons')
            ->select('taxon_id', DB::raw('count(*) as count'))
            ->groupBy('taxon_id')
            ->pluck('count', 'taxon_id');

        foreach ($allTaxons as $taxon) {
            $this->taxonsFlat[] = [
                'id' => $taxon->id,
                'parent_id' => $taxon->parent_id,
                'priority' => $taxon->priority ?? 0,
                'items_count' => $itemCounts[$taxon->id] ?? 0,
                'is_new' => false,
                'data' => $this->buildTaxonDataBuffer($taxon),
            ];
        }
    }

    protected function buildTaxonDataBuffer(Taxon $taxon): array
    {
        $buffer = [];

        $buffer[$this->mainLocale()] = [
            'name' => (string) $taxon->name,
            'slug' => (string) $taxon->slug,
            'meta_title' => (string) $taxon->meta_title,
            'meta_description' => (string) $taxon->meta_description,
        ];

        foreach ($this->otherLocales() as $locale) {
            $translation = Translation::findByModel($taxon, $locale);
            $fields = $translation && is_array($translation->fields) ? $translation->fields : [];

            $buffer[$locale] = [
                'name' => (string) ($translation?->getName() ?? ''),
                'slug' => (string) ($translation?->getSlug() ?? ''),
                'meta_title' => (string) ($fields['meta_title'] ?? ''),
                'meta_description' => (string) ($fields['meta_description'] ?? ''),
            ];
        }

        return $buffer;
    }

    #[Computed]
    public function taxonsTree()
    {
        $grouped = collect($this->taxonsFlat)->groupBy('parent_id');

        $buildTree = function ($parentId) use (&$buildTree, $grouped) {
            $nodes = $grouped->get($parentId, collect())->sortBy('priority')->values()->all();
            foreach ($nodes as &$node) {
                $node['flat_index'] = collect($this->taxonsFlat)->search(fn ($t) => (string) $t['id'] === (string) $node['id']);
                $node['children'] = $buildTree($node['id']);
            }

            return $nodes;
        };

        return $buildTree(null);
    }

    protected function rules(): array
    {
        return [
            'taxonomyData.'.$this->mainLocale().'.name' => 'required|string|max:255',
            'taxonomyData.'.$this->mainLocale().'.slug' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'taxonomyData.'.$this->mainLocale().'.name.required' => __('The :locale name is required.', ['locale' => $this->locales()[$this->mainLocale()] ?? $this->mainLocale()]),
            'main_image.image' => __('Must be an image.'),
            'main_image.max' => __('Max 10MB.'),
        ];
    }

    public function save()
    {
        $main = $this->mainLocale();

        if (empty($this->taxonomyData[$main]['slug'])) {
            $this->taxonomyData[$main]['slug'] = Str::slug($this->taxonomyData[$main]['name'] ?? '');
        }

        $this->validate();

        $wasCreated = ! ($this->taxonomy && $this->taxonomy->exists);

        DB::transaction(function () use ($main) {
            $payload = [
                'name' => $this->taxonomyData[$main]['name'],
                'slug' => $this->taxonomyData[$main]['slug'],
            ];

            if ($this->taxonomy && $this->taxonomy->exists) {
                $this->taxonomy->update($payload);
                $taxonomy = $this->taxonomy;
            } else {
                $taxonomy = Taxonomy::create($payload);
            }

            $this->syncTaxons($taxonomy);
            $this->uploadEditingTaxonMedia();
        });

        Flux::toast(
            __($wasCreated ? 'Taxonomy created successfully.' : 'Taxonomy updated successfully.'),
            variant: 'success'
        );

        return redirect()->route('taxonomies.index');
    }

    public function updateTreeOrder(array $serializedTree)
    {
        foreach ($serializedTree as $item) {
            $index = collect($this->taxonsFlat)->search(fn ($t) => (string) $t['id'] === (string) $item['id']);
            if ($index !== false) {
                $this->taxonsFlat[$index]['parent_id'] = ($item['parent_id'] !== '' && $item['parent_id'] !== null) ? $item['parent_id'] : null;
                $this->taxonsFlat[$index]['priority'] = (int) $item['priority'];
            }
        }

        if ($this->taxonomy && $this->taxonomy->exists) {
            DB::transaction(function () {
                $this->syncTaxons($this->taxonomy);
            });
            $this->loadTaxons();
            Flux::toast(__('Taxon order updated successfully.'), variant: 'success');
        }
    }

    public function addTaxon()
    {
        $this->newTaxonName = trim($this->newTaxonName);

        if ($this->newTaxonName === '') {
            return;
        }

        // New terms must be created in the main locale so that the canonical
        // base-column value is set deliberately by the user. Translations for
        // other locales are added afterwards by switching the locale.
        if ($this->selectedLocale !== $this->mainLocale()) {
            Flux::toast(
                __('Switch to :main to create a new term, then translate it.', [
                    'main' => __($this->locales()[$this->mainLocale()] ?? $this->mainLocale()),
                ]),
                variant: 'warning'
            );

            return;
        }

        $data = $this->emptyTaxonData();
        $data[$this->mainLocale()]['name'] = $this->newTaxonName;

        $priority = (collect($this->taxonsFlat)->whereNull('parent_id')->max('priority') ?? 0) + 1;

        if ($this->taxonomy && $this->taxonomy->exists) {
            DB::transaction(function () use ($data, $priority) {
                $taxon = Taxon::create([
                    'taxonomy_id' => $this->taxonomy->id,
                    'name' => $data[$this->mainLocale()]['name'],
                    'priority' => $priority,
                ]);

                foreach ($this->otherLocales() as $locale) {
                    $name = (string) ($data[$locale]['name'] ?? '');

                    if ($name === '') {
                        continue;
                    }

                    $this->syncTranslation($taxon, [
                        'name' => $name,
                        'slug' => Str::slug($name),
                    ], $locale);
                }
            });

            $this->loadTaxons();
            Flux::toast(__('Taxon added.'), variant: 'success');
        } else {
            $this->taxonsFlat[] = [
                'id' => 'new-'.(string) Str::uuid(),
                'parent_id' => null,
                'priority' => $priority,
                'items_count' => 0,
                'is_new' => true,
                'data' => $data,
            ];
        }

        $this->newTaxonName = '';
    }

    protected function emptyTaxonData(): array
    {
        $data = [];

        foreach (array_keys($this->locales()) as $locale) {
            $data[$locale] = ['name' => '', 'slug' => '', 'meta_title' => '', 'meta_description' => ''];
        }

        return $data;
    }

    public function editTaxon($id): void
    {
        $this->editingTaxonId = (string) $id;
    }

    public function cancelEdit(): void
    {
        $this->editingTaxonId = null;
        $this->main_image = null;
        unset($this->existingTaxonMainMedia);
    }

    public function removeTaxon($id)
    {
        $idsToRemove = [$id];

        $findChildren = function ($parentId) use (&$findChildren, &$idsToRemove) {
            $children = collect($this->taxonsFlat)->where('parent_id', $parentId)->pluck('id');
            foreach ($children as $childId) {
                $idsToRemove[] = $childId;
                $findChildren($childId);
            }
        };
        $findChildren($id);

        if ($this->taxonomy && $this->taxonomy->exists) {
            Taxon::whereIn('id', $idsToRemove)->delete();
            $this->loadTaxons();
            Flux::toast(__('Taxon removed successfully.'), variant: 'success');
        } else {
            $this->taxonsFlat = collect($this->taxonsFlat)
                ->filter(fn ($t) => ! in_array($t['id'], $idsToRemove))
                ->values()
                ->all();
        }
    }

    protected function syncTaxons(Taxonomy $taxonomy): void
    {
        $main = $this->mainLocale();
        $existingTaxons = $taxonomy->taxons()->get()->keyBy('id');

        $saveNode = function ($node, $parentId = null) use (&$saveNode, $taxonomy, $existingTaxons, $main) {
            $dbId = null;
            $taxon = null;
            $mainPayload = [
                'name' => (string) ($node['data'][$main]['name'] ?? ''),
                // An empty slug lets the Sluggable trait derive one from the name;
                // a non-empty value is respected verbatim (editable per-locale slug).
                'slug' => Str::slug((string) ($node['data'][$main]['slug'] ?? '')),
                'meta_title' => $node['data'][$main]['meta_title'] ?? null,
                'meta_description' => $node['data'][$main]['meta_description'] ?? null,
            ];

            if ($node['is_new']) {
                $taxon = Taxon::create(array_merge($mainPayload, [
                    'taxonomy_id' => $taxonomy->id,
                    'parent_id' => $parentId,
                    'priority' => $node['priority'] ?? 0,
                ]));
                $dbId = $taxon->id;
            } else {
                $dbId = $node['id'];
                $existingTaxon = $existingTaxons->get($dbId);

                if ($existingTaxon) {
                    $existingTaxon->update(array_merge($mainPayload, [
                        'parent_id' => $parentId,
                        'priority' => $node['priority'] ?? 0,
                    ]));
                    $taxon = $existingTaxon;
                    $existingTaxons->forget($dbId);
                } else {
                    return;
                }
            }

            foreach ($this->otherLocales() as $locale) {
                $name = (string) ($node['data'][$locale]['name'] ?? '');
                $slugInput = (string) ($node['data'][$locale]['slug'] ?? '');
                // Use the explicitly entered slug when present, otherwise derive it from the name.
                $slug = $slugInput !== '' ? Str::slug($slugInput) : Str::slug($name);
                $metaTitle = $node['data'][$locale]['meta_title'] ?? null;
                $metaDescription = $node['data'][$locale]['meta_description'] ?? null;

                // Skip writing a blank translation row if nothing's set and none exists.
                $existingTranslation = Translation::findByModel($taxon, $locale);
                $hasAnyValue = $name !== ''
                    || $slugInput !== ''
                    || ($metaTitle !== null && $metaTitle !== '')
                    || ($metaDescription !== null && $metaDescription !== '');
                if (! $hasAnyValue && ! $existingTranslation) {
                    continue;
                }

                $this->syncTranslation($taxon, [
                    'name' => $name,
                    'slug' => $slug,
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                ], $locale);
            }

            foreach ($node['children'] as $child) {
                $saveNode($child, $dbId);
            }
        };

        foreach ($this->taxonsTree as $rootNode) {
            $saveNode($rootNode, null);
        }

        foreach ($existingTaxons as $toDelete) {
            $toDelete->delete();
        }
    }

    #[Computed]
    public function existingTaxonMainMedia()
    {
        $taxon = $this->editingTaxon();

        return $taxon ? $taxon->getMedia('main') : collect();
    }

    #[On('remove-upload')]
    public function handleRemoveUpload(array $params): void
    {
        $mediaId = $params['id'] ?? null;
        $taxon = $this->editingTaxon();

        if (! $mediaId || ! $taxon) {
            return;
        }

        $taxon->media()->where('id', $mediaId)->first()?->delete();

        $this->forgetTaxonMainMedia($taxon);
    }

    public function clearPendingTaxonImage(): void
    {
        $this->main_image = null;
    }

    public function removeEditingTaxonImage(): void
    {
        $taxon = $this->editingTaxon();

        if (! $taxon) {
            return;
        }

        $taxon->clearMediaCollection('main');

        $this->forgetTaxonMainMedia($taxon);
    }

    protected function uploadEditingTaxonMedia(): void
    {
        if (! $this->main_image) {
            return;
        }

        $taxon = $this->editingTaxon();

        if (! $taxon) {
            return;
        }

        $taxon->clearMediaCollection('main');
        $taxon
            ->addMedia($this->main_image->getRealPath())
            ->usingName($this->main_image->getClientOriginalName())
            ->usingFileName($this->main_image->getClientOriginalName())
            ->toMediaCollection('main');

        $this->main_image = null;

        $this->forgetTaxonMainMedia($taxon);
    }

    protected function editingTaxon(): ?Taxon
    {
        if (! $this->editingTaxonId || str_starts_with($this->editingTaxonId, 'new-')) {
            return null;
        }

        return Taxon::query()->find((int) $this->editingTaxonId);
    }

    protected function forgetTaxonMainMedia(Taxon $taxon): void
    {
        unset($this->existingTaxonMainMedia);

        app(SearchIndexInvalidator::class)->reindexForTaxons([$taxon->getKey()]);
    }

    protected function syncTranslation($model, array $payload, ?string $locale = null): void
    {
        $locale = $locale ?? app()->getLocale();

        $translation = Translation::findByModel($model, $locale);

        $name = $payload['name'] ?? null;
        $slug = $payload['slug'] ?? null;

        if (! empty($slug)) {
            $slug = $this->uniqueTranslationSlug(
                $slug,
                $model->getMorphClass(),
                $locale,
                $translation?->id
            );
        }

        $extraFields = Arr::except($payload, ['name', 'slug']);

        if ($translation) {
            $translation->update([
                'name' => $name,
                'slug' => $slug,
                'fields' => $extraFields,
            ]);

            return;
        }

        Translation::createForModel($model, $locale, array_merge($payload, ['slug' => $slug]));
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
}; ?>

<div>
    <form wire:submit="save">
        <div class="flex items-center justify-between mb-6">
            <div>
                <flux:heading size="xl" level="1">
                    {{ $taxonomy?->exists ? __('Edit Taxonomy') : __('Create Taxonomy') }}
                </flux:heading>
                <flux:subheading size="lg" class="mb-6">
                    {{ __('Define a group or classification for your products.') }}
                </flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:button href="{{ route('taxonomies.index') }}">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2 space-y-6">
                <!-- General Information -->
                <flux:card class="space-y-6 md:max-h-[calc(100vh-14rem)] md:overflow-y-auto md:pr-2">
                    <div>
                        <flux:heading size="lg">{{ __('General') }}</flux:heading>
                    </div>

                    <flux:input
                        wire:model.blur="taxonomyData.{{ $this->mainLocale() }}.name"
                        label="{{ __('Name') }}"
                        placeholder="E.g. Categories, Brands" />

                    <x-slug-input
                        :model="'taxonomyData.' . $this->mainLocale() . '.slug'"
                        modifier=".blur"
                        placeholder="categories (auto-generated if empty)" />
                </flux:card>

                <!-- Root Taxons -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Categories / Terms (Taxons)') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Add your base terms to this taxonomy one by one.') }}
                        </flux:subheading>
                    </div>

                    @php
                        $onMainLocale = $selectedLocale === config('app.main_locale');
                    @endphp
                    <div class="space-y-2">
                        <div class="flex gap-2">
                            <flux:input wire:model="newTaxonName" wire:keydown.enter.prevent="addTaxon"
                                :disabled="! $onMainLocale"
                                placeholder="{{ $onMainLocale
                                    ? __('Enter a new term…')
                                    : __('Switch to :main to add a new term', ['main' => __(config('app.available_locales')[config('app.main_locale')] ?? config('app.main_locale'))]) }}"
                                class="flex-1" />
                            <flux:button variant="primary" wire:click="addTaxon" :disabled="! $onMainLocale">{{ __('Add') }}</flux:button>
                        </div>
                        @unless($onMainLocale)
                            <flux:subheading class="text-xs">
                                {{ __('New terms are created in :main. Switch the language to translate them afterwards.', [
                                    'main' => __(config('app.available_locales')[config('app.main_locale')] ?? config('app.main_locale')),
                                ]) }}
                            </flux:subheading>
                        @endunless
                    </div>

                    <div x-data="taxonSortable()" x-init="initSortable($el)">
                        <ul
                            class="nested-sortable space-y-2 min-h-[50px] p-2 border border-dashed border-zinc-200 dark:border-zinc-700 rounded-lg bg-zinc-50 dark:bg-zinc-900/50">
                            @foreach($this->taxonsTree as $node)
                                @include('components.taxonomies.⚡taxon-node', ['node' => $node, 'selectedLocale' => $selectedLocale])
                            @endforeach
                            @if(empty($this->taxonsTree))
                                <div class="py-6 text-center text-zinc-500">
                                    {{ __('No terms added yet. Add one above.') }}
                                </div>
                            @endif
                        </ul>
                    </div>

                    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('alpine:init', () => {
                            Alpine.data('taxonSortable', () => ({
                                initSortable(container) {
                                    if (typeof Sortable === 'undefined') {
                                        setTimeout(() => this.initSortable(container), 100);
                                        return;
                                    }
                                    
                                    const initNested = (el) => {
                                        Array.from(el.querySelectorAll('.nested-sortable')).forEach(list => {
                                            if (list._sortable) {
                                                list._sortable.destroy();
                                            }
                                            
                                            list._sortable = new Sortable(list, {
                                                group: 'nested',
                                                animation: 150,
                                                fallbackOnBody: true,
                                                swapThreshold: 0.65,
                                                handle: '.handle',
                                                onEnd: (evt) => {
                                                    this.updateOrder();
                                                }
                                            });
                                        });
                                    };
                                    
                                    initNested(container);
                                },
                                
                                updateOrder() {
                                    const serializeTree = (ul, parentId = null) => {
                                        let items = [];
                                        let priority = 0;
                                        Array.from(ul.children).forEach(li => {
                                            if (li.tagName !== 'LI') return;
                                            let id = li.dataset.id;
                                            if (!id) return;
                                            
                                            items.push({ id: id, parent_id: parentId, priority: priority++ });
                                            
                                            let nestedUl = li.querySelector(':scope > ul.nested-sortable');
                                            if (nestedUl) {
                                                items = items.concat(serializeTree(nestedUl, id));
                                            }
                                        });
                                        return items;
                                    };
                                    
                                    const rootUl = this.$el.querySelector('.nested-sortable');
                                    const serialized = serializeTree(rootUl);
                                    
                                    console.log('Sending tree to Livewire:', serialized);
                                    this.$wire.updateTreeOrder(serialized).then(() => {
                                        // Re-initialize after Livewire updates the DOM
                                        setTimeout(() => {
                                            this.initSortable(this.$el);
                                        }, 50);
                                    });
                                }
                            }));
                        });
                    </script>
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6 self-start md:max-h-[calc(100vh-8rem)] md:overflow-y-auto md:pr-2">
                <!-- Help -->
                {{-- <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Help') }}</flux:heading>
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('A Taxonomy is a classification system. A Taxon is an individual term within that system. For example, "Brands" is a Taxonomy, and "Adidas" is a Taxon.') }}
                    </p>
                </flux:card> --}}

                <!-- Taxon Metadata Editor -->
                @if($editingTaxonId)
                    @php
                        $editingIndex = collect($taxonsFlat)->search(fn($t) => (string) $t['id'] === (string) $editingTaxonId);
                        $mainLocale = config('app.main_locale');
                    @endphp
                    @if($editingIndex !== false)
                        @php
                            $editingName = $taxonsFlat[$editingIndex]['data'][$selectedLocale]['name'] ?? '';
                            $editingName = $editingName !== '' ? $editingName : ($taxonsFlat[$editingIndex]['data'][$mainLocale]['name'] ?? '');
                        @endphp
                        <flux:card class="space-y-6 border-2 border-primary-500 shadow-lg">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <flux:heading size="lg">{{ __('Edit SEO & Translations') }}</flux:heading>
                                    <flux:button variant="subtle" icon="x-mark" size="sm" wire:click="cancelEdit" />
                                </div>
                                <flux:subheading>
                                    {{ __('Editing term: ') }}
                                    <strong>{{ $editingName }}</strong>
                                </flux:subheading>
                            </div>

                            @foreach (config('app.available_locales') as $localeCode => $localeName)
                                <flux:fieldset wire:key="seo-fieldset-{{ $editingTaxonId }}-{{ $localeCode }}">
                                    <flux:legend>
                                        <span class="text-base leading-none">{{ $localeCode === 'en' ? '🇬🇧' : '🇳🇱' }}</span>
                                        {{ __($localeName) }}
                                    </flux:legend>

                                    <flux:input
                                        wire:model.blur="taxonsFlat.{{ $editingIndex }}.data.{{ $localeCode }}.name"
                                        label="{{ __('Title') }}"
                                        placeholder="{{ __('Term title') }}" />

                                    <x-slug-input
                                        :model="'taxonsFlat.' . $editingIndex . '.data.' . $localeCode . '.slug'"
                                        modifier=".blur"
                                        placeholder="{{ __('auto-generated if empty') }}" />

                                    <flux:input
                                        wire:model.blur="taxonsFlat.{{ $editingIndex }}.data.{{ $localeCode }}.meta_title"
                                        label="{{ __('Meta Title') }}"
                                        placeholder="{{ __('SEO Title') }}" />

                                    <flux:textarea
                                        wire:model.blur="taxonsFlat.{{ $editingIndex }}.data.{{ $localeCode }}.meta_description"
                                        label="{{ __('Meta Description') }}"
                                        placeholder="{{ __('SEO Description') }}"
                                        rows="3" />
                                </flux:fieldset>
                            @endforeach

                            @if(! str_starts_with((string) $editingTaxonId, 'new-'))
                                <flux:fieldset>
                                    <flux:legend>{{ __('Image') }}</flux:legend>

                                    <flux:field>
                                        <flux:label>{{ __('Main Image') }}</flux:label>

                                        @php
                                            $existingMainMedia = $this->existingTaxonMainMedia->first();
                                        @endphp

                                        <div class="space-y-3">
                                            @if ($main_image)
                                                <div class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-white/10 dark:bg-white/5">
                                                    <img
                                                        src="{{ $main_image->temporaryUrl() }}"
                                                        alt="{{ __('Selected category image preview') }}"
                                                        class="size-20 shrink-0 rounded-md object-cover" />

                                                    <div class="min-w-0 flex-1">
                                                        <flux:text class="truncate text-sm font-medium">
                                                            {{ $main_image->getClientOriginalName() }}
                                                        </flux:text>
                                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ __('Preview of the image ready to save.') }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="x-mark"
                                                        wire:click="clearPendingTaxonImage"
                                                        aria-label="{{ __('Remove selected image') }}" />
                                                </div>
                                            @elseif ($existingMainMedia)
                                                <div class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-white/10 dark:bg-white/5">
                                                    <img
                                                        src="{{ $existingMainMedia->getUrl() }}"
                                                        alt="{{ __('Current category image') }}"
                                                        class="size-20 shrink-0 rounded-md object-cover" />

                                                    <div class="min-w-0 flex-1">
                                                        <flux:text class="truncate text-sm font-medium">
                                                            {{ $existingMainMedia->file_name }}
                                                        </flux:text>
                                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ __('Current saved image.') }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:button
                                                        type="button"
                                                        size="sm"
                                                        variant="danger"
                                                        icon="trash"
                                                        wire:click="removeEditingTaxonImage"
                                                        wire:confirm="{{ __('Remove this category image?') }}"
                                                        aria-label="{{ __('Remove saved image') }}" />
                                                </div>
                                            @endif

                                            <flux:file-upload wire:model="main_image" accept="image/*">
                                                <flux:file-upload.dropzone
                                                    heading="{{ __('Drop image or click to browse') }}"
                                                    text="{{ __('JPG, PNG, GIF, WEBP up to 10MB') }}"
                                                    with-progress
                                                    inline />
                                            </flux:file-upload>
                                        </div>

                                        <flux:error name="main_image" />
                                    </flux:field>
                                </flux:fieldset>
                            @endif

                            <flux:subheading class="text-xs">
                                {{ __('Changes save when you press the main Save button at the top.') }}
                            </flux:subheading>
                        </flux:card>
                    @endif
                @endif
            </div>
        </div>
    </form>
</div>

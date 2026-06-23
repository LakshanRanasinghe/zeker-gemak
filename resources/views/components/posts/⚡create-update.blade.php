<?php

use App\Concerns\HandlesWysiwygMedia;
use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Post;
use Flux\Flux;
use Vanilo\Translation\Models\Translation;

new class extends Component {
    use HandlesWysiwygMedia;

    public ?Post $post = null;

    public $title = '';
    public $content = '';
    public $excerpt = '';
    public $slug = '';
    public $status = 'draft';
    public $post_type = 'page';
    public $template = 'default';

    public $meta_title = '';
    public $meta_description = '';

    public string $selectedLocale = '';
    public array $translations = [];
    public bool $slugManuallyEdited = false;
    protected string $generatedSlugFromTitle = '';

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
        $this->syncSlugAutomationState();
        app()->setLocale($locale);

        $this->dispatch('wysiwyg-reload');
    }

    public function mount(?Post $post = null)
    {
        if ($post && $post->exists) {
            $this->post = $post;
            $this->title = $post->title;
            $this->content = $post->content;
            $this->excerpt = $post->excerpt;
            $this->slug = $post->slug;
            $this->status = $post->status;
            $this->post_type = 'post';
            $this->template = $post->template;

            $this->meta_title = (string) $post->getMeta('meta_title');
            $this->meta_description = (string) $post->getMeta('meta_description');

            $this->selectedLocale = app()->getLocale();
            $this->initTranslations();

            // Store main locale fields into translations array
            $this->storeFieldsToTranslations($this->mainLocale());

            // Load translations for other locales from DB
            foreach ($this->otherLocales() as $locale) {
                $translation = Translation::findByModel($post, $locale);
                if ($translation) {
                    $fields = is_array($translation->fields) ? $translation->fields : [];
                    foreach ($this->translatableFields() as $field) {
                        $this->translations[$locale][$field] = (string) ($fields[$field] ?? '');
                    }
                }
            }
            $this->syncSlugAutomationState();
        } else {
            $this->selectedLocale = app()->getLocale();
            $this->initTranslations();
            $this->syncSlugAutomationState();
        }
    }

    public function updatedTitle(string $value): void
    {
        $previousGeneratedSlug = $this->generatedSlugFromTitle;
        $nextGeneratedSlug = Str::slug($value);

        if (! $this->slugManuallyEdited && (trim($this->slug) === '' || $this->slug === $previousGeneratedSlug)) {
            $this->slug = $nextGeneratedSlug;
        }

        $this->generatedSlugFromTitle = $nextGeneratedSlug;
        $this->validateOnly('title');

        if ($this->slug !== '') {
            $this->validateOnly('slug');
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slug = Str::slug($value);
        $this->slugManuallyEdited = $this->slug !== '' && $this->slug !== Str::slug($this->title);

        $this->validateOnly('slug');
    }

    public function save()
    {
        $this->storeFieldsToTranslations();

        $oldWysiwygIds = [];
        if ($this->post && $this->post->exists) {
            $oldWysiwygIds = $this->extractWysiwygMediaIds($this->translations[$this->selectedLocale]['content']);
        }

        $this->validate();

        // Always save main locale data to the primary model columns
        $mainData = $this->translations[$this->mainLocale()];
        $mainData['post_type'] = 'post';
        $mainData['status'] = $this->status;
        $mainData['template'] = $this->template;

        if (empty($mainData['title'])) {
            $mainData['title'] = $this->title;
        }
        if (empty($mainData['slug'])) {
            $mainData['slug'] = $this->slug;
        }
        if (empty($mainData['content'])) {
            $mainData['content'] = $this->content;
        }
        if (empty($mainData['excerpt'])) {
            $mainData['excerpt'] = $this->excerpt;
        }

        DB::transaction(function () use ($mainData, $oldWysiwygIds): void {
            if (!$this->post) {
                $mainData['author'] = auth()->id();
                $post = Post::create($mainData);
                $this->post = $post;
                Flux::toast(__('Post created successfully.'), variant: 'success');
            } else {
                $this->post->update($mainData);
                Flux::toast(__('Post updated successfully.'), variant: 'success');
            }

            // Sync translations for all other locales
            foreach ($this->otherLocales() as $locale) {
                $payload = $this->translations[$locale];
                $hasContent = collect($payload)->contains(fn ($value) => trim((string) $value) !== '');

                if (! $hasContent && ! Translation::findByModel($this->post, $locale)) {
                    continue;
                }

                $this->syncTranslation($this->post, $payload, $locale);
            }

            // Sync SEO meta for main locale
            $this->syncMeta($this->post);

            $this->cleanupRemovedWysiwygMedia($oldWysiwygIds);
        });
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $this->slugUniqueRule()],
            'status' => ['required', 'in:draft,published'],
            'template' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function slugUniqueRule(): \Illuminate\Validation\Rules\Unique
    {
        if ($this->selectedLocale !== $this->mainLocale()) {
            $translation = $this->post ? Translation::findByModel($this->post, $this->selectedLocale) : null;

            return Rule::unique('translations', 'slug')
                ->where(fn ($query) => $query
                    ->where('translatable_type', morph_type_of(Post::class))
                    ->where('language', $this->selectedLocale))
                ->ignore($translation?->id);
        }

        return Rule::unique('posts', 'slug')->ignore($this->post?->id);
    }

    protected function wysiwygFieldValues(): array
    {
        return [$this->content];
    }

    protected function usesMainLocale(): bool
    {
        return app()->getLocale() === config('app.main_locale');
    }

    protected function syncMeta(Post $post): void
    {
        // Get the meta values from the main locale's translation buffer
        $mainMeta = $this->translations[$this->mainLocale()] ?? [];

        $post->meta()->updateOrCreate(
            ['meta_key' => 'meta_title'], 
            ['meta_value' => $mainMeta['meta_title'] ?? '']
        );
        $post->meta()->updateOrCreate(
            ['meta_key' => 'meta_description'], 
            ['meta_value' => $mainMeta['meta_description'] ?? '']
        );
    }

    protected function storeFieldsToTranslations(?string $locale = null): void
    {
        $locale = $locale ?? $this->selectedLocale;

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

    protected function translatableFields(): array
    {
        return ['title', 'content', 'excerpt', 'slug', 'meta_title', 'meta_description'];
    }

    protected function syncTranslation(Post $post, array $payload, string $locale): void
    {
        $payload = $this->normalizedTranslationPayload($payload, $locale);
        
        $translation = Translation::findByModel($post, $locale);

        if ($translation) {
            $translation->update([
                'name' => $payload['name'],
                'slug' => $payload['slug'],
                'fields' => collect($payload)->except(['name', 'slug'])->all(),
            ]);

            return;
        }

        Translation::createForModel($post, $locale, $payload);
    }

    protected function normalizedTranslationPayload(array $payload, string $locale): array
    {
        $payload['name'] = $payload['title'] ?? null;
        $payload['slug'] = trim((string) ($payload['slug'] ?? ''));

        if ($payload['slug'] === '' && trim((string) ($payload['title'] ?? '')) !== '') {
            $payload['slug'] = Str::slug($payload['title']);
        } elseif ($payload['slug'] !== '') {
            $payload['slug'] = Str::slug($payload['slug']);
        }

        if ($payload['slug'] !== '') {
            $payload['slug'] = $this->uniqueTranslationSlug($payload['slug'], $locale, Translation::findByModel($this->post, $locale)?->id);
        } else {
            $payload['slug'] = null;
        }

        return $payload;
    }

    protected function uniqueTranslationSlug(string $baseSlug, string $locale, ?int $excludeTranslationId = null): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (
            Translation::query()
                ->where('translatable_type', morph_type_of(Post::class))
                ->where('language', $locale)
                ->where('slug', $slug)
                ->when($excludeTranslationId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function syncSlugAutomationState(): void
    {
        $this->generatedSlugFromTitle = Str::slug($this->title);
        $this->slugManuallyEdited = $this->slug !== '' && $this->slug !== $this->generatedSlugFromTitle;
    }
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $post ? __('Edit Post') : __('Create Post') }}</flux:heading>
                <flux:subheading>{{ $post ? __('Update your post details.') : __('Add a new post.') }}
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
                            <flux:subheading>{{ __('Post title, content, and general details.') }}</flux:subheading>
                        </div>
                        <div class="flex items-center gap-1">
                            @foreach (config('app.available_locales') as $localeCode => $localeName)
                                <button type="button" wire:click="switchLocale('{{ $localeCode }}')"
                                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-sm font-medium transition-colors {{ $selectedLocale === $localeCode ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
                                    <span
                                        class="text-base leading-none">{{ $localeCode === 'en' ? '🇬🇧' : '🇳🇱' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <flux:input wire:model.live.debounce.600ms="title" label="{{ __('Title') }}" placeholder="Enter title" />
                        </div>
                        <div class="flex-1">
                            <x-slug-input model="slug" modifier=".blur" placeholder="Enter slug" />
                        </div>
                    </div>

                    <flux:textarea wire:model="excerpt" label="{{ __('Excerpt') }}" placeholder="Short summary..."
                        rows="2" />

                    <x-quill-editor wire:model="content" :defer-delete="$post !== null"
                        label="{{ __('Content') }}" placeholder="Detailed content..." />
                </flux:card>
            </div>

            <div class="space-y-6 sticky top-6">
                <!-- Status & Visibility -->
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Visibility & Status') }}</flux:heading>
                    </div>

                    <flux:select wire:model="status" label="{{ __('Status') }}">
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="published">{{ __('Published') }}</option>
                    </flux:select>

                    <flux:input wire:model="template" label="{{ __('Template') }}" placeholder="default" />

                    <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('SEO / Meta Data') }}</flux:heading>
                        
                        <flux:input wire:model="meta_title" label="{{ __('Meta Title') }}" placeholder="{{ __('SEO Title') }}" />
                        
                        <flux:textarea wire:model="meta_description" label="{{ __('Meta Description') }}" placeholder="{{ __('SEO Description') }}" rows="3" />
                    </div>

                    <div class="flex space-x-2 items-end">
                        <flux:button variant="primary" type="submit" class="w-full">{{ __('Save') }}</flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    </form>
</div>

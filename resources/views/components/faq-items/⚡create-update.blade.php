<?php

use App\Concerns\HandlesWysiwygMedia;
use App\Models\FaqItem;
use App\Services\FaqTranslator;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Vanilo\Translation\Models\Translation;

new class extends Component {
    use HandlesWysiwygMedia;

    public ?FaqItem $faqItem = null;

    public string $question = '';
    public string $answer = '';

    public string $selectedLocale = '';

    /** @var array<string, array<string, string>> Per-locale field buffer. */
    public array $translations = [];

    /** Shown when the target locale already has content the AI would overwrite. */
    public bool $showTranslateConfirm = false;

    public function mount(?FaqItem $faqItem = null): void
    {
        $this->selectedLocale = $this->mainLocale();
        $this->initTranslations();

        if ($faqItem && $faqItem->exists) {
            $this->faqItem = $faqItem;
            $this->question = (string) $faqItem->question;
            $this->answer = (string) $faqItem->answer;

            $this->storeFieldsToTranslations($this->mainLocale());

            foreach ($this->otherLocales() as $locale) {
                $translation = Translation::findByModel($faqItem, $locale);
                $fields = $translation && is_array($translation->fields) ? $translation->fields : [];

                foreach ($this->translatableFields() as $field) {
                    $this->translations[$locale][$field] = (string) ($fields[$field] ?? '');
                }
            }
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

        $this->dispatch('wysiwyg-reload');
    }

    /* ---------------------------------------------------------------- */
    /* AI translation                                                   */
    /* ---------------------------------------------------------------- */

    /** The locale the translate button writes into (the one not being edited). */
    #[Computed]
    public function translateTarget(): string
    {
        $others = array_values(array_diff(array_keys($this->locales()), [$this->selectedLocale]));

        return $others[0] ?? $this->selectedLocale;
    }

    /**
     * Translate the question & answer from the locale currently being
     * edited into the other locale. Confirms first if that locale already
     * has content the translation would overwrite.
     */
    public function translate(): void
    {
        $this->storeFieldsToTranslations();

        if (trim((string) ($this->translations[$this->selectedLocale]['question'] ?? '')) === '') {
            Flux::toast(__('Add a question first, then translate.'), variant: 'warning');

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

        try {
            $translated = app(FaqTranslator::class)->translate(
                $this->translations[$source],
                $source,
                $target,
                htmlFields: ['answer'],
            );
        } catch (\Throwable $e) {
            Log::error('FAQ item translation failed', ['error' => $e->getMessage()]);
            Flux::toast(__('Translation failed. Please try again.'), variant: 'danger');

            return;
        }

        foreach ($this->translatableFields() as $field) {
            if (isset($translated[$field])) {
                $this->translations[$target][$field] = $translated[$field];
            }
        }

        // Switch to the target locale so the editor shows the result.
        $this->loadFieldsFromTranslations($target);
        $this->selectedLocale = $target;
        app()->setLocale($target);
        unset($this->translateTarget);
        $this->dispatch('wysiwyg-reload');

        Flux::toast(
            __('Translated to :locale.', ['locale' => $this->locales()[$target] ?? $target]),
            variant: 'success',
        );
    }

    protected function translateTargetHasContent(): bool
    {
        $buffer = $this->translations[$this->translateTarget] ?? [];

        foreach ($this->translatableFields() as $field) {
            if (trim(strip_tags((string) ($buffer[$field] ?? ''))) !== '') {
                return true;
            }
        }

        return false;
    }

    public function save()
    {
        $this->storeFieldsToTranslations();

        $this->validate([
            'translations.'.$this->mainLocale().'.question' => 'required|string|max:500',
        ], [], [
            'translations.'.$this->mainLocale().'.question' => __('question'),
        ]);

        $oldWysiwygIds = [];
        if ($this->faqItem && $this->faqItem->exists) {
            $oldWysiwygIds = $this->extractWysiwygMediaIds(
                ...array_column($this->translations, 'answer')
            );
        }

        $mainData = $this->translations[$this->mainLocale()];

        if (! $this->faqItem) {
            $this->faqItem = FaqItem::create($mainData);
            Flux::toast(__('FAQ item created successfully.'), variant: 'success');
        } else {
            $this->faqItem->update($mainData);
            Flux::toast(__('FAQ item updated successfully.'), variant: 'success');
        }

        foreach ($this->otherLocales() as $locale) {
            $this->syncTranslation($this->faqItem, $this->translations[$locale], $locale);
        }

        $this->cleanupRemovedWysiwygMedia($oldWysiwygIds);

        $this->redirect(route('faq-items.index'), navigate: true);
    }

    protected function wysiwygFieldValues(): array
    {
        return array_column($this->translations, 'answer');
    }

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

    protected function syncTranslation(FaqItem $model, array $payload, string $locale): void
    {
        $translation = Translation::findByModel($model, $locale);

        if ($translation) {
            $translation->update([
                'name' => $payload['question'] ?? null,
                'slug' => null,
                'fields' => Arr::except($payload, ['name', 'slug']),
            ]);

            return;
        }

        Translation::createForModel($model, $locale, array_merge($payload, [
            'name' => $payload['question'] ?? null,
        ]));
    }

    protected function translatableFields(): array
    {
        return FaqItem::TRANSLATABLE_FIELDS;
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
};
?>

<div>
    <form wire:submit="save" class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ $faqItem ? __('Edit FAQ Item') : __('Create FAQ Item') }}</flux:heading>
                <flux:subheading>
                    {{ __('A reusable question & answer for any FAQ page.') }}
                </flux:subheading>
            </div>
            <div class="flex space-x-2">
                <flux:button variant="ghost" href="{{ route('faq-items.index') }}" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </div>

        <flux:card class="space-y-6">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Question & Answer') }}</flux:heading>
                    <flux:subheading>{{ __('Editing the') }}
                        <span class="font-medium">{{ config('app.available_locales')[$selectedLocale] ?? $selectedLocale }}</span>
                        {{ __('version.') }}</flux:subheading>
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
                </div>
            </div>

            <flux:input wire:model="question" label="{{ __('Question') }}"
                placeholder="{{ __('e.g. How do I install the printer driver?') }}" />

            <x-wysiwyg-editor wire:model="answer" :defer-delete="$faqItem !== null"
                label="{{ __('Answer') }}"
                placeholder="{{ __('Write the answer — supports links, lists, images and formatting...') }}" />
        </flux:card>
    </form>

    {{-- Confirm before an AI translation overwrites the other locale --}}
    <flux:modal wire:model.self="showTranslateConfirm" class="md:w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Replace existing translation?') }}</flux:heading>
                <flux:subheading>
                    {{ __('The :locale version already has content. Translating will overwrite it.', ['locale' => config('app.available_locales')[$this->translateTarget] ?? $this->translateTarget]) }}
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

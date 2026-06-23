<?php

use App\Models\AiSetting;
use App\Services\ProductContentGenerator;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public string $selectedScope = AiSetting::SCOPE_PRODUCT;

    /**
     * Per-scope state, keyed by scope code. Shape per scope:
     * [
     *   'settingId' => int|null,
     *   'lang' => ['en' => [...], 'nl' => [...]],
     *   'default_fields_en' => [],
     *   'default_fields_nl' => [],
     *   'default_language' => 'en',
     *   'auto_detect_locale' => true,
     * ]
     */
    public array $scopes = [];

    public string $selectedLocale = 'en';

    public string $preview_locale = 'en';

    public function booted(): void
    {
        // Re-apply locale on every Livewire request so all __() calls render correctly.
        app()->setLocale($this->selectedLocale);
    }

    public function mount(): void
    {
        $this->selectedLocale = config('app.main_locale', 'en');
        $this->preview_locale = $this->selectedLocale;

        foreach (array_keys(AiSetting::SCOPES) as $scope) {
            $this->scopes[$scope] = $this->loadScopeState($scope);
        }

        app()->setLocale($this->selectedLocale);
    }

    protected function loadScopeState(string $scope): array
    {
        $setting = AiSetting::current($scope);

        $lang = [];
        foreach (AiSetting::SUPPORTED_LOCALES as $locale) {
            $lang[$locale] = $setting->settingsForLocale($locale);
        }

        return [
            'settingId' => $setting->id,
            'lang' => $lang,
            'default_fields_en' => (array) ($setting->default_fields_en ?? []),
            'default_fields_nl' => (array) ($setting->default_fields_nl ?? []),
            'default_language' => (string) ($setting->default_language ?? 'en'),
            'auto_detect_locale' => (bool) $setting->auto_detect_locale,
        ];
    }

    public function switchScope(string $scope): void
    {
        if (! array_key_exists($scope, AiSetting::SCOPES) || $scope === $this->selectedScope) {
            return;
        }

        $this->selectedScope = $scope;
    }

    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, AiSetting::SUPPORTED_LOCALES, true) || $locale === $this->selectedLocale) {
            return;
        }

        $this->selectedLocale = $locale;
        $this->preview_locale = $locale;
        app()->setLocale($locale);
    }

    public function save(): void
    {
        $scope = $this->selectedScope;
        $toneValues = implode(',', array_keys(AiSetting::TONES));

        $rules = [
            "scopes.{$scope}.default_fields_en" => 'array',
            "scopes.{$scope}.default_fields_nl" => 'array',
            "scopes.{$scope}.default_language" => 'required|string|in:' . implode(',', AiSetting::SUPPORTED_LOCALES),
            "scopes.{$scope}.auto_detect_locale" => 'boolean',
        ];

        foreach (AiSetting::SUPPORTED_LOCALES as $locale) {
            $rules["scopes.{$scope}.lang.{$locale}.role_description"] = 'nullable|string|max:5000';
            $rules["scopes.{$scope}.lang.{$locale}.taxonomy_guidelines"] = 'nullable|string|max:5000';
            $rules["scopes.{$scope}.lang.{$locale}.tone"] = "required|string|in:{$toneValues}";
            $rules["scopes.{$scope}.lang.{$locale}.style_notes"] = 'nullable|string|max:5000';
            $rules["scopes.{$scope}.lang.{$locale}.wc_excerpt"] = 'required|integer|min:1|max:2000';
            $rules["scopes.{$scope}.lang.{$locale}.wc_short_description"] = 'required|integer|min:1|max:2000';
            $rules["scopes.{$scope}.lang.{$locale}.wc_content"] = 'required|integer|min:1|max:5000';
            $rules["scopes.{$scope}.lang.{$locale}.wc_product_information"] = 'required|integer|min:1|max:5000';
        }

        $this->validate($rules);

        $allowed = AiSetting::generatableFieldsForScope($scope);
        $state = $this->scopes[$scope];

        AiSetting::query()->updateOrCreate(
            ['id' => $state['settingId'], 'scope' => $scope],
            [
                'scope' => $scope,
                'language_settings' => $state['lang'],
                'default_fields_en' => array_values(array_intersect($state['default_fields_en'], $allowed)),
                'default_fields_nl' => array_values(array_intersect($state['default_fields_nl'], $allowed)),
                'default_language' => $state['default_language'],
                'auto_detect_locale' => $state['auto_detect_locale'],
            ]
        );

        // Refresh the stored setting id (in case we just created the row)
        $this->scopes[$scope] = $this->loadScopeState($scope);

        Flux::toast(__('AI settings saved.'), variant: 'success');
    }

    public function resetToDefaults(): void
    {
        $scope = $this->selectedScope;
        $localeDefaults = AiSetting::defaultLocaleSettings();

        $lang = [];
        foreach (AiSetting::SUPPORTED_LOCALES as $locale) {
            $lang[$locale] = $localeDefaults;
        }

        $this->scopes[$scope]['lang'] = $lang;
        $this->scopes[$scope]['default_fields_en'] = [];
        $this->scopes[$scope]['default_fields_nl'] = [];
        $this->scopes[$scope]['default_language'] = 'en';
        $this->scopes[$scope]['auto_detect_locale'] = true;

        Flux::toast(__('AI settings reverted to defaults. Click Save to persist.'), variant: 'success');
    }

    public function previewParts(): array
    {
        $scope = $this->selectedScope;
        $state = $this->scopes[$scope] ?? null;
        $localeConfig = ($state['lang'][$this->preview_locale] ?? null) ?: AiSetting::defaultLocaleSettings();

        $transient = new AiSetting([
            'scope' => $scope,
            'language_settings' => [
                $this->preview_locale => $localeConfig,
            ],
        ]);

        $generator = app(ProductContentGenerator::class);

        $isNl = $this->preview_locale === 'nl';

        $base = $generator->getBasePromptForPreview($this->preview_locale, $scope);
        $runtime = $generator->buildRuntimeInstructionsForPreview($transient, $this->preview_locale, $scope);
        $full = $base . "\n\n" . $runtime;

        $promptFilename = $scope === AiSetting::SCOPE_GROUP_PRODUCT ? 'group-product-content' : 'product-content';
        $promptPath = 'resources/prompts/' . $promptFilename . ($this->preview_locale !== 'en' ? '.' . $this->preview_locale : '') . '.md';

        return [
            'base' => $base,
            'runtime' => $runtime,
            'full' => $full,
            'characters' => mb_strlen($full),
            'tokens' => (int) ceil(mb_strlen($full) / 4),
            'prompt_path' => $promptPath,
            'label_base' => $isNl ? 'Basis-prompt · vast' : 'Base prompt · fixed',
            'label_runtime' => $isNl ? 'Runtime instructies · van uw instellingen' : 'Runtime instructions · from your settings',
            'label_updates' => $isNl ? 'wordt bijgewerkt terwijl u typt' : 'updates as you type',
            'label_show' => $isNl ? 'Gecombineerde prompt weergeven (wat de AI ziet)' : 'Show combined prompt (what the AI actually sees)',
            'label_hide' => $isNl ? 'Gecombineerde prompt verbergen' : 'Hide combined prompt',
        ];
    }

    public function fieldOptions(): array
    {
        $labels = [
            'title' => __('Title'),
            'slug' => __('Slug'),
            'subtitle' => __('Subtitle'),
            'excerpt' => __('Excerpt'),
            'short_description' => __('Short Description'),
            'content' => __('Content'),
            'product_information' => __('Product Information'),
            'meta_title' => __('Meta Title'),
            'meta_description' => __('Meta Description'),
        ];

        return array_intersect_key($labels, array_flip(AiSetting::generatableFieldsForScope($this->selectedScope)));
    }

    public function scopeLabel(string $scope): string
    {
        return match ($scope) {
            AiSetting::SCOPE_GROUP_PRODUCT => __('Group Products'),
            default => __('Products'),
        };
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('AI Content Settings') }}</flux:heading>
            <flux:subheading size="lg">
                {{ __('Tune how generated content sounds, how long it should be, and which fields to pre-select. Each scope keeps its own prompt configuration.') }}
            </flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            {{-- Locale switcher --}}
            <div class="flex items-center gap-1">
                @foreach (config('app.available_locales') as $localeCode => $localeName)
                    <button type="button" wire:click="switchLocale('{{ $localeCode }}')"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-sm font-medium transition-colors {{ $selectedLocale === $localeCode ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700' }}">
                        <span class="text-base leading-none">{{ $localeCode === 'en' ? '🇬🇧' : '🇳🇱' }}</span>
                    </button>
                @endforeach
            </div>

            <flux:button type="button" variant="ghost" x-on:click="$flux.modal('reset-ai-defaults').show()">
                {{ __('Reset to defaults') }}
            </flux:button>
            <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving...') }}</span>
            </flux:button>
        </div>
    </div>

    {{-- Scope switcher --}}
    <div class="mb-6 flex items-center gap-2">
        @foreach (\App\Models\AiSetting::SCOPES as $scopeCode => $scopeLabel)
            <button type="button" wire:click="switchScope('{{ $scopeCode }}')"
                class="px-4 py-2 rounded-md text-sm font-medium transition-colors {{ $selectedScope === $scopeCode ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}">
                {{ $this->scopeLabel($scopeCode) }}
            </button>
        @endforeach
    </div>

    <form wire:submit.prevent="save" class="grid grid-cols-1 md:grid-cols-3 gap-6 md:items-start">
        <div class="md:col-span-2 space-y-6">

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Identity & Expertise') }}</flux:heading>
                    <flux:subheading>{{ __('Describe the role the AI should play and any taxonomy rules.') }}</flux:subheading>
                </div>

                <flux:textarea
                    wire:key="role-{{ $selectedScope }}-{{ $selectedLocale }}"
                    wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.role_description"
                    label="{{ __('Role / persona description') }}"
                    placeholder="{{ __('You are an expert product content writer.') }}"
                    rows="3" />

                <flux:textarea
                    wire:key="taxonomy-{{ $selectedScope }}-{{ $selectedLocale }}"
                    wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.taxonomy_guidelines"
                    label="{{ __('Taxonomy guidelines') }}"
                    placeholder="{{ __('e.g. Always use B2B language. Avoid consumer-retail buzzwords.') }}"
                    rows="4" />
            </flux:card>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Tone & Style') }}</flux:heading>
                    <flux:subheading>{{ __('Voice and writing style applied to every generation.') }}</flux:subheading>
                </div>

                <flux:select wire:key="tone-{{ $selectedScope }}-{{ $selectedLocale }}"
                    wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.tone" label="{{ __('Tone') }}">
                    @foreach (\App\Models\AiSetting::TONES as $val => $label)
                        <option value="{{ $val }}">{{ __($label) }}</option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    wire:key="style-{{ $selectedScope }}-{{ $selectedLocale }}"
                    wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.style_notes"
                    label="{{ __('Writing style notes') }}"
                    placeholder="{{ __('e.g. Prefer short sentences. Use second person. Avoid exclamation marks.') }}"
                    rows="4" />
            </flux:card>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Word Count Targets') }}</flux:heading>
                    <flux:subheading>{{ __('Target lengths the model should aim for per field.') }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:input
                        wire:key="wc-excerpt-{{ $selectedScope }}-{{ $selectedLocale }}"
                        wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.wc_excerpt"
                        type="number" min="1" max="2000"
                        label="{{ __('Excerpt') }}" />
                    <flux:input
                        wire:key="wc-short-{{ $selectedScope }}-{{ $selectedLocale }}"
                        wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.wc_short_description"
                        type="number" min="1" max="2000"
                        label="{{ __('Short Description') }}" />
                    <flux:input
                        wire:key="wc-content-{{ $selectedScope }}-{{ $selectedLocale }}"
                        wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.wc_content"
                        type="number" min="1" max="5000"
                        label="{{ __('Content') }}" />
                    @if ($selectedScope !== \App\Models\AiSetting::SCOPE_GROUP_PRODUCT)
                        <flux:input
                            wire:key="wc-pi-{{ $selectedScope }}-{{ $selectedLocale }}"
                            wire:model.blur="scopes.{{ $selectedScope }}.lang.{{ $selectedLocale }}.wc_product_information"
                            type="number" min="1" max="5000"
                            label="{{ __('Product Information') }}" />
                    @endif
                </div>
            </flux:card>

        </div>

        <div class="space-y-6 sticky top-6">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Default Field Selection') }}</flux:heading>
                    <flux:subheading>{{ __('Fields pre-checked when the generate modal opens.') }}</flux:subheading>
                </div>

                <flux:checkbox.group wire:key="default-fields-en-{{ $selectedScope }}" label="{{ __('Default EN fields') }}">
                    @foreach ($this->fieldOptions() as $val => $label)
                        <flux:checkbox wire:key="default-fields-en-{{ $selectedScope }}-{{ $val }}"
                            wire:model="scopes.{{ $selectedScope }}.default_fields_en" value="{{ $val }}"
                            label="{{ $label }}" />
                    @endforeach
                </flux:checkbox.group>

                <flux:checkbox.group wire:key="default-fields-nl-{{ $selectedScope }}" label="{{ __('Default NL fields') }}">
                    @foreach ($this->fieldOptions() as $val => $label)
                        <flux:checkbox wire:key="default-fields-nl-{{ $selectedScope }}-{{ $val }}"
                            wire:model="scopes.{{ $selectedScope }}.default_fields_nl" value="{{ $val }}"
                            label="{{ $label }}" />
                    @endforeach
                </flux:checkbox.group>
            </flux:card>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Language Defaults') }}</flux:heading>
                </div>

                <flux:select wire:key="default-language-{{ $selectedScope }}"
                    wire:model="scopes.{{ $selectedScope }}.default_language" label="{{ __('Default language') }}">
                    @foreach (\App\Models\AiSetting::SUPPORTED_LOCALES as $locale)
                        <option value="{{ $locale }}">
                            {{ $locale === 'en' ? __('English') : __('Dutch') }}
                        </option>
                    @endforeach
                </flux:select>

                <flux:checkbox wire:key="auto-detect-{{ $selectedScope }}"
                    wire:model="scopes.{{ $selectedScope }}.auto_detect_locale"
                    label="{{ __('Auto-detect from active locale tab') }}" />
            </flux:card>
        </div>
    </form>

    @php($preview = $this->previewParts())

    <flux:card class="mt-6 space-y-4">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Prompt Preview') }}</flux:heading>
                <flux:subheading>
                    {{ __('Live preview of the system prompt the AI receives. The base prompt is loaded from') }}
                    <code
                        class="text-xs px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800">{{ $preview['prompt_path'] }}</code>
                    {{ __('and the runtime block is built from your settings above.') }}
                </flux:subheading>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <flux:badge color="violet" size="lg">{{ $this->scopeLabel($selectedScope) }}</flux:badge>
                <flux:badge color="sky" size="lg">{{ strtoupper($preview_locale) }}</flux:badge>
                <flux:button type="button" size="sm" variant="ghost" icon="clipboard-document"
                    x-data="{ copied: false }"
                    x-on:click="
                        navigator.clipboard.writeText($refs.promptFull.innerText);
                        copied = true;
                        setTimeout(() => copied = false, 1500);
                    ">
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                </flux:button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <flux:badge color="zinc">
                {{ number_format($preview['characters']) }} {{ __('characters') }}
            </flux:badge>
            <flux:badge color="zinc">
                ~{{ number_format($preview['tokens']) }} {{ __('tokens') }}
            </flux:badge>
        </div>

        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div
                class="flex items-center justify-between px-4 py-2 bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-zinc-400"></span>
                    <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300 uppercase tracking-wide">
                        {{ $preview['label_base'] }}
                    </span>
                </div>
                <span class="text-[11px] text-zinc-500">{{ $preview['prompt_path'] }}</span>
            </div>
            <pre
                class="px-4 py-3 text-xs leading-relaxed font-mono whitespace-pre-wrap wrap-break-word text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 max-h-72 overflow-auto">{{ $preview['base'] }}</pre>
        </div>

        <div class="rounded-lg border border-sky-300 dark:border-sky-700 overflow-hidden">
            <div
                class="flex items-center justify-between px-4 py-2 bg-sky-50 dark:bg-sky-900/30 border-b border-sky-200 dark:border-sky-800">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-sky-500"></span>
                    <span class="text-xs font-medium text-sky-700 dark:text-sky-300 uppercase tracking-wide">
                        {{ $preview['label_runtime'] }}
                    </span>
                </div>
                <span class="text-[11px] text-sky-600 dark:text-sky-400">{{ $preview['label_updates'] }}</span>
            </div>
            <pre
                class="px-4 py-3 text-xs leading-relaxed font-mono whitespace-pre-wrap wrap-break-word text-zinc-800 dark:text-zinc-200 bg-sky-50/40 dark:bg-sky-950/30 max-h-96 overflow-auto">{{ $preview['runtime'] }}</pre>
        </div>

        <div x-data="{ open: false }" class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
            <button type="button" x-on:click="open = !open"
                class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100">
                <flux:icon name="chevron-right" class="w-4 h-4 transition-transform"
                    x-bind:class="open && 'rotate-90'" />
                <span
                    x-text="open ? '{{ $preview['label_hide'] }}' : '{{ $preview['label_show'] }}'"></span>
            </button>
            <pre x-show="open" x-cloak x-ref="promptFull"
                class="mt-3 px-4 py-3 text-xs leading-relaxed font-mono whitespace-pre-wrap wrap-break-word text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 max-h-128 overflow-auto">{{ $preview['full'] }}</pre>
            <pre x-ref="promptFull" x-show="false" class="hidden">{{ $preview['full'] }}</pre>
        </div>
    </flux:card>

    <flux:modal name="reset-ai-defaults" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Reset to defaults?') }}</flux:heading>
                <flux:subheading>
                    {{ __('AI settings for the current scope will revert to factory defaults. You will still need to click Save to persist them.') }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('reset-ai-defaults').close()">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="button" variant="primary"
                    x-on:click="$wire.resetToDefaults(); $flux.modal('reset-ai-defaults').close()">
                    {{ __('Reset') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

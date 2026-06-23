<?php

use App\Jobs\ExportPrintersJob;
use App\Models\Export;
use App\Models\Post;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public ?Export $activeExport = null;

    public bool $showExportModal = false;

    public ?array $pendingExportIds = null;

    public bool $pendingExportAll = false;

    public string $exportLocale = 'en';

    public string $exportHeadingLocale = 'en';

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

        $query = Post::where('post_type', 'printer');

        if (!$all && $ids !== null) {
            $query->whereIn('id', $ids);
        }

        if ($query->count() === 0) {
            Flux::toast(__('No printers to export.'), variant: 'warning');
            return;
        }

        $filters = $all ? ['locale' => $locale, 'heading_locale' => $headingLocale] : ['ids' => $ids, 'locale' => $locale, 'heading_locale' => $headingLocale];

        $export = Export::create([
            'user_id' => auth()->id(),
            'type' => 'printers',
            'status' => 'pending',
            'filters' => $filters,
        ]);

        ExportPrintersJob::dispatch($export);

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
            ->where('type', 'printers')
            ->whereIn('status', ['pending', 'processing', 'completed', 'failed'])
            ->latest()
            ->first();

        // Auto-clear old completed/failed exports (older than 1 hour)
        if ($this->activeExport && !$this->activeExport->isInProgress() && $this->activeExport->updated_at->lt(now()->subHour())) {
            $this->activeExport->delete();
            $this->activeExport = null;
        }
    }
};
?>

<div @if ($activeExport && $activeExport->isInProgress()) wire:poll.2s="pollExport" @endif>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Printers') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">{{ __('Manage your printers.') }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:modal.trigger name="printer-import-modal">
                <flux:button icon="document-arrow-down" />
            </flux:modal.trigger>
            <flux:button variant="primary" icon="plus" href="{{ route('printers.create') }}" wire:navigate>
                {{ __('New Printer') }}
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
                                {{ __('Exporting printers...') }}
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

    <livewire:printer-table />

    <livewire:printers.import-modal />

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
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
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
                <flux:button type="button" variant="ghost" wire:click="$set('showExportModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="primary" wire:click="confirmExport">{{ __('Export') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

<div>
    @php
        $totalCount = $this->totalRecordCount();
        $totalSelected = count($this->checkboxValues);
        $entityLabel = __($this->entityLabel);
    @endphp
    @if (!empty($this->checkboxValues) && $this->checkboxAll && !$this->selectAllRecords)
        @if ($totalCount > $totalSelected)
            <div
                class="mb-2 flex items-center justify-center gap-2 py-2 px-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md text-sm text-blue-700 dark:text-blue-300">
                <span>{{ __('All :count :entity on this page are selected.', ['count' => $totalSelected, 'entity' => $entityLabel]) }}</span>
                <button wire:click="toggleSelectAll" class="font-semibold underline hover:no-underline">
                    {{ __('Select all :total :entity', ['total' => $totalCount, 'entity' => $entityLabel]) }}
                </button>
            </div>
        @endif
    @elseif($this->selectAllRecords)
        <div
            class="mb-2 flex items-center justify-center gap-2 py-2 px-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md text-sm text-blue-700 dark:text-blue-300">
            <span>{{ __('All :total :entity are selected.', ['total' => $totalCount, 'entity' => $entityLabel]) }}</span>
            <button wire:click="toggleSelectAll" class="font-semibold underline hover:no-underline">
                {{ __('Clear selection') }}
            </button>
        </div>
    @endif
</div>

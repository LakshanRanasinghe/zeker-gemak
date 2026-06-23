<?php

use App\Imports\MappedProductImport;
use App\Models\Product;
use App\Models\ProductMeta;
use App\Support\CatalogMetaFilters;
use Flux\Flux;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

new class extends Component {
    use WithFileUploads;

    public $file;

    public $headers = [];

    public $mapping = [];

    public $dbColumns = [];

    protected $ignoredColumns = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function mount()
    {
        $this->dbColumns = [];

        // Dynamically load all columns from the products table
        $productColumns = Schema::getColumnListing((new Product)->getTable());
        foreach ($productColumns as $column) {
            if (in_array($column, $this->ignoredColumns)) {
                continue;
            }

            $this->dbColumns[$column] = Str::headline($column);
        }

        // Dynamically load all possible meta keys from filters and DB
        $metaKeysFromConfig = array_keys(CatalogMetaFilters::definitions());
        $metaKeysFromDb = ProductMeta::select('meta_key')->distinct()->pluck('meta_key')->toArray();
        $allMetaKeys = array_unique(array_merge($metaKeysFromConfig, $metaKeysFromDb));

        foreach ($allMetaKeys as $key) {
            $def = CatalogMetaFilters::definitions()[$key] ?? null;
            $label = $def ? $def['label'] : Str::headline($key);
            $this->dbColumns['meta:'.$key] = 'Meta: '.$label;
        }
    }

    public function updatedFile()
    {
        $this->headers = [];
        $this->mapping = [];

        if ($this->file) {
            try {
                // Disable formatting to get raw headers from the file
                HeadingRowFormatter::default('none');
                $rows = (new HeadingRowImport)->toArray($this->file->getRealPath());

                if (isset($rows[0][0]) && is_array($rows[0][0])) {
                    $hasSku = false;

                    foreach ($rows[0][0] as $header) {
                        if (filled($header)) {
                            $normalized = Str::slug((string) $header, '_');

                            if ($normalized === 'sku') {
                                $hasSku = true;
                            }

                            if (! in_array(Str::lower($header), $this->ignoredColumns)) {
                                $this->headers[] = [
                                    'raw' => (string) $header,
                                    'normalized' => $normalized,
                                ];
                            }
                        }
                    }

                    if (! $hasSku) {
                        $this->reset(['file', 'headers', 'mapping']);
                        Flux::toast('The imported file must contain a SKU column.', variant: 'danger');

                        return;
                    }
                }
            } catch (\Exception $e) {
                Flux::toast('Failed to read file headers.', variant: 'danger');
            }
        }
    }

    public function import()
    {
        $this->validate([
            'file' => 'required|mimes:csv,xlsx,xls',
        ]);

        $mappedIndices = array_filter($this->mapping);

        if (empty($mappedIndices)) {
            Flux::toast('Please map at least one column.', variant: 'danger');

            return;
        }

        // Convert index-based mapping back to raw-header-based mapping for the Import class
        $finalMapping = [];
        foreach ($mappedIndices as $index => $dbColumn) {
            if (isset($this->headers[$index])) {
                $finalMapping[$this->headers[$index]['raw']] = $dbColumn;
            }
        }

        if (! in_array('sku', $finalMapping)) {
            Flux::toast('The SKU column must be mapped for updating existing products.', variant: 'danger');

            return;
        }

        try {
            // Keep formatting disabled for the actual import to match the raw headers
            HeadingRowFormatter::default('none');
            Excel::import(new MappedProductImport($finalMapping), $this->file->getRealPath());

            Flux::toast('Products imported successfully.', variant: 'success');

            $this->dispatch('pg:eventRefresh-products');
            $this->dispatch('close-modal', 'product-import-modal');

            $this->resetForm();
        } catch (\Exception $e) {
            Flux::toast('Import failed: '.$e->getMessage(), variant: 'danger');
            Log::info($e->getMessage());
        }
    }

    public function resetForm()
    {
        $this->reset(['file', 'headers', 'mapping']);
    }
}; ?>

<flux:modal name="product-import-modal" class="w-full flex flex-col gap-6" wire:ignore.self x-on:close="$wire.resetForm()">
    <div>
        <flux:heading size="lg">Import Products</flux:heading>
        <flux:text class="mt-2">Map the columns and import properly.</flux:text>
    </div>

    <div class="flex flex-col gap-4">
        <flux:file-upload wire:model.live="file" label="Upload files" accept=".csv,.xlsx,.xls">
            <flux:file-upload.dropzone heading="Drop files here or click to browse" text="CSV, XLSX up to 10MB" />
        </flux:file-upload>

        @if($file)
            <flux:file-item heading="{{ $file->getClientOriginalName() }}">
                <x-slot name="actions">
                    <flux:file-item.remove wire:click="$set('file', null)" />
                </x-slot>
            </flux:file-item>
        @endif
    </div>

    @if(!empty($headers))
        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between gap-3 pb-1">
                <flux:heading size="lg">Map Columns</flux:heading>
                <flux:badge variant="info">{{ count($headers) }} Columns Identified</flux:badge>
            </div>
            <div class="flex flex-col gap-4 max-h-[30vh] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                @php
                    $assignedColumns = collect($mapping)->filter()->values()->all();
                @endphp
                @foreach($headers as $index => $header)
                    @php
                        $currentValue = $mapping[$index] ?? null;
                        $takenByOthers = array_values(array_filter($assignedColumns, fn($v) => $v !== $currentValue));
                    @endphp
                    <flux:card class="grid grid-cols-2 gap-2 !p-2 items-center justify-center">
                        <div class="flex flex-col truncate pl-3">
                            <span class="text-sm font-medium truncate" title="{{ $header['raw'] }}">{{ $header['raw'] }}</span>
                            <span class="text-xs text-gray-400 font-mono truncate">{{ $header['normalized'] }}</span>
                        </div>
                        <flux:select wire:model.live="mapping.{{ $index }}" variant="listbox" searchable placeholder="Database Column" clearable>
                            @foreach($dbColumns as $value => $label)
                                @php $isDisabled = in_array($value, $takenByOthers); @endphp
                                <flux:select.option value="{{ $value }}" :disabled="$isDisabled">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex justify-start">
        <flux:button variant="primary" class="mt-2" wire:click="import">Import</flux:button>
    </div>
</flux:modal>

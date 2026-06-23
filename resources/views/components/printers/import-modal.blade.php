<?php

use App\Imports\MappedPostImport;
use App\Models\PostMeta;
use Flux\Flux;
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

    public function mount()
    {
        $this->dbColumns = [];

        // Post table columns relevant for printers
        $postColumns = ['title', 'content', 'excerpt', 'slug', 'status', 'template'];
        foreach ($postColumns as $column) {
            $this->dbColumns[$column] = Str::headline($column);
        }

        // Printer meta keys from the create-update form
        $printerMetaKeys = [
            'subtitle' => 'Subtitle',
            'kern' => 'Kern',
            'label_breedte' => 'Label Breedte',
            'label_type' => 'Label Type',
            'max_buiten_diameter' => 'Max Buiten Diameter',
            'width' => 'Width',
            'druktype' => 'Druktype',
            'buiten_diameter' => 'Buiten Diameter',
            'detectie' => 'Detectie',
            'featured' => 'Featured',
            'printer_url' => 'Printer URL',
        ];

        // Also pull any additional meta keys from DB
        $dbMetaKeys = PostMeta::whereHas('post', function ($q) {
            $q->where('post_type', 'printer');
        })->select('meta_key')->distinct()->pluck('meta_key')->toArray();

        $allMetaKeys = array_unique(array_merge(array_keys($printerMetaKeys), $dbMetaKeys));

        foreach ($allMetaKeys as $key) {
            $label = $printerMetaKeys[$key] ?? Str::headline($key);
            $this->dbColumns['meta:'.$key] = 'Meta: '.$label;
        }
    }

    public function updatedFile()
    {
        $this->headers = [];
        $this->mapping = [];

        if ($this->file) {
            try {
                HeadingRowFormatter::default('none');
                $rows = (new HeadingRowImport)->toArray($this->file->getRealPath());

                if (isset($rows[0][0]) && is_array($rows[0][0])) {
                    foreach ($rows[0][0] as $header) {
                        if (filled($header)) {
                            $this->headers[] = [
                                'raw' => (string) $header,
                                'normalized' => Str::slug((string) $header, '_'),
                            ];
                        }
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

        $finalMapping = [];
        foreach ($mappedIndices as $index => $dbColumn) {
            if (isset($this->headers[$index])) {
                $finalMapping[$this->headers[$index]['raw']] = $dbColumn;
            }
        }

        if (! in_array('slug', $finalMapping)) {
            Flux::toast('The Slug column must be mapped for updating existing printers.', variant: 'danger');

            return;
        }

        try {
            HeadingRowFormatter::default('none');
            Excel::import(new MappedPostImport($finalMapping, 'printer'), $this->file->getRealPath());

            Flux::toast('Printers imported successfully.', variant: 'success');
            $this->reset(['file', 'headers', 'mapping']);

            $this->dispatch('pg:eventRefresh-printers-table');
            $this->dispatch('close-modal', 'printer-import-modal');
        } catch (\Exception $e) {
            Flux::toast('Import failed: '.$e->getMessage(), variant: 'danger');
        }
    }
}; ?>

<flux:modal name="printer-import-modal" class="w-full flex flex-col gap-6" wire:ignore.self>
    <div>
        <flux:heading size="lg">Import Printers</flux:heading>
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

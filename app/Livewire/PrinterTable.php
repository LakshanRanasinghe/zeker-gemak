<?php

namespace App\Livewire;

use App\Models\Post;
use Flux\Flux;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class PrinterTable extends PowerGridComponent
{
    public string $tableName = 'printers-table';

    public bool $selectAllRecords = false;

    public string $entityLabel = 'printers';

    public function totalRecordCount(): int
    {
        return Post::where('post_type', 'printer')->count();
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput()
                ->includeViewOnTop('components.shared.select-all-banner'),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        $locale = app()->getLocale();

        return DB::table('posts')
            ->where('post_type', 'printer')
            ->leftJoin('translations as t', function ($join) use ($locale) {
                $join->on('t.translatable_id', '=', 'posts.id')
                    ->where('t.translatable_type', '=', 'App\Models\Post')
                    ->where('t.language', '=', $locale);
            })
            ->leftJoin(DB::raw("(
                SELECT m.model_id, CONCAT('/storage/', m.id, '/', m.file_name) as image_url
                FROM media m
                INNER JOIN (
                    SELECT model_id, MIN(id) as min_id
                    FROM media
                    WHERE model_type = 'App\\\\Models\\\\Post' AND collection_name = 'main'
                    GROUP BY model_id
                ) fm ON m.id = fm.min_id
            ) printer_media"), 'printer_media.model_id', '=', 'posts.id')
            ->selectRaw("
                posts.id,
                COALESCE(t.name, posts.title) as title,
                COALESCE(t.slug, posts.slug) as slug,
                printer_media.image_url as main_image,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.druktype')), (
                    SELECT GROUP_CONCAT(pv.value ORDER BY pv.value SEPARATOR ', ')
                    FROM model_property_values mpv
                    INNER JOIN property_values pv ON pv.id = mpv.property_value_id
                    INNER JOIN properties p ON p.id = pv.property_id
                    WHERE mpv.model_type = 'App\\\\Models\\\\Post'
                        AND mpv.model_id = posts.id
                        AND p.slug = 'printmethode'
                )) as druktype,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.width')), (
                    SELECT GROUP_CONCAT(pv.value ORDER BY CAST(REPLACE(pv.value, ',', '.') AS DECIMAL(12,4)) SEPARATOR ', ')
                    FROM model_property_values mpv
                    INNER JOIN property_values pv ON pv.id = mpv.property_value_id
                    INNER JOIN properties p ON p.id = pv.property_id
                    WHERE mpv.model_type = 'App\\\\Models\\\\Post'
                        AND mpv.model_id = posts.id
                        AND p.slug = 'breedte'
                )) as width,
                posts.author,
                posts.post_type,
                posts.status,
                posts.created_at
            ");
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('main_image', fn ($row) => $row->main_image
                ? '<img src="'.e($row->main_image).'" class="w-10 h-10 rounded-md object-cover" />'
                : '<span class="inline-block w-10 h-10 rounded-md bg-zinc-100 dark:bg-zinc-800"></span>')
            ->add('title')
            ->add('slug')
            // ->add('druktype', fn($row) => is_array(json_decode($row->druktype)) ? implode(', ', json_decode($row->druktype)) : $row->druktype)
            // ->add('width', fn($row) => is_array(json_decode($row->width)) ? implode(', ', json_decode($row->width)) : $row->width)
            ->add('post_type', fn ($row) => ucfirst($row->post_type))
            ->add('status', fn ($row) => ucfirst($row->status))
            ->add('created_at', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->searchable()
                ->sortable()->hidden(),
            Column::make(__('Product'), 'main_image'),
            Column::make(__('Title'), 'title')
                ->sortable()
                ->searchable(),

            Column::make(__('Slug'), 'slug')
                ->searchable(),

            // Column::make(__('Druktype'), 'druktype')
            //     ->sortable(),

            // Column::make(__('Width'), 'width'),

            Column::make(__('Status'), 'status')
                ->sortable(),

            Column::make(__('Created at'), 'created_at')
                ->sortable()->hidden(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource([
                    ['name' => __('Published'), 'value' => 'published'],
                    ['name' => __('Draft'), 'value' => 'draft'],
                ])
                ->optionValue('value')
                ->optionLabel('name'),
        ];
    }

    public function header(): array
    {
        $buttons = [];

        if ($this->hasActiveFilters()) {
            $buttons[] = Button::add('clear-filters')
                ->slot(__('Clear Filters'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "\$wire.clearAllFilters(); \$wire.set('search', '');",
                ]);
        }

        if (! empty($this->checkboxValues)) {
            $buttons[] = Button::add('bulk-delete')
                ->slot(__('Bulk Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected printers?')."')) { \$wire.bulkDelete() }",
                ]);
        }
        $buttons[] = Button::add('export-selected')
            ->slot($this->selectAllRecords ? __('Export All Records') : __('Export Selected'))
            ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
            ->attributes([
                'x-data' => '',
                'x-show' => "\$store.pgBulkActions.count('{$this->tableName}') > 0",
                'x-on:click' => '$wire.exportSelected()',
            ]);

        $buttons[] = Button::add('bulk-delete')
            ->slot(__('Bulk Delete'))
            ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
            ->attributes([
                'x-data' => '',
                'x-show' => "\$store.pgBulkActions.count('{$this->tableName}') > 0",
                'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected printers?')."')) { \$wire.bulkDelete() }",
            ]);

        return $buttons;
    }

    protected function hasActiveFilters(): bool
    {
        if (filled($this->search)) {
            return true;
        }

        foreach ($this->filters as $filter) {
            if (is_array($filter)) {
                foreach ($filter as $value) {
                    if (filled($value)) {
                        return true;
                    }
                }
            } elseif (filled($filter)) {
                return true;
            }
        }

        return false;
    }

    public function actions(object $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->route('printers.edit', ['printer' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deletePrinter', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this printer? This action cannot be undone.')),
        ];
    }

    public function updatedCheckboxAll(): void
    {
        if (! $this->checkboxAll) {
            $this->selectAllRecords = false;
        }
    }

    public function toggleSelectAll(): void
    {
        $this->selectAllRecords = ! $this->selectAllRecords;
    }

    public function exportSelected(): void
    {
        if (! $this->selectAllRecords && empty($this->checkboxValues)) {
            Flux::toast(__('Please select at least one printer.'), variant: 'warning');

            return;
        }

        if ($this->selectAllRecords) {
            $this->dispatch('showExportModal', ids: null, all: true);
        } else {
            $this->dispatch('showExportModal', ids: $this->checkboxValues, all: false);
        }

        $this->resetCheckboxes();
    }

    protected function resetCheckboxes(): void
    {
        $this->selectAllRecords = false;
        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);
    }

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one printer.'), variant: 'warning');

            return;
        }

        Post::whereIn('id', $ids)->where('post_type', 'printer')->get()->each(function ($perinter) {
            $perinter->translations()->delete();
            $perinter->delete();
        });

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected printers deleted successfully.'), variant: 'success');
    }

    #[On('deletePrinter')]
    public function deletePrinter(int $id): void
    {
        $printer = Post::findOrFail($id);
        $printer->translations()->delete();
        $printer->delete();

        Flux::toast(__('Printer deleted successfully.'), variant: 'success');
    }
}

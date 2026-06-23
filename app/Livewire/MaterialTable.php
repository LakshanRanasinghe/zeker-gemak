<?php

namespace App\Livewire;

use App\Concerns\RendersHoverBadgeList;
use App\Models\Material;
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
use Vanilo\Category\Models\TaxonProxy;

final class MaterialTable extends PowerGridComponent
{
    use RendersHoverBadgeList;

    public string $tableName = 'materials-table';

    public bool $selectAllRecords = false;

    public string $entityLabel = 'materials';

    public function totalRecordCount(): int
    {
        return Material::count();
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

        return DB::table('materials')
            ->leftJoin('translations as t_mat', function ($join) use ($locale) {
                $join->on('t_mat.translatable_id', '=', 'materials.id')
                    ->where('t_mat.translatable_type', '=', 'App\Models\Material')
                    ->where('t_mat.language', '=', $locale);
            })
            ->leftJoin(DB::raw("(
                SELECT m.model_id, GROUP_CONCAT(COALESCE(t_taxon.name, t.name) SEPARATOR ', ') as taxon_names
                FROM model_taxons m
                JOIN taxons t ON t.id = m.taxon_id
                LEFT JOIN translations t_taxon ON t_taxon.translatable_id = t.id
                    AND t_taxon.translatable_type = 'Vanilo\\\\Category\\\\Models\\\\Taxon'
                    AND t_taxon.language = '{$locale}'
                WHERE m.model_type = 'App\\\\Models\\\\Material'
                GROUP BY m.model_id
            ) material_taxons"), 'material_taxons.model_id', '=', 'materials.id')
            ->selectRaw("
                materials.id,
                COALESCE(t_mat.name, JSON_UNQUOTE(JSON_EXTRACT(t_mat.fields, '$.name')), JSON_UNQUOTE(JSON_EXTRACT(t_mat.fields, '$.title')), materials.title) as title,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t_mat.fields, '$.subtitle')), materials.subtitle) as subtitle,
                material_taxons.taxon_names as category_name,
                materials.created_at
            ");
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('subtitle')
            ->add('category_name', function ($row) {
                if (! $row->category_name) {
                    return '—';
                }

                $categories = array_values(array_filter(array_map('trim', explode(',', (string) $row->category_name))));

                return $this->renderHoverBadgeList($categories);
            })
            ->add('created_at', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id', 'materials.id')
                ->searchable()
                ->sortable(),

            Column::make(__('Title'), 'title', 'materials.title')
                ->sortable()
                ->searchable(),

            Column::make(__('Subtitle'), 'subtitle', 'materials.subtitle')
                ->searchable(),

            Column::make(__('Category'), 'category_name'),

            Column::make(__('Created at'), 'created_at', 'materials.created_at')
                ->sortable()->hidden(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('category_name', 'category_name')
                ->dataSource(
                    TaxonProxy::whereHas('taxonomy', fn ($q) => $q->where('name', 'Material Category'))
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(fn ($taxon) => [
                            'name' => $taxon->name,
                            'value' => (string) $taxon->id,
                        ])
                        ->prepend([
                            'name' => __('Uncategorized'),
                            'value' => '__none__',
                        ])
                        ->values()
                        ->all()
                )
                ->optionValue('value')
                ->optionLabel('name')
                ->builder(function (Builder $query, string $value) {
                    if ($value === '__none__') {
                        $query->whereNotExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('model_taxons')
                                ->whereColumn('model_taxons.model_id', 'materials.id')
                                ->where('model_taxons.model_type', 'App\Models\Material');
                        });

                        return;
                    }

                    $query->whereExists(function ($q) use ($value) {
                        $q->select(DB::raw(1))
                            ->from('model_taxons')
                            ->whereColumn('model_taxons.model_id', 'materials.id')
                            ->where('model_taxons.model_type', 'App\Models\Material')
                            ->where('model_taxons.taxon_id', $value);
                    });
                }),

            Filter::datepicker('created_at', 'materials.created_at')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                ]),
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
                'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected materials?')."')) { \$wire.bulkDelete() }",
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
                ->route('materials.edit', ['material' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('spec-sheet')
                ->slot(__('Spec Sheet'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->route('api.materials.spec-sheet', ['id' => $row->id, 'lang' => app()->getLocale()])
                ->attributes(['target' => '_blank', 'rel' => 'noopener']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteMaterial', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this material? This action cannot be undone.')),
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
            Flux::toast(__('Please select at least one material.'), variant: 'warning');

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
            Flux::toast(__('Please select at least one material.'), variant: 'warning');

            return;
        }

        // Material::whereIn('id', $ids)->get()->each->delete();
        Material::whereIn('id', $ids)->get()->each(function ($material) {
            $material->translations()->delete();
            $material->taxons()->detach();
            $material->delete();
        });

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected materials deleted successfully.'), variant: 'success');
    }

    #[On('deleteMaterial')]
    public function deleteMaterial(int $id): void
    {
        $material = Material::findOrFail($id);
        $material->translations()->delete();
        $material->taxons()->detach();
        $material->delete();

        Flux::toast(__('Material deleted successfully.'), variant: 'success');
    }
}

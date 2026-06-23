<?php

namespace App\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Translation\Models\Translation;

final class TaxonomyTable extends PowerGridComponent
{
    public string $tableName = 'userTableTaxonomyTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        $locale = app()->getLocale();

        return DB::table('taxonomies')
            ->leftJoin('translations as t', function ($join) use ($locale) {
                $join->on('t.translatable_id', '=', 'taxonomies.id')
                    ->where('t.translatable_type', '=', morph_type_of(Taxonomy::class))
                    ->where('t.language', '=', $locale);
            })
            ->selectRaw("
                taxonomies.id,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.name')), taxonomies.name) as name,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.fields, '$.slug')), taxonomies.slug) as slug,
                taxonomies.created_at
            ");
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('slug')
            ->add('created_at')
            ->add('created_at_formatted', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y H:i:s'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->sortable()
                ->searchable(),

            Column::make(__('Name'), 'name')
                ->sortable()
                ->searchable(),

            Column::make(__('Slug'), 'slug')
                ->sortable()
                ->searchable(),

            Column::make(__('Created at'), 'created_at_formatted', 'created_at')
                ->sortable()->hidden(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
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
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected taxonomies?')."')) { \$wire.bulkDelete() }",
                ]);
        }

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
                ->route('taxonomies.edit', ['taxonomy' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteTaxonomy', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this taxonomy? This action cannot be undone.')),
        ];
    }

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one taxonomy.'), variant: 'warning');

            return;
        }

        foreach ($ids as $id) {
            $this->deleteTaxonomy((int) $id);
        }

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected taxonomies deleted successfully.'), variant: 'success');
    }

    #[On('deleteTaxonomy')]
    public function deleteTaxonomy(int $id): void
    {
        $taxonomy = Taxonomy::with('taxons')->findOrFail($id);

        DB::transaction(function () use ($taxonomy) {
            $taxonomy->removeFromAllChannels();
            $taxonomy->videos()->detach();
            $this->deleteTranslationsFor($taxonomy);

            $taxonomy->taxons->each(function ($taxon) {
                $taxon->videos()->detach();
                $this->deleteTranslationsFor($taxon);
                $taxon->delete();
            });

            $taxonomy->delete();
        });

        Flux::toast(__('Taxonomy deleted successfully.'), variant: 'success');
    }

    private function deleteTranslationsFor(Model $model): void
    {
        Translation::query()
            ->where('translatable_type', morph_type_of($model))
            ->where('translatable_id', $model->getKey())
            ->delete();
    }

    /*
    public function actionRules($row): array
    {
       return [
            // Hide button edit for ID 1
            Rule::button('edit')
                ->when(fn($row) => $row->id === 1)
                ->hide(),
        ];
    }
    */
}

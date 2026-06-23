<?php

namespace App\Livewire;

use App\Concerns\RendersHoverBadgeList;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Konekt\Address\Models\Country;
use Konekt\Address\Models\Zone;
use Konekt\Address\Models\ZoneMemberType;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ZoneTable extends PowerGridComponent
{
    use RendersHoverBadgeList;

    public string $tableName = 'zoneTable';

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
        return Zone::query()
            ->with([
                'members' => fn ($query) => $query
                    ->where('member_type', ZoneMemberType::COUNTRY),
            ]);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('scope_formatted', fn ($row) => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200">'.ucfirst($row->scope->value()).'</span>')
            ->add('countries', function ($row) {
                $ids = collect($row->members ?? [])
                    ->filter(fn ($member) => $member->isCountry())
                    ->pluck('member_id')
                    ->filter()
                    ->all();

                if (empty($ids)) {
                    return '—';
                }

                $names = Country::whereIn('id', $ids)
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();

                return $this->renderHoverBadgeList($names);
            })
            ->add('created_at')
            ->add('created_at_formatted', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y h:i A'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->searchable()
                ->sortable(),

            Column::make(__('Name'), 'name')
                ->sortable()
                ->searchable(),

            Column::make(__('Scope'), 'scope_formatted', 'scope')
                ->sortable(),

            Column::make(__('Countries'), 'countries'),

            Column::make(__('Created at'), 'created_at_formatted', 'created_at')
                ->sortable()->hidden(),

            Column::make(__('Created at'), 'created_at')
                ->sortable()
                ->searchable()->hidden(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    #[On('editZone')]
    public function editZone($rowId): void
    {
        $this->redirect(route('zones.edit', $rowId), navigate: true);
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
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected zones?')."')) { \$wire.bulkDelete() }",
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
            if (\is_array($filter)) {
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

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one zone.'), variant: 'warning');

            return;
        }

        Zone::whereIn('id', $ids)->delete();

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected zones deleted successfully.'), variant: 'success');
    }

    #[On('deleteZone')]
    public function deleteZone(int $id): void
    {
        Zone::findOrFail($id)->delete();
        Flux::toast(__('Zone deleted successfully.'), variant: 'success');
    }

    public function actions(Zone $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->dispatch('editZone', ['rowId' => $row->id]),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteZone', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this zone? This action cannot be undone.')),
        ];
    }
}

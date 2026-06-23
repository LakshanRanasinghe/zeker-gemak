<?php

namespace App\Livewire;

use App\Models\WarrantyGroup;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class WarrantyGroupTable extends PowerGridComponent
{
    public string $tableName = 'warrantyGroupTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return WarrantyGroup::query()->withCount(['options', 'products']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('is_active_label', fn (WarrantyGroup $group) => $group->is_active ? __('Active') : __('Inactive'))
            ->add('options_count')
            ->add('products_count')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')->sortable()->searchable()->hidden(),
            Column::make(__('Name'), 'name')->sortable()->searchable(),
            Column::make(__('Status'), 'is_active_label')->sortable(),
            Column::make(__('Options'), 'options_count')->sortable(),
            Column::make(__('Products'), 'products_count')->sortable(),
            Column::make(__('Created at'), 'created_at')->sortable()->searchable()->hidden(),
            Column::action(__('Action')),
        ];
    }

    #[On('editWarrantyGroup')]
    public function editWarrantyGroup(int $rowId): void
    {
        $this->redirect(route('warranty-groups.edit', $rowId), navigate: true);
    }

    #[On('deleteWarrantyGroup')]
    public function deleteWarrantyGroup(int $id): void
    {
        WarrantyGroup::findOrFail($id)->delete();
        Flux::toast(__('Warranty group deleted successfully.'), variant: 'success');
    }

    public function bulkDelete(): void
    {
        if (empty($this->checkboxValues)) {
            Flux::toast(__('Please select at least one warranty group.'), variant: 'warning');

            return;
        }

        WarrantyGroup::whereIn('id', $this->checkboxValues)->delete();

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected warranty groups deleted successfully.'), variant: 'success');
    }

    public function header(): array
    {
        if (empty($this->checkboxValues)) {
            return [];
        }

        return [
            Button::add('bulk-delete')
                ->slot(__('Bulk Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected warranty groups?')."')) { \$wire.bulkDelete() }",
                ]),
        ];
    }

    public function actions(WarrantyGroup $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('editWarrantyGroup', ['rowId' => $row->id]),

            Button::add('delete')
                ->slot(__('Delete'))
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('deleteWarrantyGroup', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this warranty group? Assigned products will no longer offer warranties.')),
        ];
    }
}

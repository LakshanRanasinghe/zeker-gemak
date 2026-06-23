<?php

namespace App\Livewire;

use App\Models\GroupProduct;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class GroupProductTable extends PowerGridComponent
{
    public string $tableName = 'groupProductTable';

    public function datasource(): Builder
    {
        return GroupProduct::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('title')
            ->add('slug')
            ->add('article_number')
            ->add('sku')
            ->add('state', fn (GroupProduct $model) => ucfirst($model->state ?? 'active'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->sortable()
                ->searchable(),

            Column::make(__('Title'), 'title')
                ->sortable()
                ->searchable(),

            Column::make(__('Slug'), 'slug')
                ->sortable()
                ->searchable(),

            Column::make(__('Article Number'), 'article_number')
                ->sortable()
                ->searchable(),

            Column::make(__('SKU'), 'sku')
                ->sortable()
                ->searchable(),

            Column::make(__('Status'), 'state')
                ->sortable(),

            Column::action(__('Actions')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('title')->operators(['contains']),
            Filter::inputText('article_number')->operators(['contains']),
            Filter::inputText('sku')->operators(['contains']),
            Filter::select('state')
                ->dataSource([
                    ['value' => 'draft', 'label' => __('Draft')],
                    ['value' => 'active', 'label' => __('Active')],
                    ['value' => 'unavailable', 'label' => __('Archived')],
                ])
                ->optionValue('value')
                ->optionLabel('label'),
        ];
    }

    #[On('edit')]
    public function edit($rowId): void
    {
        $this->redirect(route('group-products.edit', $rowId), navigate: true);
    }

    #[On('delete')]
    public function delete($rowId): void
    {
        $groupProduct = GroupProduct::findOrFail($rowId);

        $groupProduct->delete();
        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    public function actions(GroupProduct $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->id()
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('edit', ['rowId' => $row->id]),

            Button::add('delete')
                ->slot(__('Delete'))
                ->id()
                ->class('pg-btn-white dark:ring-red-600 dark:border-red-600 dark:hover:bg-red-700 dark:ring-offset-red-800 dark:text-red-300 dark:bg-red-700')
                ->dispatch('delete', ['rowId' => $row->id])
                ->confirm(__('Are you sure you want to delete this group product?')),
        ];
    }
}

<?php

namespace App\Livewire;

use App\Models\CountryShippingRule;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class ShippingCostTable extends PowerGridComponent
{
    public string $tableName = 'shippingCostTable';

    public function setUp(): array
    {
        return [
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return CountryShippingRule::query();
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('country_code')
            ->add('country_name')
            ->add('shipping_cost_formatted', fn (CountryShippingRule $rule): string => '€'.number_format((float) $rule->shipping_cost, 2))
            ->add('free_shipping_threshold_formatted', fn (CountryShippingRule $rule): string => '€'.number_format((float) $rule->free_shipping_threshold, 2))
            ->add('status', fn (CountryShippingRule $rule): string => $rule->is_active ? __('Active') : __('Inactive'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('Code'), 'country_code')->sortable()->searchable(),
            Column::make(__('Country'), 'country_name')->sortable()->searchable(),
            Column::make(__('Shipping Cost'), 'shipping_cost_formatted', 'shipping_cost')->sortable(),
            Column::make(__('Free Shipping Threshold'), 'free_shipping_threshold_formatted', 'free_shipping_threshold')->sortable(),
            Column::make(__('Status'), 'status', 'is_active')->sortable(),
            Column::action(__('Action')),
        ];
    }

    public function actions(CountryShippingRule $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 rounded-md border border-zinc-200 dark:border-zinc-700')
                ->dispatch('editShippingCostRule', ['rowId' => $row->id]),
            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 rounded-md border border-red-200 text-red-600 dark:border-red-800 dark:text-red-400')
                ->dispatch('deleteShippingCostRule', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this shipping rule?')),
        ];
    }

    #[On('editShippingCostRule')]
    public function editShippingCostRule(int $rowId): void
    {
        $this->redirect(route('shipping-cost.edit', $rowId), navigate: true);
    }

    #[On('deleteShippingCostRule')]
    public function deleteShippingCostRule(int $id): void
    {
        CountryShippingRule::query()->findOrFail($id)->delete();
        Flux::toast(__('Shipping rule deleted.'), variant: 'success');
    }
}

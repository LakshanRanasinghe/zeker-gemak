<?php

namespace App\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use Vanilo\Shipment\Models\Carrier;
use Vanilo\Shipment\Models\ShippingMethod;

final class ShippingMethodTable extends PowerGridComponent
{
    public string $tableName = 'shippingMethodTable';

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
        return ShippingMethod::query()
            ->leftJoin('carriers', 'shipping_methods.carrier_id', '=', 'carriers.id')
            ->leftJoin('zones', 'shipping_methods.zone_id', '=', 'zones.id')
            ->select('shipping_methods.*', 'carriers.name as carrier_name', 'zones.name as zone_name');
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('carrier_name', fn ($row) => $row->carrier_name ?? '—')
            ->add('zone_name', fn ($row) => $row->zone_name ?? '—')
            ->add('calculator', fn ($row) => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200">'.ucfirst(str_replace('_', ' ', $row->calculator ?? 'none')).'</span>')
            ->add('cost', function ($row) {
                $config = is_array($row->configuration) ? $row->configuration : [];

                return isset($config['cost']) ? config('app.currency_symbol', '€').number_format($config['cost'], 2) : '—';
            })
            ->add('is_active', fn ($row) => $row->is_active
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-400">'.__('Active').'</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400">'.__('Inactive').'</span>')
            ->add('created_at')
            ->add('created_at_formatted', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y h:i A'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->searchable()
                ->sortable(),

            Column::make(__('Name'), 'name', 'shipping_methods.name')
                ->sortable()
                ->searchable(),

            Column::make(__('Carrier'), 'carrier_name', 'carriers.name')
                ->sortable()
                ->searchable(),

            Column::make(__('Zone'), 'zone_name', 'zones.name')
                ->sortable()
                ->searchable(),

            Column::make(__('Calculator'), 'calculator')
                ->sortable(),

            Column::make(__('Cost'), 'cost'),

            Column::make(__('Status'), 'is_active', 'shipping_methods.is_active')
                ->sortable(),

            Column::make(__('Created at'), 'created_at_formatted', 'shipping_methods.created_at')
                ->sortable()->hidden(),

            Column::make(__('Created at'), 'created_at', 'shipping_methods.created_at')
                ->sortable()
                ->searchable()->hidden(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::boolean('is_active')
                ->label(__('Active'), __('Inactive'))
                ->builder(function (Builder $query, string $value) {
                    $query->where('shipping_methods.is_active', $value === 'true');
                }),

            Filter::select('carrier_id')
                ->dataSource(Carrier::query()->where('is_active', true)->get())
                ->optionLabel('name')
                ->optionValue('id')
                ->builder(function (Builder $query, string $value) {
                    $query->where('shipping_methods.carrier_id', $value);
                }),
        ];
    }

    #[On('editShippingMethod')]
    public function editShippingMethod($rowId): void
    {
        $this->redirect(route('shipping-settings.edit', $rowId), navigate: true);
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
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected shipping methods?')."')) { \$wire.bulkDelete() }",
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

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one shipping method.'), variant: 'warning');

            return;
        }

        ShippingMethod::whereIn('id', $ids)->delete();

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected shipping methods deleted successfully.'), variant: 'success');
    }

    #[On('deleteShippingMethod')]
    public function deleteShippingMethod(int $id): void
    {
        ShippingMethod::findOrFail($id)->delete();
        Flux::toast(__('Shipping method deleted successfully.'), variant: 'success');
    }

    public function actions(ShippingMethod $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->dispatch('editShippingMethod', ['rowId' => $row->id]),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteShippingMethod', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this shipping method? This action cannot be undone.')),
        ];
    }
}

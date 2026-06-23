<?php

namespace App\Livewire;

use App\Models\Coupon;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class CouponTable extends PowerGridComponent
{
    public string $tableName = 'couponTable';

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
        return Coupon::query();
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('code')
            ->add('discount_type', fn (Coupon $row) => Coupon::DISCOUNT_TYPES[$row->discount_type] ?? $row->discount_type)
            ->add('amount', fn (Coupon $row) => $row->discount_type === 'percentage'
                ? $row->amount.'%'
                : '€'.number_format($row->amount, 2))
            ->add('usage', fn (Coupon $row) => $row->usage_count.' / '.($row->usage_limit_per_coupon ?? '∞'))
            ->add('expiry_date_formatted', fn (Coupon $row) => $row->expiry_date?->format('Y-m-d') ?? '—')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')
                ->searchable()
                ->sortable(),

            Column::make(__('Code'), 'code')
                ->sortable()
                ->searchable(),

            Column::make(__('Discount Type'), 'discount_type')
                ->sortable(),

            Column::make(__('Amount'), 'amount'),

            Column::make(__('Usage'), 'usage'),

            Column::make(__('Expiry Date'), 'expiry_date_formatted', 'expiry_date')
                ->sortable(),

            Column::make(__('Created at'), 'created_at')
                ->sortable()
                ->searchable()
                ->hidden(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('discount_type')
                ->dataSource(collect(Coupon::DISCOUNT_TYPES)->map(fn ($label, $value) => [
                    'id' => $value,
                    'name' => $label,
                ])->values()->toArray())
                ->optionLabel('name')
                ->optionValue('id'),

            Filter::datepicker('expiry_date_formatted', 'expiry_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                ])
                ->builder(function (Builder $query, array $value) {
                    $start = data_get($value, 'start');
                    $end = data_get($value, 'end');
                    if ($start && $end) {
                        $query->whereBetween('expiry_date', [
                            Carbon::parse($start)->startOfDay(),
                            Carbon::parse($end)->endOfDay(),
                        ]);
                    }
                }),
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
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected coupons?')."')) { \$wire.bulkDelete() }",
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
            Flux::toast(__('Please select at least one coupon.'), variant: 'warning');

            return;
        }

        Coupon::whereIn('id', $ids)->delete();

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected coupons deleted successfully.'), variant: 'success');
    }

    #[On('deleteCoupon')]
    public function deleteCoupon(int $id): void
    {
        Coupon::findOrFail($id)->delete();
        Flux::toast(__('Coupon deleted successfully.'), variant: 'success');
    }

    public function actions(Coupon $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->route('coupons.edit', ['shopCoupon' => $row])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->id()
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteCoupon', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this coupon? This action cannot be undone.')),
        ];
    }
}

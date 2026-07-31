<?php

namespace App\Livewire;

use App\Models\User;
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

final class CustomerTable extends PowerGridComponent
{
    public string $tableName = 'customers-table';

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
        return DB::table('users')
            ->whereNull('users.deleted_at')
            ->leftJoin(
                DB::raw('(SELECT model_id, COUNT(*) as address_count, MAX(CASE WHEN type = "billing" THEN email END) as billing_email FROM addresses WHERE model_type = "App\\\\Models\\\\User" AND deleted_at IS NULL GROUP BY model_id) as addr_counts'),
                fn ($join) => $join->on('users.id', '=', 'addr_counts.model_id')
            )
            ->leftJoin(
                DB::raw('(SELECT o.user_id, COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.deleted_at IS NULL GROUP BY o.user_id) as revenue'),
                fn ($join) => $join->on('users.id', '=', 'revenue.user_id')
            )
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.is_active',
                'users.type',
                'users.created_at',
                DB::raw('COALESCE(addr_counts.address_count, 0) as address_count'),
                DB::raw('addr_counts.billing_email as billing_email'),
                DB::raw('COALESCE(revenue.total_revenue, 0) as total_revenue'),
            ]);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('phone', fn ($row) => $row->phone ?? '—')
            ->add('is_active', fn ($row) => $row->is_active
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">'.__('Active').'</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">'.__('Inactive').'</span>'
            )
            ->add('type', fn ($row) => $row->type ? ucfirst($row->type) : '—')
            ->add('address_count', fn ($row) => $row->address_count > 0
                ? '<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">'.$row->address_count.'</span>'
                : '<span class="text-zinc-400">0</span>'
            )
            ->add('billing_email', fn ($row) => $row->billing_email ?? '<span class="text-zinc-400">—</span>')
            ->add('total_revenue', fn ($row) => $row->total_revenue > 0
                ? '€ '.number_format($row->total_revenue, 2)
                : '<span class="text-zinc-400">€ 0.00</span>'
            )
            ->add('created_at', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y h:i A'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id', 'users.id')
                ->searchable()
                ->sortable(),

            Column::make(__('Name'), 'name', 'users.name')
                ->sortable()
                ->searchable(),

            Column::make(__('Email'), 'email', 'users.email')
                ->sortable()
                ->searchable(),

            Column::make(__('Phone'), 'phone', 'users.phone')
                ->searchable(),

            Column::make(__('Status'), 'is_active', 'users.is_active')
                ->sortable(),

            Column::make(__('Type'), 'type', 'users.type')
                ->sortable(),

            Column::make(__('Addresses'), 'address_count')
                ->sortable(),

            Column::make(__('Billing Email'), 'billing_email'),

            Column::make(__('Total Revenue'), 'total_revenue')
                ->sortable(),

            Column::make(__('Created at'), 'created_at', 'users.created_at')
                ->sortable(),

            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::boolean('is_active', 'users.is_active')
                ->label(__('Active'), __('Inactive')),

            Filter::select('type', 'users.type')
                ->dataSource(
                    collect(['client', 'admin'])
                        ->map(fn ($type) => [
                            'name' => ucfirst($type),
                            'value' => $type,
                        ])
                        ->all()
                )
                ->optionValue('value')
                ->optionLabel('name'),

            Filter::datepicker('created_at', 'users.created_at')
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

        if (! empty($this->checkboxValues)) {
            $buttons[] = Button::add('bulk-delete')
                ->slot(__('Bulk Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected customers?')."')) { \$wire.bulkDelete() }",
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
                ->route('customers.edit', ['id' => $row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteCustomer', ['id' => $row->id])
                ->confirm(__('Are you sure you want to delete this customer? This action cannot be undone.')),
        ];
    }

    public function bulkDelete(): void
    {
        $ids = $this->checkboxValues;

        if (empty($ids)) {
            Flux::toast(__('Please select at least one customer.'), variant: 'warning');

            return;
        }

        $users = User::whereIn('id', $ids)->get();
        $softDeleted = 0;
        $hardDeleted = 0;

        foreach ($users as $user) {
            $this->deleteUser($user);

            if ($user->orders()->exists()) {
                $softDeleted++;
            } else {
                $hardDeleted++;
            }
        }

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        $message = __(':count customers deleted successfully.', ['count' => $hardDeleted + $softDeleted]);
        if ($softDeleted > 0) {
            $message .= ' '.__(':count with orders were archived (soft deleted).', ['count' => $softDeleted]);
        }

        Flux::toast($message, variant: 'success');
    }

    #[On('deleteCustomer')]
    public function deleteCustomer(int $id): void
    {
        $user = User::findOrFail($id);
        $hasOrders = $user->orders()->exists();

        $this->deleteUser($user);

        if ($hasOrders) {
            Flux::toast(__('Customer archived successfully. Order history has been preserved.'), variant: 'success');
        } else {
            Flux::toast(__('Customer deleted successfully.'), variant: 'success');
        }
    }

    protected function deleteUser(User $user): void
    {
        // Always clean up non-essential associated data
        $user->favoriteProducts()->delete();
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        if ($user->orders()->exists()) {
            // Soft delete — preserve user for order history / audit trail
            $user->addresses()->delete();
            $user->delete(); // SoftDeletes trait: sets deleted_at
        } else {
            // No orders — safe to hard delete everything
            $user->addresses()->forceDelete();
            // $user->roles()->detach();
            // $user->permissions()->detach();
            $user->forceDelete();
        }
    }
}

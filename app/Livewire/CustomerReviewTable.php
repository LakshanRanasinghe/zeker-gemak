<?php

namespace App\Livewire;

use App\Models\CustomerReview;
use Flux\Flux;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class CustomerReviewTable extends PowerGridComponent
{
    public string $tableName = 'customer-reviews-table';

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
        return DB::table('customer_reviews')
            ->leftJoin('users', 'users.id', '=', 'customer_reviews.user_id')
            ->whereNull('customer_reviews.deleted_at')
            ->select([
                'customer_reviews.id',
                'customer_reviews.name',
                'customer_reviews.email',
                'customer_reviews.rating',
                'customer_reviews.comment',
                'customer_reviews.source',
                'customer_reviews.status',
                'customer_reviews.product_id',
                'customer_reviews.product_type',
                'customer_reviews.user_id',
                'users.name as user_name',
                'users.email as user_email',
                'customer_reviews.created_at',
            ]);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('email')
            ->add('rating', fn ($row) => str_repeat('★', (int) $row->rating).str_repeat('☆', max(0, 5 - (int) $row->rating)))
            ->add('comment_excerpt', fn ($row) => Str::limit((string) $row->comment, 10))
            ->add('source', function ($row) {
                $map = [
                    'manual' => __('Manual'),
                    'google' => __('Google'),
                    'api' => __('Storefront'),
                ];

                return $map[$row->source] ?? ucfirst((string) $row->source);
            })
            ->add('status_badge', function ($row) {
                $map = [
                    'pending' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
                    'rejected' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
                ];
                $labels = [
                    'pending' => __('Pending'),
                    'approved' => __('Approved'),
                    'rejected' => __('Rejected'),
                ];
                $cls = $map[$row->status] ?? $map['pending'];
                $label = $labels[$row->status] ?? ucfirst((string) $row->status);

                return '<span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs font-medium '.$cls.'">'.$label.'</span>';
            })
            ->add('product_label', function ($row) {
                if (! $row->product_id) {
                    return '<span class="text-zinc-400">'.__('Site-wide').'</span>';
                }

                $type = $row->product_type === 'variable' ? __('Variable') : __('Simple');

                return '#'.$row->product_id.' ('.$type.')';
            })
            ->add('customer_label', function ($row) {
                if (! $row->user_id) {
                    return '<span class="text-zinc-400">'.__('Guest').'</span>';
                }

                $url = route('customers.edit', ['id' => $row->user_id]);
                $label = e($row->user_name ?: ('#'.$row->user_id));

                return '<a href="'.$url.'" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:underline">'.$label.'</a>';
            })
            ->add('created_at', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')->searchable()->sortable(),
            Column::make(__('Name'), 'name')->searchable()->sortable(),
            Column::make(__('Email'), 'email')->searchable()->hidden(),
            Column::make(__('Rating'), 'rating')->sortable(),
            Column::make(__('Product'), 'product_label')->visibleInExport(false),
            Column::make(__('Customer'), 'customer_label')->visibleInExport(false),
            Column::make(__('Comment'), 'comment_excerpt')->searchable(),
            Column::make(__('Source'), 'source')->sortable(),
            Column::make(__('Status'), 'status_badge')->visibleInExport(false),
            Column::make(__('Created at'), 'created_at')->sortable(),
            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource([
                    ['name' => __('Pending'), 'value' => 'pending'],
                    ['name' => __('Approved'), 'value' => 'approved'],
                    ['name' => __('Rejected'), 'value' => 'rejected'],
                ])
                ->optionValue('value')
                ->optionLabel('name'),
            Filter::select('rating', 'rating')
                ->dataSource([
                    ['name' => '5 ★', 'value' => 5],
                    ['name' => '4 ★', 'value' => 4],
                    ['name' => '3 ★', 'value' => 3],
                    ['name' => '2 ★', 'value' => 2],
                    ['name' => '1 ★', 'value' => 1],
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
            $buttons[] = Button::add('bulk-approve')
                ->slot(__('Approve Selected'))
                ->class('px-2 py-1 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-md text-sm shadow-sm hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 transition-colors')
                ->attributes(['x-data' => '', 'x-on:click' => '$wire.bulkApprove()']);

            $buttons[] = Button::add('bulk-reject')
                ->slot(__('Reject Selected'))
                ->class('px-2 py-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md text-sm shadow-sm hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-400 transition-colors')
                ->attributes(['x-data' => '', 'x-on:click' => '$wire.bulkReject()']);

            $buttons[] = Button::add('bulk-delete')
                ->slot(__('Bulk Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->attributes([
                    'x-data' => '',
                    'x-on:click' => "if(confirm('".__('Delete the selected reviews?')."')) { \$wire.bulkDelete() }",
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
        $actions = [];

        if ($row->status !== 'approved') {
            $actions[] = Button::add('approve')
                ->slot(__('Approve'))
                ->class('px-2 py-1 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-md text-sm shadow-sm hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 transition-colors')
                ->dispatch('approveReview', ['id' => $row->id]);
        }

        if ($row->status !== 'rejected') {
            $actions[] = Button::add('reject')
                ->slot(__('Reject'))
                ->class('px-2 py-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md text-sm shadow-sm hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-400 transition-colors')
                ->dispatch('rejectReview', ['id' => $row->id]);
        }

        $actions[] = Button::add('edit')
            ->slot(__('Edit'))
            ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
            ->route('customer-reviews.edit', ['customerReview' => $row->id])
            ->attributes(['wire:navigate' => '']);

        $actions[] = Button::add('delete')
            ->slot(__('Delete'))
            ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
            ->dispatch('deleteReview', ['id' => $row->id])
            ->confirm(__('Delete this review? This cannot be undone.'));

        return $actions;
    }

    #[On('approveReview')]
    public function approveReview(int $id): void
    {
        $review = CustomerReview::findOrFail($id);
        $review->update(['status' => 'approved', 'reviewed_at' => now()]);

        Flux::toast(__('Review approved.'), variant: 'success');
    }

    #[On('rejectReview')]
    public function rejectReview(int $id): void
    {
        $review = CustomerReview::findOrFail($id);
        $review->update(['status' => 'rejected', 'reviewed_at' => now()]);

        Flux::toast(__('Review rejected.'), variant: 'success');
    }

    #[On('deleteReview')]
    public function deleteReview(int $id): void
    {
        CustomerReview::findOrFail($id)->delete();

        Flux::toast(__('Review deleted.'), variant: 'success');
    }

    public function bulkApprove(): void
    {
        if (empty($this->checkboxValues)) {
            Flux::toast(__('Select at least one review.'), variant: 'warning');

            return;
        }

        CustomerReview::whereIn('id', $this->checkboxValues)
            ->update(['status' => 'approved', 'reviewed_at' => now()]);

        $this->clearSelection();
        Flux::toast(__('Selected reviews approved.'), variant: 'success');
    }

    public function bulkReject(): void
    {
        if (empty($this->checkboxValues)) {
            Flux::toast(__('Select at least one review.'), variant: 'warning');

            return;
        }

        CustomerReview::whereIn('id', $this->checkboxValues)
            ->update(['status' => 'rejected', 'reviewed_at' => now()]);

        $this->clearSelection();
        Flux::toast(__('Selected reviews rejected.'), variant: 'success');
    }

    public function bulkDelete(): void
    {
        if (empty($this->checkboxValues)) {
            Flux::toast(__('Select at least one review.'), variant: 'warning');

            return;
        }

        CustomerReview::whereIn('id', $this->checkboxValues)->get()->each->delete();

        $this->clearSelection();
        Flux::toast(__('Selected reviews deleted.'), variant: 'success');
    }

    protected function clearSelection(): void
    {
        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);
    }
}

<?php

namespace App\Livewire;

use App\Concerns\RendersHoverBadgeList;
use App\Models\MasterProduct;
use App\Models\Product;
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

final class ProductTable extends PowerGridComponent
{
    use RendersHoverBadgeList;

    public string $tableName = 'products';

    public string $primaryKey = 'row_id';

    public bool $selectAllRecords = false;

    public string $entityLabel = 'products';

    public function totalRecordCount(): int
    {
        return Product::whereNull('deleted_at')->count()
            + MasterProduct::whereNull('deleted_at')->count();
    }

    public function setUp(): array
    {
        $this->showCheckBox('row_id');

        return [
            PowerGrid::header()
                ->showSearchInput()
                ->includeViewOnTop('components.shared.select-all-banner'),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        $locale = app()->getLocale();

        $simpleProducts = DB::table('products')
            ->whereNull('products.deleted_at')
            ->leftJoin('translations as t_simple', function ($join) use ($locale) {
                $join->on('t_simple.translatable_id', '=', 'products.id')
                    ->where('t_simple.translatable_type', '=', 'App\Models\Product')
                    ->where('t_simple.language', '=', $locale);
            })
            ->leftJoin(DB::raw("(
                SELECT m.model_id, CONCAT('/storage/', m.id, '/', m.file_name) as image_url
                FROM media m
                INNER JOIN (
                    SELECT model_id, MIN(id) as min_id
                    FROM media
                    WHERE model_type = 'App\\\\Models\\\\Product' AND collection_name = 'main'
                    GROUP BY model_id
                ) fm ON m.id = fm.min_id
            ) product_media"), 'product_media.model_id', '=', 'products.id')
            ->leftJoin(DB::raw("(
                SELECT m.model_id, GROUP_CONCAT(t.name SEPARATOR ', ') as taxon_names
                FROM model_taxons m
                JOIN taxons t ON t.id = m.taxon_id
                WHERE m.model_type = 'App\\\\Models\\\\Product'
                GROUP BY m.model_id
            ) product_taxons"), 'product_taxons.model_id', '=', 'products.id')
            ->selectRaw("
                CONCAT('simple_', products.id) as row_id,
                products.id,
                COALESCE(t_simple.name, products.name) as name,
                products.sku,
                product_taxons.taxon_names as category,
                'simple' as product_type,
                products.state as status,
                products.price,
                products.stock,
                product_media.image_url as main_image,
                products.created_at
            ");

        $masterProducts = DB::table('master_products')
            ->whereNull('master_products.deleted_at')
            ->leftJoin('translations as t_master', function ($join) use ($locale) {
                $join->on('t_master.translatable_id', '=', 'master_products.id')
                    ->where('t_master.translatable_type', '=', 'App\Models\MasterProduct')
                    ->where('t_master.language', '=', $locale);
            })
            ->leftJoin(DB::raw("(
                SELECT m.model_id, CONCAT('/storage/', m.id, '/', m.file_name) as image_url
                FROM media m
                INNER JOIN (
                    SELECT model_id, MIN(id) as min_id
                    FROM media
                    WHERE model_type = 'App\\\\Models\\\\MasterProduct' AND collection_name = 'main'
                    GROUP BY model_id
                ) fm ON m.id = fm.min_id
            ) master_media"), 'master_media.model_id', '=', 'master_products.id')
            ->leftJoin(DB::raw("(
                SELECT m.model_id, GROUP_CONCAT(t.name SEPARATOR ', ') as taxon_names
                FROM model_taxons m
                JOIN taxons t ON t.id = m.taxon_id
                WHERE m.model_type = 'App\\\\Models\\\\MasterProduct'
                GROUP BY m.model_id
            ) master_taxons"), 'master_taxons.model_id', '=', 'master_products.id')
            ->leftJoin(DB::raw('(
                SELECT master_product_id, COALESCE(SUM(stock), 0) as total_stock
                FROM master_product_variants
                GROUP BY master_product_id
            ) variant_stock'), 'variant_stock.master_product_id', '=', 'master_products.id')
            ->selectRaw("
                CONCAT('variable_', master_products.id) as row_id,
                master_products.id,
                COALESCE(t_master.name, master_products.name) as name,
                '-' as sku,
                master_taxons.taxon_names as category,
                'variable' as product_type,
                master_products.state as status,
                master_products.price,
                COALESCE(variant_stock.total_stock, 0) as stock,
                master_media.image_url as main_image,
                master_products.created_at
            ");

        if (filled($this->search)) {
            $term = '%'.$this->search.'%';
            $simpleProducts->where(function ($q) use ($term) {
                $q->whereRaw('COALESCE(t_simple.name, products.name) LIKE ?', [$term])
                    ->orWhere('products.sku', 'like', $term)
                    ->orWhere('products.state', 'like', $term)
                    ->orWhere('products.price', 'like', $term)
                    ->orWhere('products.stock', 'like', $term)
                    ->orWhere('products.created_at', 'like', $term)
                    ->orWhereRaw("'simple' LIKE ?", [$term])
                    ->orWhere('product_taxons.taxon_names', 'like', $term);
            });
            $masterProducts->where(function ($q) use ($term) {
                $q->whereRaw('COALESCE(t_master.name, master_products.name) LIKE ?', [$term])
                    ->orWhere('master_products.state', 'like', $term)
                    ->orWhere('master_products.price', 'like', $term)
                    ->orWhere('master_products.created_at', 'like', $term)
                    ->orWhereRaw("'variable' LIKE ?", [$term])
                    ->orWhere('master_taxons.taxon_names', 'like', $term)
                    ->orWhere('variant_stock.total_stock', 'like', $term);
            });
        }

        $stockFilter = data_get($this->filters, 'select.stock_raw');
        if ($stockFilter === 'in_stock') {
            $simpleProducts->where('products.stock', '>', 0);
            $masterProducts->whereRaw('COALESCE(variant_stock.total_stock, 0) > 0');
        } elseif ($stockFilter === 'out_of_stock') {
            $simpleProducts->where(function ($q) {
                $q->where('products.stock', '<=', 0)->orWhereNull('products.stock');
            });
            $masterProducts->whereRaw('COALESCE(variant_stock.total_stock, 0) <= 0');
        }

        $statusFilter = data_get($this->filters, 'select.status');
        if ($statusFilter) {
            $simpleProducts->where('products.state', $statusFilter);
            $masterProducts->where('master_products.state', $statusFilter);
        }

        $categoryFilter = data_get($this->filters, 'select.category');
        if ($categoryFilter) {
            $simpleProducts->whereExists(function ($q) use ($categoryFilter) {
                $q->select(DB::raw(1))
                    ->from('model_taxons')
                    ->whereColumn('model_taxons.model_id', 'products.id')
                    ->where('model_taxons.model_type', 'App\\Models\\Product')
                    ->where('model_taxons.taxon_id', $categoryFilter);
            });
            $masterProducts->whereExists(function ($q) use ($categoryFilter) {
                $q->select(DB::raw(1))
                    ->from('model_taxons')
                    ->whereColumn('model_taxons.model_id', 'master_products.id')
                    ->where('model_taxons.model_type', 'App\\Models\\MasterProduct')
                    ->where('model_taxons.taxon_id', $categoryFilter);
            });
        }

        $dateFilter = data_get($this->filters, 'date.created_at') ?? data_get($this->filters, 'datetime.created_at');
        if ($dateFilter) {
            $start = data_get($dateFilter, 'start');
            $end = data_get($dateFilter, 'end');
            if ($start && $end) {
                $startDate = Carbon::parse($start)->format('Y-m-d H:i:s');
                $endDate = Carbon::parse($end)->format('Y-m-d H:i:s');
                $simpleProducts->whereBetween('products.created_at', [$startDate, $endDate]);
                $masterProducts->whereBetween('master_products.created_at', [$startDate, $endDate]);
            }
        }

        $typeFilter = data_get($this->filters, 'select.product_type');
        if ($typeFilter === 'simple') {
            return $simpleProducts;
        }
        if ($typeFilter === 'variable') {
            return $masterProducts;
        }

        return $simpleProducts->union($masterProducts);
    }

    public function fields(): PowerGridFields
    {
        $counter = 0;

        return PowerGrid::fields()
            ->add('row_id', fn ($row) => $row->row_id)
            ->add('id', function () use (&$counter) {
                return ++$counter;
            })
            ->add('main_image', fn ($row) => $row->main_image
                ? '<a href="'.route('products.edit', ['productKey' => $row->product_type.'_'.$row->id]).'"><img src="'.e($row->main_image).'" class="w-10 h-10 rounded-md object-cover" /></a>'
                : '<span class="inline-block w-10 h-10 rounded-md bg-zinc-100 dark:bg-zinc-800"></span>')
            ->add('name', function ($row) {
                $html = '<div><a href="'.route('products.edit', ['productKey' => $row->product_type.'_'.$row->id]).'">'.e($row->name).'</a>';
                if ($row->product_type === 'simple' && $row->sku) {
                    $html .= '<div class="text-xs text-zinc-400">'.e($row->sku).'</div>';
                }
                $html .= '</div>';

                return $html;
            })
            ->add('category', function ($row) {
                if (! $row->category) {
                    return '—';
                }

                $categories = array_values(array_filter(array_map('trim', explode(',', (string) $row->category))));

                return $this->renderHoverBadgeList($categories);
            })
            ->add('product_type', fn ($row) => ucfirst($row->product_type))
            ->add('status', fn ($row) => ucfirst($row->status))
            ->add('price', fn ($row) => config('app.currency_symbol').number_format((float) $row->price, 2))
            ->add('stock_raw', fn ($row) => $row->stock)
            ->add('stock', fn ($row) => (float) $row->stock > 0
                ? '<span class="text-green-600 dark:text-green-400 font-medium">'.__('In Stock').' ('.(float) $row->stock.')</span>'
                : '<span class="text-red-500 font-medium">'.__('Out of Stock').'</span>')
            ->add('created_at', fn ($row) => Carbon::parse($row->created_at)->format('d/m/Y'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('ID'), 'id')->sortable()->hidden(),
            Column::make(__('Product'), 'main_image'),
            Column::make(__('Title'), 'name')->sortable(),
            Column::make(__('Category'), 'category'),
            Column::make(__('Type'), 'product_type')->sortable()->hidden(),
            Column::make(__('Status'), 'status')->sortable(),
            Column::make(__('Price'), 'price')->sortable(),
            Column::make(__('Stock'), 'stock', 'stock_raw')->sortable(),
            Column::make(__('Created'), 'created_at')->sortable()->hidden(),
            Column::action(__('Action')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('stock_raw', 'stock_raw')
                ->dataSource([
                    ['name' => __('In Stock'), 'value' => 'in_stock'],
                    ['name' => __('Out of Stock'), 'value' => 'out_of_stock'],
                ])
                ->optionValue('value')
                ->optionLabel('name')
                ->builder(function (Builder $query, string $value) {
                    // Handled in datasource() to apply to both UNION queries
                }),

            Filter::select('product_type', 'product_type')
                ->dataSource([
                    ['name' => __('Simple'), 'value' => 'simple'],
                    ['name' => __('Variable'), 'value' => 'variable'],
                ])
                ->optionValue('value')
                ->optionLabel('name')
                ->builder(function (Builder $query, string $value) {
                    // Handled in datasource() to apply to both UNION queries
                }),

            Filter::select('category', 'category')
                ->dataSource(
                    DB::table('taxons')
                        ->select('taxons.id as value', 'taxons.name')
                        ->whereExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('model_taxons')
                                ->whereColumn('model_taxons.taxon_id', 'taxons.id')
                                ->whereIn('model_taxons.model_type', [
                                    'App\\Models\\Product',
                                    'App\\Models\\MasterProduct',
                                ]);
                        })
                        ->orderBy('taxons.name')
                        ->get()
                        ->map(fn ($t) => ['name' => $t->name, 'value' => (string) $t->value])
                        ->toArray()
                )
                ->optionValue('value')
                ->optionLabel('name')
                ->builder(function (Builder $query, string $value) {
                    // Handled in datasource() to apply to both UNION queries
                }),

            Filter::select('status', 'status')
                ->dataSource([
                    ['name' => __('Active'), 'value' => 'active'],
                    ['name' => __('Draft'), 'value' => 'draft'],
                    ['name' => __('Unavailable'), 'value' => 'unavailable'],
                ])
                ->optionValue('value')
                ->optionLabel('name')
                ->builder(function (Builder $query, string $value) {
                    // Handled in datasource() to apply to both UNION queries
                }),
            Filter::datepicker('created_at', 'created_at')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                ])
                ->builder(function (Builder $query, array $value) {
                    // No-op: handled in datasource() to apply to both UNION branches
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

        $buttons[] = Button::add('bulk-edit')
            ->slot(__('Bulk Edit'))
            ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
            ->attributes([
                'x-data' => '',
                'x-show' => "\$store.pgBulkActions.count('{$this->tableName}') > 0",
                'x-on:click' => '$dispatch(\'bulk-edit\', { productIds: window.pgBulkActions.get(\'products\') })',
            ]);

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
                'x-on:click' => "if(confirm('".__('Are you sure you want to delete the selected products?')."')) { \$wire.bulkDelete() }",
            ]);

        return $buttons;
    }

    public function actions(object $row): array
    {
        return [
            Button::add('edit')
                ->slot(__('Edit'))
                ->class('px-2 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-sm shadow-sm hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 transition-colors')
                ->route('products.edit', ['productKey' => $row->product_type.'_'.$row->id])
                ->attributes(['wire:navigate' => '']),

            Button::add('delete')
                ->slot(__('Delete'))
                ->class('px-2 py-1 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 transition-colors')
                ->dispatch('deleteProduct', ['id' => $row->id, 'type' => $row->product_type])
                ->confirm(__('Are you sure you want to delete this product? This action cannot be undone.')),
        ];
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
            Flux::toast(__('Please select at least one product.'), variant: 'warning');

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
        $rowIds = $this->checkboxValues;

        if (empty($rowIds)) {
            Flux::toast(__('Please select at least one product.'), variant: 'warning');

            return;
        }

        foreach ($rowIds as $rowId) {
            [$type, $id] = explode('_', $rowId, 2);
            $modelClass = $type === 'variable' ? MasterProduct::class : Product::class;
            $product = $modelClass::find((int) $id);
            if ($product) {
                $product->translations()->delete();
                $product->delete();
            }

        }

        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);

        Flux::toast(__('Selected products deleted successfully.'), variant: 'success');
    }

    #[On('deleteProduct')]
    public function deleteProduct(int $id, string $type): void
    {
        $modelClass = $type === 'variable' ? MasterProduct::class : Product::class;
        $product = $modelClass::findOrFail($id);
        $product->translations()->delete();
        $product->delete();

        Flux::toast(__('Product deleted successfully.'), variant: 'success');
    }
}

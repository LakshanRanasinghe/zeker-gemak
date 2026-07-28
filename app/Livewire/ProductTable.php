<?php

namespace App\Livewire;

use App\Concerns\RendersHoverBadgeList;
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
        return Product::query()->count();
    }

    public function setUp(): array
    {
        $this->showCheckBox('row_id');

        return [
            PowerGrid::header()->showSearchInput()->includeViewOnTop('components.shared.select-all-banner'),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        $products = DB::table('products')
            ->whereNull('products.deleted_at')
            ->selectRaw("
                CONCAT('simple_', products.id) as row_id,
                products.id,
                products.name,
                products.sku,
                'simple' as product_type,
                products.state as status,
                products.price,
                products.stock,
                products.created_at
            ");

        if (filled($this->search)) {
            $term = '%'.$this->search.'%';
            $products->where(function ($query) use ($term): void {
                $query->where('products.name', 'like', $term)
                    ->orWhere('products.sku', 'like', $term);
            });
        }

        $stock = data_get($this->filters, 'select.stock_raw');
        if ($stock === 'in_stock') {
            $products->where('products.stock', '>', 0);
        } elseif ($stock === 'out_of_stock') {
            $products->where('products.stock', '<=', 0);
        }

        if ($status = data_get($this->filters, 'select.status')) {
            $products->where('products.state', $status);
        }

        $date = data_get($this->filters, 'date.created_at') ?? data_get($this->filters, 'datetime.created_at');
        if ($date && data_get($date, 'start') && data_get($date, 'end')) {
            $products->whereBetween('products.created_at', [
                Carbon::parse($date['start'])->startOfDay(),
                Carbon::parse($date['end'])->endOfDay(),
            ]);
        }

        return $products;
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('row_id')
            ->add('name', fn ($row): string => '<a href="'.route('products.edit', ['productKey' => "simple_{$row->id}"]).'">'.e($row->name).'</a>')
            ->add('product_type', fn (): string => __('Simple'))
            ->add('status', fn ($row): string => ucfirst($row->status))
            ->add('price', fn ($row): string => config('app.currency_symbol').number_format((float) $row->price, 2))
            ->add('stock_raw', fn ($row) => $row->stock)
            ->add('stock', fn ($row): string => (float) $row->stock > 0
                ? '<span class="text-green-600">'.__('In Stock').' ('.(float) $row->stock.')</span>'
                : '<span class="text-red-500">'.__('Out of Stock').'</span>')
            ->add('created_at', fn ($row): string => Carbon::parse($row->created_at)->format('d/m/Y'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('Title'), 'name')->sortable(),
            Column::make(__('Type'), 'product_type'),
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
                ])->optionValue('value')->optionLabel('name')
                ->builder(fn (Builder $query, string $value) => $query),
            Filter::select('status', 'status')
                ->dataSource([
                    ['name' => __('Active'), 'value' => 'active'],
                    ['name' => __('Draft'), 'value' => 'draft'],
                    ['name' => __('Unavailable'), 'value' => 'unavailable'],
                ])->optionValue('value')->optionLabel('name')
                ->builder(fn (Builder $query, string $value) => $query),
            Filter::datepicker('created_at', 'created_at')->builder(fn (Builder $query, array $value) => $query),
        ];
    }

    public function actions(object $row): array
    {
        return [
            Button::add('edit')->slot(__('Edit'))->route('products.edit', ['productKey' => "simple_{$row->id}"]),
            Button::add('delete')->slot(__('Delete'))
                ->dispatch('deleteProduct', ['id' => $row->id, 'type' => 'simple'])
                ->confirm(__('Are you sure you want to delete this product?')),
        ];
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
        if (! $this->selectAllRecords && $this->checkboxValues === []) {
            Flux::toast(__('Please select at least one product.'), variant: 'warning');

            return;
        }

        $this->dispatch(
            'showExportModal',
            ids: $this->selectAllRecords ? null : $this->checkboxValues,
            all: $this->selectAllRecords,
        );
        $this->resetCheckboxes();
    }

    public function bulkDelete(): void
    {
        foreach ($this->checkboxValues as $rowId) {
            $product = Product::query()->find((int) str_replace('simple_', '', $rowId));
            $product?->translations()->delete();
            $product?->delete();
        }

        $this->resetCheckboxes();
        Flux::toast(__('Selected products deleted successfully.'), variant: 'success');
    }

    #[On('deleteProduct')]
    public function deleteProduct(int $id, string $type = 'simple'): void
    {
        $product = Product::query()->findOrFail($id);
        $product->translations()->delete();
        $product->delete();

        Flux::toast(__('Product deleted successfully.'), variant: 'success');
    }

    protected function resetCheckboxes(): void
    {
        $this->selectAllRecords = false;
        $this->checkboxValues = [];
        $this->checkboxAll = false;
        $this->dispatch('pgBulkActions::clear', $this->tableName);
    }
}

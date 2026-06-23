<?php

namespace App\Jobs;

use App\Exports\ProductExport;
use App\Models\Export;
use App\Models\MasterProduct;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        protected Export $export,
    ) {}

    public function handle(): void
    {
        $filters = $this->export->filters;
        $rowIds = $filters['ids'] ?? null;
        $locale = $filters['locale'] ?? 'en';
        $headingLocale = $filters['heading_locale'] ?? $locale;

        $total = $this->countTotalRows($rowIds);

        $this->export->update([
            'status' => 'processing',
            'total_rows' => $total,
        ]);

        $filename = 'products-'.now()->format('Y-m-d-His').'.xlsx';

        Excel::store(new ProductExport($rowIds, $locale, $headingLocale), $filename, 'local');

        $this->export
            ->addMedia(storage_path('app/private/'.$filename))
            ->usingFileName($filename)
            ->toMediaCollection('export');

        $this->export->update([
            'status' => 'completed',
        ]);
    }

    protected function countTotalRows(?array $rowIds): int
    {
        if ($rowIds === null) {
            return Product::whereNull('deleted_at')->count()
                + MasterProduct::whereNull('deleted_at')->count();
        }

        $simpleIds = [];
        $variableIds = [];

        foreach ($rowIds as $rowId) {
            [$type, $id] = explode('_', $rowId, 2);

            if ($type === 'simple') {
                $simpleIds[] = (int) $id;
            } else {
                $variableIds[] = (int) $id;
            }
        }

        $count = 0;

        if (! empty($simpleIds)) {
            $count += Product::whereNull('deleted_at')->whereIn('id', $simpleIds)->count();
        }

        if (! empty($variableIds)) {
            $count += MasterProduct::whereNull('deleted_at')->whereIn('id', $variableIds)->count();
        }

        return $count;
    }

    public function failed(Throwable $exception): void
    {
        $this->export->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }
}

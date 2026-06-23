<?php

namespace App\Jobs;

use App\Exports\MaterialExport;
use App\Models\Export;
use App\Models\Material;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExportMaterialsJob implements ShouldQueue
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
        $ids = $filters['ids'] ?? null;
        $locale = $filters['locale'] ?? 'en';
        $headingLocale = $filters['heading_locale'] ?? $locale;

        $query = Material::query();

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        $total = $query->count();

        $this->export->update([
            'status' => 'processing',
            'total_rows' => $total,
        ]);

        $filename = 'materials-'.now()->format('Y-m-d-His').'.xlsx';

        Excel::store(new MaterialExport($ids, $locale, $headingLocale), $filename, 'local');

        $this->export
            ->addMedia(storage_path('app/private/'.$filename))
            ->usingFileName($filename)
            ->toMediaCollection('export');

        $this->export->update([
            'status' => 'completed',
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->export->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Services\PrinterProductCompatibilitySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPrinterProductCompatibility implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $printerId = null,
        public ?int $productId = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Execute the job.
     */
    public function handle(PrinterProductCompatibilitySyncService $sync): void
    {
        if ($this->printerId !== null) {
            $sync->syncPrinter($this->printerId);
        }

        if ($this->productId !== null) {
            $sync->syncProduct($this->productId);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Services\PrinterProductCompatibilitySyncService;
use Illuminate\Console\Command;

class SyncPrinterProductCompatibilityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-printer-product-compatibility
                            {--truncate : Empty printer_product before rebuilding all printer compatibility}
                            {--printer= : Rebuild compatibility for one printer ID}
                            {--product= : Rebuild compatibility for one product ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync printer_product compatibility from Vanilo properties';

    /**
     * Execute the console command.
     */
    public function handle(PrinterProductCompatibilitySyncService $sync): int
    {
        $printerId = $this->option('printer');
        $productId = $this->option('product');

        if ($printerId !== null && $productId !== null) {
            $this->error('Use either --printer or --product, not both.');

            return self::FAILURE;
        }

        if ($printerId !== null) {
            $result = $sync->syncPrinter((int) $printerId);
            $this->info(sprintf(
                'Synced printer %d with %d compatible products.',
                $result['printer_id'],
                count($result['matched_product_ids'])
            ));

            return self::SUCCESS;
        }

        if ($productId !== null) {
            $result = $sync->syncProduct((int) $productId);
            $this->info(sprintf(
                'Synced product %d with %d compatible printers.',
                $result['product_id'],
                count($result['matched_printer_ids'])
            ));

            return self::SUCCESS;
        }

        $result = $sync->syncAll((bool) $this->option('truncate'));

        $this->info(sprintf(
            'Synced %d printers and affected %d products.',
            $result['printers'],
            $result['products']
        ));

        return self::SUCCESS;
    }
}

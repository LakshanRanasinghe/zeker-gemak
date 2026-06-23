<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UpdateS2BStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-s2b-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download S2B stock XML feed and update matching products stock in the database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $xmlUrl = config('products.s2b.xml_url');

        if (empty($xmlUrl)) {
            $this->error('S2B XML URL is not configured.');

            return self::FAILURE;
        }

        $this->info("Fetching XML from: {$xmlUrl}");

        try {
            $response = Http::get($xmlUrl);

            if (! $response->successful()) {
                $this->error("Failed to download XML from {$xmlUrl} (Status: {$response->status()})");

                return self::FAILURE;
            }

            $xmlContent = $response->body();
            // Strip UTF-8 BOM if present.
            if (str_starts_with($xmlContent, "\xEF\xBB\xBF")) {
                $xmlContent = substr($xmlContent, 3);
            }
            $xmlContent = trim($xmlContent);

            $xml = @simplexml_load_string($xmlContent);

            if ($xml === false) {
                $this->error('Failed to parse XML content. Ensure the feed is valid XML.');

                return self::FAILURE;
            }

            if (! isset($xml->VOORRADEN->VOORRAAD)) {
                $this->error('Invalid S2B XML structure: <VOORRADEN><VOORRAAD> nodes not found.');

                return self::FAILURE;
            }

            $this->info('XML parsed successfully. Loading products from database...');

            // Fetch products into memory keyed by lowercase SKU to avoid N+1 queries.
            $products = Product::query()
                ->whereNotNull('sku')
                ->select(['id', 'sku', 'stock'])
                ->get()
                ->keyBy(function ($product) {
                    return strtolower(trim((string) $product->sku));
                });

            $this->info('Updating stock levels in database...');

            $totalCount = 0;
            $matchedCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;

            DB::transaction(function () use (
                $xml,
                $products,
                &$totalCount,
                &$matchedCount,
                &$updatedCount,
                &$unchangedCount
            ): void {
                foreach ($xml->VOORRADEN->VOORRAAD as $item) {
                    $totalCount++;
                    $sku = trim((string) $item->VRD_ARTNUMMER);
                    $skuKey = strtolower($sku);
                    $xmlStock = (float) $item->VRD_AANTAL;

                    if ($sku !== '' && isset($products[$skuKey])) {
                        $matchedCount++;
                        $product = $products[$skuKey];

                        // Only write to the DB if stock actually changed.
                        if ((float) $product->stock !== $xmlStock) {
                            $product->stock = $xmlStock;
                            $product->save();
                            $updatedCount++;
                        } else {
                            $unchangedCount++;
                        }
                    }
                }
            });

            $this->info('Summary of S2B Stock Sync:');
            $this->info("- Total XML Items Processed: {$totalCount}");
            $this->info("- Matched Products: {$matchedCount}");
            $this->info("- Database Stocks Updated: {$updatedCount}");
            $this->info("- Database Stocks Unchanged: {$unchangedCount}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}

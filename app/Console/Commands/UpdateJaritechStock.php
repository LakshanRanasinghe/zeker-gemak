<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdateJaritechStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-jaritech-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download Jaritech stock CSV, update stock quantities matching product SKUs, and save the updated CSV.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $csvUrl = config('products.jaritech.csv_url');
        $outputPath = config('products.jaritech.csv_output_path');

        if (empty($csvUrl)) {
            $this->error('Jaritech CSV URL is not configured.');

            return self::FAILURE;
        }

        $this->info("Fetching CSV from: {$csvUrl}");

        $tempInputPath = tempnam(sys_get_temp_dir(), 'jaritech_in_');
        $tempOutputPath = tempnam(sys_get_temp_dir(), 'jaritech_out_');

        try {
            $response = Http::sink($tempInputPath)->get($csvUrl);

            if (!$response->successful()) {
                $this->error("Failed to download CSV from {$csvUrl} (Status: {$response->status()})");

                return self::FAILURE;

            }

            $this->info('CSV downloaded successfully. Loading product SKU mapping from database...');

            // Build a fast case-insensitive cache of product stocks by SKU to avoid N+1 queries.
            $skuStockMap = [];
            Product::query()
                ->whereNotNull('sku')
                ->select(['sku', 'stock'])
                ->chunk(1000, function ($products) use (&$skuStockMap): void {
                    foreach ($products as $product) {
                        $skuStockMap[strtolower(trim((string) $product->sku))] = $product->stock;
                    }
                });

            $this->info('Processing CSV file...');

            $inputFile = fopen($tempInputPath, 'r');
            $outputFile = fopen($tempOutputPath, 'w');

            if ($inputFile === false || $outputFile === false) {
                $this->error('Failed to open input or output stream.');
                if ($inputFile) {
                    fclose($inputFile);
                }
                if ($outputFile) {
                    fclose($outputFile);
                }

                return self::FAILURE;
            }

            $headers = fgetcsv($inputFile);
            if ($headers === false) {
                $this->error('CSV file is empty or invalid.');
                fclose($inputFile);
                fclose($outputFile);

                return self::FAILURE;
            }

            // Strip UTF-8 BOM if present on the first header field.
            if (str_starts_with($headers[0], "\xEF\xBB\xBF")) {
                $headers[0] = substr($headers[0], 3);
            }

            $originalArtNoIdx = array_search('ORIGINAL_ART_NO', $headers);
            $stockQtyIdx = array_search('STOCK_QTY', $headers);

            if ($originalArtNoIdx === false || $stockQtyIdx === false) {
                $this->error('Required columns (ORIGINAL_ART_NO, STOCK_QTY) not found in the CSV headers.');
                fclose($inputFile);
                fclose($outputFile);

                return self::FAILURE;
            }

            // Write the headers back to the output file.
            fputcsv($outputFile, $headers);

            $totalCount = 0;
            $matchedCount = 0;
            $unmatchedCount = 0;

            while (($row = fgetcsv($inputFile)) !== false) {
                $totalCount++;
                $originalArtNo = isset($row[$originalArtNoIdx]) ? trim((string) $row[$originalArtNoIdx]) : '';
                $skuKey = strtolower($originalArtNo);

                if ($originalArtNo !== '' && isset($skuStockMap[$skuKey])) {
                    $stock = $skuStockMap[$skuKey];
                    // Format stock to integer if it represents a whole number, otherwise keep as float.
                    $row[$stockQtyIdx] = ((float) $stock == (int) $stock) ? (int) $stock : (float) $stock;
                    $matchedCount++;
                } else {
                    $unmatchedCount++;
                }

                fputcsv($outputFile, $row);
            }

            fclose($inputFile);
            fclose($outputFile);

            $this->info("CSV processing complete. Saving to: {$outputPath}");
            File::ensureDirectoryExists(dirname($outputPath));

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }
            File::move($tempOutputPath, $outputPath);

            $this->info('Summary:');
            $this->info("- Total Rows Processed: {$totalCount}");
            $this->info("- Products Matched & Updated: {$matchedCount}");
            $this->info("- Unmatched Rows (kept original stock): {$unmatchedCount}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            if (File::exists($tempInputPath)) {
                File::delete($tempInputPath);
            }
            if (File::exists($tempOutputPath)) {
                File::delete($tempOutputPath);
            }
        }
    }
}

<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class PrinterProductCompatibilitySyncService
{
    public function __construct(
        protected PrinterProductMatcher $printerProductMatcher,
        protected ProductPrinterMatcher $productPrinterMatcher,
    ) {}

    /**
     * Rebuild compatibility for every printer.
     *
     * @return array{printers: int, products: int}
     */
    public function syncAll(bool $truncate = false): array
    {
        if ($truncate) {
            DB::table('printer_product')->truncate();
        }

        $printerCount = 0;
        $affectedProductIds = collect();

        Post::query()
            ->where('post_type', 'printer')
            ->with('propertyValues.property')
            ->chunkById(50, function (Collection $printers) use (&$printerCount, $affectedProductIds): void {
                foreach ($printers as $printer) {
                    $result = $this->syncPrinter($printer);
                    $printerCount++;
                    $affectedProductIds->push(...$result['affected_product_ids']);
                }
            });

        return [
            'printers' => $printerCount,
            'products' => $affectedProductIds->unique()->count(),
        ];
    }

    /**
     * Recalculate products compatible with one printer.
     *
     * @return array{printer_id: int, matched_product_ids: array<int, int>, affected_product_ids: array<int, int>}
     */
    public function syncPrinter(Post|int $printer): array
    {
        $printer = $printer instanceof Post
            ? $printer
            : Post::query()->findOrFail($printer);

        if ($printer->post_type !== 'printer') {
            return [
                'printer_id' => (int) $printer->getKey(),
                'matched_product_ids' => [],
                'affected_product_ids' => [],
            ];
        }

        $printer->loadMissing('propertyValues.property');

        $previousProductIds = $printer->products()
            ->pluck('products.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $matchedProductIds = $this->printerProductMatcher
            ->getMatchingProducts($printer)
            ->pluck('products.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $printer->products()->sync($matchedProductIds);

        $affectedProductIds = collect($previousProductIds)
            ->merge($matchedProductIds)
            ->unique()
            ->values()
            ->all();

        $this->reindexPrinter($printer);
        $this->reindexProducts($affectedProductIds);

        return [
            'printer_id' => (int) $printer->getKey(),
            'matched_product_ids' => array_values($matchedProductIds),
            'affected_product_ids' => $affectedProductIds,
        ];
    }

    /**
     * Recalculate printers compatible with one product.
     *
     * @return array{product_id: int, matched_printer_ids: array<int, int>, affected_printer_ids: array<int, int>}
     */
    public function syncProduct(Product|int $product): array
    {
        $product = $product instanceof Product
            ? $product
            : Product::query()->findOrFail($product);

        $product->loadMissing('propertyValues.property');

        $previousPrinterIds = $product->printers()
            ->pluck('posts.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $matchedPrinterIds = $this->productPrinterMatcher
            ->getMatchingPrinters($product)
            ->pluck('posts.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $product->printers()->sync($matchedPrinterIds);

        $affectedPrinterIds = collect($previousPrinterIds)
            ->merge($matchedPrinterIds)
            ->unique()
            ->values()
            ->all();

        $this->reindexProduct($product);
        $this->reindexPrinters($affectedPrinterIds);

        return [
            'product_id' => (int) $product->getKey(),
            'matched_printer_ids' => array_values($matchedPrinterIds),
            'affected_printer_ids' => $affectedPrinterIds,
        ];
    }

    /**
     * @param  array<int, int>  $productIds
     */
    protected function reindexProducts(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        try {
            Product::query()->whereKey($productIds)->get()->searchable();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<int, int>  $printerIds
     */
    protected function reindexPrinters(array $printerIds): void
    {
        if ($printerIds === []) {
            return;
        }

        try {
            Post::query()->whereKey($printerIds)->get()->searchable();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function reindexProduct(Product $product): void
    {
        try {
            $product->searchable();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function reindexPrinter(Post $printer): void
    {
        try {
            $printer->searchable();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

<?php

namespace App\Imports;

use App\Models\Product;
use App\Services\PrinterProductCompatibilitySyncService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterChunk;
use Vanilo\Properties\Models\Property;

class MappedProductImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    /**
     * @var array
     */
    protected $mapping;

    /**
     * Tracks the mapped properties for the current chunk.
     * Structure: ['SKU' => ['property-slug' => 'value', ...], ...]
     *
     * @var array
     */
    protected $chunkProperties = [];

    /**
     * @param  array  $mapping  The user-defined column mapping ['file_header' => 'database_column']
     */
    public function __construct(array $mapping)
    {
        // Filter out any unmapped items
        $this->mapping = array_filter($mapping);
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $rowArray = is_array($row) ? $row : $row->toArray();
            $mappedData = [];
            $metaData = [];

            foreach ($this->mapping as $fileHeader => $dbColumn) {
                if (array_key_exists($fileHeader, $rowArray)) {
                    $value = $rowArray[$fileHeader];

                    // Handle NOT NULL columns that should default to 0 if empty in the file
                    if (is_null($value) && in_array($dbColumn, ['stock', 'units_sold', 'priority'])) {
                        $value = 0;
                    }

                    if (str_starts_with($dbColumn, 'meta:')) {
                        $metaKey = substr($dbColumn, 5);
                        $metaData[$metaKey] = $value;
                    } else {
                        $mappedData[$dbColumn] = $value;
                    }
                }
            }

            if (! isset($mappedData['sku']) || empty($mappedData['sku'])) {
                continue;
            }

            $sku = (string) $mappedData['sku'];

            if (! empty($metaData)) {
                $this->chunkMetas[$sku] = $metaData;
            }

            Product::updateOrCreate(
                ['sku' => $sku],
                $mappedData
            );
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            AfterChunk::class => function (AfterChunk $event) {
                if (empty($this->chunkProperties)) {
                    return;
                }

                $skus = array_keys($this->chunkProperties);

                // Get the products by SKU after the chunk was upserted
                $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

                // Ensure all properties exist before syncing
                $allPropertySlugs = [];
                foreach ($this->chunkProperties as $properties) {
                    $allPropertySlugs = array_merge($allPropertySlugs, array_keys($properties));
                }
                $allPropertySlugs = array_unique($allPropertySlugs);

                // Create missing properties
                foreach ($allPropertySlugs as $slug) {
                    Property::firstOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => ucfirst(str_replace(['-', '_'], ' ', $slug)),
                            'type' => 'text',
                        ]
                    );
                }

                // Sync properties for each product using Vanilo's built-in method
                foreach ($products as $sku => $product) {
                    if (isset($this->chunkProperties[$sku])) {
                        // replacePropertyValuesByScalar handles:
                        // - Creating missing property values
                        // - Syncing the pivot table (no duplicates)
                        // - Removing old property values not in the array
                        $product->replacePropertyValuesByScalar($this->chunkProperties[$sku]);
                        app(PrinterProductCompatibilitySyncService::class)->syncProduct($product);
                    }
                }

                // Clear memory for the next chunk
                $this->chunkProperties = [];
            },
        ];
    }
}

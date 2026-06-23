<?php

namespace App\Services;

use App\Models\Post;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;

class PrinterPropertySyncService
{
    public function __construct(
        protected WooCommercePrinterPropertyMapper $mapper,
        protected PrinterProductCompatibilitySyncService $compatibilitySync,
    ) {}

    public function syncFromWooCommerceData(Post $post, array $data): int
    {
        return $this->syncPropertyPayload($post, $this->mapper->fromPrinterData($data));
    }

    /**
     * @param  array<string, array<int, string>>  $propertyPayload
     */
    protected function syncPropertyPayload(Post $post, array $propertyPayload): int
    {
        if ($propertyPayload === []) {
            $post->propertyValues()->sync([]);
            $this->compatibilitySync->syncPrinter($post);

            return 0;
        }

        $propertyValueIds = [];

        foreach ($propertyPayload as $slug => $values) {
            $property = Property::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace('-', ' ')->title()->toString(),
                    'type' => 'text',
                ]
            );

            foreach ($values as $value) {
                $propertyValueIds[] = PropertyValue::firstOrCreate(
                    [
                        'property_id' => $property->id,
                        'value' => $value,
                    ],
                    ['title' => $value]
                )->id;
            }
        }

        $propertyValueIds = array_values(array_unique($propertyValueIds));

        $post->propertyValues()->sync($propertyValueIds);
        $this->compatibilitySync->syncPrinter($post);

        return count($propertyValueIds);
    }
}

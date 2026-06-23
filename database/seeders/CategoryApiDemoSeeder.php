<?php

namespace Database\Seeders;

use App\Models\GroupProduct;
use App\Models\GroupProductItem;
use App\Models\MasterProduct;
use App\Models\Material;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;
use Vanilo\Foundation\Models\Taxon;
use Vanilo\Foundation\Models\Taxonomy;
use Vanilo\Properties\Models\Property;
use Vanilo\Properties\Models\PropertyValue;

class CategoryApiDemoSeeder extends Seeder
{
    public function run(): void
    {
        Product::disableSearchSyncing();
        MasterProduct::disableSearchSyncing();
        GroupProduct::disableSearchSyncing();

        try {
            $taxonomy = Taxonomy::query()->updateOrCreate(
                ['slug' => 'category'],
                ['name' => 'Category']
            );

            // Material taxons are synced from WooCommerce, not created here

            $labels = $this->upsertTaxon($taxonomy, 'labels', 'Labels');
            $ribbons = $this->upsertTaxon($taxonomy, 'ribbons', 'Ribbons');
            $shippingLabels = $this->upsertTaxon($taxonomy, 'shipping-labels', 'Shipping Labels', $labels);
            $freezerLabels = $this->upsertTaxon($taxonomy, 'freezer-labels', 'Freezer Labels', $labels);
            $waxRibbons = $this->upsertTaxon($taxonomy, 'wax-ribbons', 'Wax Ribbons', $ribbons);

            $thermalPaper = $this->upsertMaterial('thermal-paper', 'Thermal Paper');
            $freezerPaper = $this->upsertMaterial('freezer-paper', 'Freezer Paper');
            $polypropyleneFilm = $this->upsertMaterial('polypropylene-film', 'Polypropylene Film');
            $polyethyleneFilm = $this->upsertMaterial('polyethylene-film', 'Polyethylene Film');
            $polyesterRibbon = $this->upsertMaterial('polyester-ribbon-film', 'Polyester Ribbon Film');

            $this->upsertSimpleProduct(
                attributes: [
                    'name' => 'Demo Zebra Shipping Labels',
                    'title' => 'Demo Zebra Shipping Labels',
                    'subtitle' => 'Desktop direct thermal shipping labels',
                    'slug' => 'demo-zebra-shipping-labels',
                    'sku' => 'DEMO-LBL-001',
                    'price' => 14.50,
                    'original_price' => 17.00,
                    'stock' => 48,
                    'material_id' => $thermalPaper->id,
                    'state' => 'active',
                    'product_type' => 'simple',
                    'product_template' => 'label',
                    'excerpt' => 'Fast shipping label roll for desktop printers.',
                    'description' => 'Demo shipping label product for API and Elasticsearch testing.',
                    'content' => 'Great for browser, Postman, and filter testing.',
                    'product_information' => 'Core 25 mm, outer diameter 5 inch, gap detection.',
                ],
                meta: [
                    'brand' => 'zebra',
                    'material_code' => 'paper_white',
                    'finishing' => 'gloss',
                    'glue' => 'permanent',
                    'druktype' => 'direct_thermal',
                    'printer_type' => 'desktop',
                    'meta_width' => '100mm',
                    'meta_height' => '50mm',
                    'kern' => '25mm',
                    'buitendia' => '5_inch',
                    'detectie' => 'gap',
                    'merken' => 'zebra',
                ],
                taxons: [$labels, $shippingLabels],
                mediaSeed: 'demo-zebra-shipping-labels',
            );

            $this->upsertSimpleProduct(
                attributes: [
                    'name' => 'Demo Honeywell Freezer Labels',
                    'title' => 'Demo Honeywell Freezer Labels',
                    'subtitle' => 'Industrial freezer-safe label roll',
                    'slug' => 'demo-honeywell-freezer-labels',
                    'sku' => 'DEMO-LBL-002',
                    'price' => 19.75,
                    'original_price' => 23.50,
                    'stock' => 30,
                    'material_id' => $freezerPaper->id,
                    'state' => 'active',
                    'product_type' => 'simple',
                    'product_template' => 'label',
                    'excerpt' => 'Freezer-ready thermal transfer labels.',
                    'description' => 'Demo freezer label product with different filter values.',
                    'content' => 'Useful for category, brand, and width range tests.',
                    'product_information' => 'Core 40 mm, outer diameter 8 inch, black mark detection.',
                ],
                meta: [
                    'brand' => 'honeywell',
                    'material_code' => 'paper_kraft',
                    'finishing' => 'matte',
                    'glue' => 'freezer',
                    'druktype' => 'thermal_transfer',
                    'printer_type' => 'industrial',
                    'meta_width' => '50mm',
                    'meta_height' => '25mm',
                    'kern' => '40mm',
                    'buitendia' => '8_inch',
                    'detectie' => 'black_mark',
                    'merken' => 'honeywell',
                ],
                taxons: [$labels, $freezerLabels],
                mediaSeed: 'demo-honeywell-freezer-labels',
            );

            $this->upsertSimpleProduct(
                attributes: [
                    'name' => 'Demo TSC Polypropylene Labels',
                    'title' => 'Demo TSC Polypropylene Labels',
                    'subtitle' => 'Durable polypropylene label roll',
                    'slug' => 'demo-tsc-polypropylene-labels',
                    'sku' => 'DEMO-LBL-003',
                    'price' => 24.00,
                    'original_price' => 28.00,
                    'stock' => 18,
                    'material_id' => $polypropyleneFilm->id,
                    'state' => 'active',
                    'product_type' => 'simple',
                    'product_template' => 'label',
                    'excerpt' => 'Polypropylene labels for industrial use.',
                    'description' => 'Demo PP label product for advanced filter checks.',
                    'content' => 'Includes different width, height, and material combinations.',
                    'product_information' => 'Core 76 mm, outer diameter 10 inch, dual detection.',
                ],
                meta: [
                    'brand' => 'tsc',
                    'material_code' => 'pp_white',
                    'finishing' => 'satin',
                    'glue' => 'removable',
                    'druktype' => 'both',
                    'printer_type' => 'industrial',
                    'meta_width' => '76mm',
                    'meta_height' => '36mm',
                    'kern' => '76mm',
                    'buitendia' => '10_inch',
                    'detectie' => 'gap_black_mark',
                    'merken' => 'tsc',
                ],
                taxons: [$labels],
                mediaSeed: 'demo-tsc-polypropylene-labels',
            );

            $this->upsertSimpleProduct(
                attributes: [
                    'name' => 'Demo Citizen Wax Ribbon',
                    'title' => 'Demo Citizen Wax Ribbon',
                    'subtitle' => 'Wax ribbon for transfer printers',
                    'slug' => 'demo-citizen-wax-ribbon',
                    'sku' => 'DEMO-RBN-001',
                    'price' => 11.25,
                    'original_price' => 13.00,
                    'stock' => 52,
                    'material_id' => $polyesterRibbon->id,
                    'state' => 'active',
                    'product_type' => 'simple',
                    'product_template' => 'ribbon',
                    'excerpt' => 'Wax ribbon demo item for ribbon category testing.',
                    'description' => 'Demo ribbon product for search and category API verification.',
                    'content' => 'Lets you verify that non-label items also appear in the category.',
                    'product_information' => 'Desktop ribbon, wax coating, 110 mm width.',
                ],
                meta: [
                    'brand' => 'citizen',
                    'material_code' => 'pp_transparent',
                    'finishing' => 'varnish',
                    'glue' => 'ultra_removable',
                    'druktype' => 'thermal_transfer',
                    'printer_type' => 'desktop',
                    'meta_width' => '104mm',
                    'meta_height' => '76mm',
                    'kern' => '25mm',
                    'buitendia' => '5_inch',
                    'detectie' => 'continuous',
                    'merken' => 'citizen',
                ],
                taxons: [$ribbons, $waxRibbons],
                mediaSeed: 'demo-citizen-wax-ribbon',
            );

            $this->upsertMasterProduct(
                attributes: [
                    'name' => 'Demo Zebra PP Label Roll',
                    'title' => 'Demo Zebra PP Label Roll',
                    'subtitle' => 'Variable PP label roll with multiple SKUs',
                    'slug' => 'demo-zebra-pp-label-roll',
                    'price' => 27.00,
                    'original_price' => 31.00,
                    'material_id' => $polypropyleneFilm->id,
                    'state' => 'active',
                    'product_type' => 'variable',
                    'product_template' => 'label',
                    'excerpt' => 'Variable product demo with searchable variant SKUs.',
                    'description' => 'Use this to verify variable products, variant SKUs, and detail payloads.',
                    'content' => 'Each variant has a distinct SKU and stock quantity.',
                    'product_information' => 'Polypropylene label roll with multiple variant sizes.',
                ],
                meta: [
                    'brand' => 'zebra',
                    'material_code' => 'pp_transparent',
                    'finishing' => 'gloss',
                    'glue' => 'high_tack',
                    'druktype' => 'thermal_transfer',
                    'printer_type' => 'industrial',
                    'meta_width' => '100mm',
                    'meta_height' => '50mm',
                    'kern' => '76mm',
                    'buitendia' => '8_inch',
                    'detectie' => 'gap',
                    'merken' => 'zebra',
                ],
                taxons: [$labels, $shippingLabels],
                variants: [
                    [
                        'name' => 'Demo Zebra PP Label Roll 100x50',
                        'sku' => 'DEMO-VAR-001-A',
                        'price' => 27.00,
                        'original_price' => 31.00,
                        'stock' => 12,
                        'state' => 'active',
                        'properties' => [
                            'Roll Size' => '100 x 50 mm',
                            'Core' => '76 mm',
                        ],
                    ],
                    [
                        'name' => 'Demo Zebra PP Label Roll 100x75',
                        'sku' => 'DEMO-VAR-001-B',
                        'price' => 29.00,
                        'original_price' => 34.00,
                        'stock' => 6,
                        'state' => 'active',
                        'properties' => [
                            'Roll Size' => '100 x 75 mm',
                            'Core' => '76 mm',
                        ],
                    ],
                ],
                mediaSeed: 'demo-zebra-pp-label-roll',
            );

            $this->upsertMasterProduct(
                attributes: [
                    'name' => 'Demo SATO Industrial Shipping Labels',
                    'title' => 'Demo SATO Industrial Shipping Labels',
                    'subtitle' => 'Variable industrial label range',
                    'slug' => 'demo-sato-industrial-shipping-labels',
                    'price' => 32.00,
                    'original_price' => 36.50,
                    'material_id' => $polyethyleneFilm->id,
                    'state' => 'active',
                    'product_type' => 'variable',
                    'product_template' => 'label',
                    'excerpt' => 'Variable industrial shipping labels for filter and SKU tests.',
                    'description' => 'Demo variable product designed for category, search, and variant API checks.',
                    'content' => 'Supports browser testing for detail pages and filter combinations.',
                    'product_information' => 'Industrial shipping labels with multiple purchasable variants.',
                ],
                meta: [
                    'brand' => 'sato',
                    'material_code' => 'pe_white',
                    'finishing' => 'matte',
                    'glue' => 'permanent',
                    'druktype' => 'both',
                    'printer_type' => 'industrial',
                    'meta_width' => '50mm',
                    'meta_height' => '76mm',
                    'kern' => '40mm',
                    'buitendia' => '12_inch',
                    'detectie' => 'black_mark',
                    'merken' => 'sato',
                ],
                taxons: [$labels, $shippingLabels],
                variants: [
                    [
                        'name' => 'Demo SATO Shipping Labels 50x76',
                        'sku' => 'DEMO-VAR-002-A',
                        'price' => 32.00,
                        'original_price' => 36.50,
                        'stock' => 9,
                        'state' => 'active',
                        'properties' => [
                            'Roll Size' => '50 x 76 mm',
                            'Printer Class' => 'Industrial',
                        ],
                    ],
                    [
                        'name' => 'Demo SATO Shipping Labels 76x76',
                        'sku' => 'DEMO-VAR-002-B',
                        'price' => 35.00,
                        'original_price' => 39.00,
                        'stock' => 4,
                        'state' => 'active',
                        'properties' => [
                            'Roll Size' => '76 x 76 mm',
                            'Printer Class' => 'Industrial',
                        ],
                    ],
                ],
                mediaSeed: 'demo-sato-industrial-shipping-labels',
            );

            $this->seedBulkDemoProducts(
                labels: $labels,
                ribbons: $ribbons,
                shippingLabels: $shippingLabels,
                freezerLabels: $freezerLabels,
                waxRibbons: $waxRibbons,
                thermalPaper: $thermalPaper,
                freezerPaper: $freezerPaper,
                polypropyleneFilm: $polypropyleneFilm,
                polyethyleneFilm: $polyethyleneFilm,
                polyesterRibbon: $polyesterRibbon,
            );

            $this->seedDemoGroupProducts($labels, $ribbons, $freezerLabels, $waxRibbons);
        } finally {
            Product::enableSearchSyncing();
            MasterProduct::enableSearchSyncing();
            GroupProduct::enableSearchSyncing();
        }

        Artisan::call('scout:import', ['model' => Product::class]);
        Artisan::call('scout:import', ['model' => MasterProduct::class]);
        Artisan::call('scout:import', ['model' => GroupProduct::class]);
    }

    protected function upsertTaxon(Taxonomy $taxonomy, string $slug, string $name, ?Taxon $parent = null): Taxon
    {
        return Taxon::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'taxonomy_id' => $taxonomy->id,
                'parent_id' => $parent?->id,
                'name' => $name,
            ]
        );
    }

    protected function upsertMaterial(string $slug, string $title): Material
    {
        return Material::query()->updateOrCreate(
            ['slug' => $slug],
            ['title' => $title]
        );
    }

    protected function upsertSimpleProduct(array $attributes, array $meta, array $taxons, string $mediaSeed): Product
    {
        /** @var Product $product */
        $product = Product::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            $attributes
        );

        $product->taxons()->sync(collect($taxons)->pluck('id')->all());
        $this->syncMetas($product, $meta);
        $this->syncMedia($product, $mediaSeed);

        return $product;
    }

    protected function upsertMasterProduct(array $attributes, array $meta, array $taxons, array $variants, string $mediaSeed): MasterProduct
    {
        /** @var MasterProduct $product */
        $product = MasterProduct::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            $attributes
        );

        $product->taxons()->sync(collect($taxons)->pluck('id')->all());
        $this->syncMetas($product, $meta);
        $this->syncVariants($product, $variants);
        $this->syncMedia($product, $mediaSeed);

        return $product;
    }

    protected function syncMetas(Product|MasterProduct $product, array $meta): void
    {
        foreach ($meta as $key => $value) {
            $product->metas()->updateOrCreate(
                ['meta_key' => $key],
                ['meta_value' => $value]
            );
        }

        $product->metas()
            ->whereNotIn('meta_key', array_keys($meta))
            ->delete();
    }

    protected function syncVariants(MasterProduct $product, array $variants): void
    {
        $variantSkus = collect($variants)->pluck('sku')->all();
        $propertyValueMap = $this->syncVariantProperties($product, $variants);

        $product->variants()
            ->whereNotIn('sku', $variantSkus)
            ->get()
            ->each(function ($variant) {
                $variant->propertyValues()->detach();
                $variant->delete();
            });

        foreach ($variants as $variant) {
            $existing = $product->variants()->where('sku', $variant['sku'])->first();
            $variantAttributes = collect($variant)->except('properties')->all();

            if ($existing) {
                $existing->fill($variantAttributes);
                $existing->save();
                $existing->propertyValues()->detach();

                $this->attachVariantPropertyValues($existing, $variant['properties'] ?? [], $propertyValueMap);

                continue;
            }

            $newVariant = $product->createVariant($variantAttributes);
            $this->attachVariantPropertyValues($newVariant, $variant['properties'] ?? [], $propertyValueMap);
        }
    }

    protected function syncVariantProperties(MasterProduct $product, array $variants): array
    {
        $product->propertyValues()->detach();

        $propertyValueMap = [];

        foreach ($variants as $variant) {
            foreach (($variant['properties'] ?? []) as $propertyName => $propertyValueTitle) {
                $property = Property::firstOrCreate(
                    ['slug' => Str::slug($propertyName)],
                    ['name' => $propertyName, 'type' => 'text']
                );

                $propertyValue = PropertyValue::firstOrCreate(
                    [
                        'property_id' => $property->id,
                        'value' => Str::slug($propertyValueTitle),
                    ],
                    ['title' => $propertyValueTitle]
                );

                $propertyValueMap[$propertyName][$propertyValueTitle] = $propertyValue;
            }
        }

        foreach ($propertyValueMap as $values) {
            foreach ($values as $propertyValue) {
                $product->addPropertyValue($propertyValue);
            }
        }

        return $propertyValueMap;
    }

    protected function attachVariantPropertyValues($variant, array $properties, array $propertyValueMap): void
    {
        foreach ($properties as $propertyName => $propertyValueTitle) {
            if (isset($propertyValueMap[$propertyName][$propertyValueTitle])) {
                $variant->addPropertyValue($propertyValueMap[$propertyName][$propertyValueTitle]);
            }
        }
    }

    protected function downloadImageForSeed(string $mediaSeed): ?string
    {
        try {
            $url = "https://picsum.photos/seed/{$mediaSeed}/1200/900";
            $response = Http::timeout(15)->get($url);

            if ($response->successful()) {
                $path = tempnam(sys_get_temp_dir(), "demo-{$mediaSeed}-").'.jpg';
                file_put_contents($path, $response->body());

                return $path;
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->command?->warn("Could not download image for {$mediaSeed}.");
        }

        return null;
    }

    protected function syncMedia(Product|MasterProduct|GroupProduct $product, string $mediaSeed): void
    {
        $product->clearMediaCollection('main');
        $product->clearMediaCollection('gallery');

        $mainImagePath = $this->downloadImageForSeed($mediaSeed);

        if (! $mainImagePath) {
            return;
        }

        try {
            $product
                ->addMedia($mainImagePath)
                ->usingName(Str::headline($mediaSeed).' Main')
                ->usingFileName("{$mediaSeed}-main.jpg")
                ->toMediaCollection('main');

            foreach ([1, 2] as $index) {
                $galleryImagePath = $this->downloadImageForSeed("{$mediaSeed}-gallery-{$index}");

                if (! $galleryImagePath) {
                    continue;
                }

                $product
                    ->addMedia($galleryImagePath)
                    ->usingName(Str::headline($mediaSeed)." Gallery {$index}")
                    ->usingFileName("{$mediaSeed}-gallery-{$index}.jpg")
                    ->toMediaCollection('gallery');
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->command?->warn("Could not attach images for {$mediaSeed}.");
        }
    }

    protected function seedDemoGroupProducts(Taxon $labels, Taxon $ribbons, Taxon $freezerLabels, Taxon $waxRibbons): void
    {
        $zebraShippingLabel = Product::query()->where('slug', 'demo-zebra-shipping-labels')->first();
        $honeywellFreezerLabel = Product::query()->where('slug', 'demo-honeywell-freezer-labels')->first();
        $citizenWaxRibbon = Product::query()->where('slug', 'demo-citizen-wax-ribbon')->first();
        $tscPolypropylene = Product::query()->where('slug', 'demo-tsc-polypropylene-labels')->first();

        // Bulk demo products for GRP-003 and GRP-004
        $bulkHoneywellLabel = Product::query()->where('slug', 'demo-honeywell-002')->first();
        $bulkSatoLabel = Product::query()->where('slug', 'demo-sato-005')->first();
        $bulkZebraLabel = Product::query()->where('slug', 'demo-zebra-001')->first();
        $bulkTscRibbon = Product::query()->where('slug', 'demo-tsc-003')->first();

        if ($zebraShippingLabel && $honeywellFreezerLabel) {
            $this->upsertGroupProduct(
                attributes: [
                    'name' => 'Demo Label Starter Bundle',
                    'title' => 'Demo Label Starter Bundle',
                    'subtitle' => 'Shipping and freezer labels combo pack',
                    'slug' => 'demo-label-starter-bundle',
                    'sku' => 'DEMO-GRP-001',
                    'excerpt' => 'A curated combo of shipping and freezer labels for demo purposes.',
                    'description' => 'Demo group product bundling shipping and freezer label rolls.',
                    'content' => 'Ideal for testing group product API endpoints and search filters.',
                    'product_information' => 'Includes one roll of shipping labels and one roll of freezer labels.',
                    'state' => 'active',
                    'discount' => 10.0,
                    'product_template' => 'label',
                ],
                items: [
                    ['product' => $zebraShippingLabel, 'quantity' => 1],
                    ['product' => $honeywellFreezerLabel, 'quantity' => 1],
                ],
                taxons: [$labels],
                mediaSeed: 'demo-label-starter-bundle',
            );
        }

        if ($citizenWaxRibbon && $tscPolypropylene) {
            $this->upsertGroupProduct(
                attributes: [
                    'name' => 'Demo Ribbon and Label Pro Pack',
                    'title' => 'Demo Ribbon and Label Pro Pack',
                    'subtitle' => 'Wax ribbon and polypropylene labels bundle',
                    'slug' => 'demo-ribbon-label-pro-pack',
                    'sku' => 'DEMO-GRP-002',
                    'excerpt' => 'Professional combo bundle of wax ribbon and PP label rolls.',
                    'description' => 'Demo group product for ribbon and label combo API testing.',
                    'content' => 'Supports filter, variant, and group product endpoint verification.',
                    'product_information' => 'Includes one wax ribbon and one polypropylene label roll.',
                    'state' => 'active',
                    'discount' => 12.5,
                    'product_template' => 'ribbon',
                ],
                items: [
                    ['product' => $citizenWaxRibbon, 'quantity' => 1],
                    ['product' => $tscPolypropylene, 'quantity' => 2],
                ],
                taxons: [$ribbons, $labels],
                mediaSeed: 'demo-ribbon-label-pro-pack',
            );
        }

        if ($bulkHoneywellLabel && $bulkSatoLabel) {
            $this->upsertGroupProduct(
                attributes: [
                    'name' => 'Demo Freezer Label Value Pack',
                    'title' => 'Demo Freezer Label Value Pack',
                    'subtitle' => 'Multi-brand freezer label bundle',
                    'slug' => 'demo-freezer-label-value-pack',
                    'sku' => 'DEMO-GRP-003',
                    'excerpt' => 'Value bundle of Honeywell and SATO freezer-grade label rolls.',
                    'description' => 'Demo group product combining two freezer-compatible label rolls from different brands.',
                    'content' => 'Designed for testing multi-brand group products and freezer category filters.',
                    'product_information' => 'Two freezer-safe label rolls for cold storage environments.',
                    'state' => 'active',
                    'discount' => 8.0,
                    'product_template' => 'label',
                ],
                items: [
                    ['product' => $bulkHoneywellLabel, 'quantity' => 2],
                    ['product' => $bulkSatoLabel, 'quantity' => 1],
                ],
                taxons: [$labels, $freezerLabels],
                mediaSeed: 'demo-freezer-label-value-pack',
            );
        }

        if ($bulkZebraLabel && $citizenWaxRibbon && $tscPolypropylene) {
            $this->upsertGroupProduct(
                attributes: [
                    'name' => 'Demo Industrial All-in-One Kit',
                    'title' => 'Demo Industrial All-in-One Kit',
                    'subtitle' => 'Complete industrial label and ribbon bundle',
                    'slug' => 'demo-industrial-all-in-one-kit',
                    'sku' => 'DEMO-GRP-004',
                    'excerpt' => 'All-in-one kit with shipping labels, PP labels, and a wax ribbon roll.',
                    'description' => 'Demo group product for testing a full industrial label and ribbon combo set.',
                    'content' => 'Covers shipping, polypropylene, and ribbon category filters in a single product.',
                    'product_information' => 'One Zebra shipping label roll, one PP label roll, and one wax ribbon.',
                    'state' => 'active',
                    'discount' => 15.0,
                    'product_template' => 'label',
                ],
                items: [
                    ['product' => $bulkZebraLabel, 'quantity' => 1],
                    ['product' => $tscPolypropylene, 'quantity' => 1],
                    ['product' => $citizenWaxRibbon, 'quantity' => 1],
                ],
                taxons: [$labels, $ribbons, $waxRibbons],
                mediaSeed: 'demo-industrial-all-in-one-kit',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{product: Product, quantity: int}>  $items
     * @param  array<int, Taxon>  $taxons
     */
    protected function upsertGroupProduct(array $attributes, array $items, array $taxons, string $mediaSeed): GroupProduct
    {
        /** @var GroupProduct $group */
        $group = GroupProduct::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            collect($attributes)->except(['discount'])->all()
        );

        // Set discount separately to allow price auto-calculation in model boot
        $group->discount = $attributes['discount'] ?? 0;

        // Sync items
        $group->items()->delete();

        foreach ($items as $itemData) {
            GroupProductItem::create([
                'group_product_id' => $group->id,
                'product_id' => $itemData['product']->id,
                'quantity' => $itemData['quantity'],
            ]);
        }

        $group->syncPrice();

        $group->taxons()->sync(collect($taxons)->pluck('id')->all());
        $this->syncMedia($group, $mediaSeed);

        return $group;
    }

    protected function seedBulkDemoProducts(
        Taxon $labels,
        Taxon $ribbons,
        Taxon $shippingLabels,
        Taxon $freezerLabels,
        Taxon $waxRibbons,
        Material $thermalPaper,
        Material $freezerPaper,
        Material $polypropyleneFilm,
        Material $polyethyleneFilm,
        Material $polyesterRibbon,
    ): void {
        $brands = [
            'zebra',
            'honeywell',
            'tsc',
            'citizen',
            'sato',
            'dymo',
            'brother',
            'avery',
            'intermec',
            'datamax',
        ];

        $materials = [
            $thermalPaper,
            $freezerPaper,
            $polypropyleneFilm,
            $polyethyleneFilm,
            $polyesterRibbon,
        ];

        $taxonPools = [
            [$labels, $shippingLabels],
            [$labels, $freezerLabels],
            [$labels],
            [$ribbons, $waxRibbons],
            [$ribbons],
        ];

        $widths = ['25mm', '50mm', '76mm', '100mm', '104mm'];
        $heights = ['25mm', '36mm', '50mm', '76mm', '100mm'];
        $cores = ['25mm', '40mm', '76mm'];
        $outerDiameters = ['5_inch', '8_inch', '10_inch', '12_inch'];
        $detectionModes = ['gap', 'black_mark', 'continuous', 'gap_black_mark'];
        $glues = ['permanent', 'removable', 'freezer', 'high_tack', 'ultra_removable'];
        $finishes = ['gloss', 'matte', 'satin', 'varnish'];

        for ($i = 1; $i <= 24; $i++) {
            $brand = $brands[($i - 1) % count($brands)];
            $material = $materials[($i - 1) % count($materials)];
            $taxons = $taxonPools[($i - 1) % count($taxonPools)];
            $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $isRibbon = collect($taxons)->contains(fn (Taxon $taxon) => $taxon->slug === 'ribbons' || $taxon->slug === 'wax-ribbons');

            $template = $isRibbon ? 'ribbon' : 'label';
            $name = $isRibbon
                ? 'Demo '.Str::headline($brand).' Ribbon '.$suffix
                : 'Demo '.Str::headline($brand).' Label '.$suffix;

            $slug = 'demo-'.$brand.'-'.$suffix;
            $price = 9.50 + (($i % 20) * 1.35);

            $this->upsertSimpleProduct(
                attributes: [
                    'name' => $name,
                    'title' => $name,
                    'subtitle' => $isRibbon
                        ? 'Bulk demo ribbon product'
                        : 'Bulk demo label product',
                    'slug' => $slug,
                    'sku' => 'DEMO-'.strtoupper($brand).'-'.$suffix,
                    'price' => round($price, 2),
                    'original_price' => round($price + 4.25, 2),
                    'stock' => 10 + ($i % 60),
                    'material_id' => $material->id,
                    'state' => 'active',
                    'product_type' => 'simple',
                    'product_template' => $template,
                    'excerpt' => 'Bulk demo product for category testing.',
                    'description' => 'Automatically generated demo product for API, search, and filter testing.',
                    'content' => 'This product is part of the bulk demo category seeder.',
                    'product_information' => 'Generated product #'.$suffix.' for testing.',
                ],
                meta: [
                    'brand' => $brand,
                    'material_code' => 'bulk_'.$material->slug,
                    'finishing' => $finishes[($i - 1) % count($finishes)],
                    'glue' => $glues[($i - 1) % count($glues)],
                    'druktype' => $isRibbon ? 'thermal_transfer' : (($i % 2 === 0) ? 'direct_thermal' : 'thermal_transfer'),
                    'printer_type' => ($i % 3 === 0) ? 'industrial' : 'desktop',
                    'meta_width' => $widths[($i - 1) % count($widths)],
                    'meta_height' => $heights[($i - 1) % count($heights)],
                    'kern' => $cores[($i - 1) % count($cores)],
                    'buitendia' => $outerDiameters[($i - 1) % count($outerDiameters)],
                    'detectie' => $detectionModes[($i - 1) % count($detectionModes)],
                    'merken' => $brand,
                ],
                taxons: $taxons,
                mediaSeed: $slug,
            );
        }
    }
}

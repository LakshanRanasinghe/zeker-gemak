<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use XMLWriter;

class GenerateGoogleMerchantFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-google-merchant-feed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the Google Merchant Center product XML feed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetPath = public_path('xmlfeed.xml');
        $temporaryPath = $targetPath.'.tmp';

        File::ensureDirectoryExists(dirname($targetPath));

        $writer = new XMLWriter;
        $writer->openUri($temporaryPath);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);
        $writer->setIndentString('  ');

        $writer->startElement('rss');
        $writer->writeAttribute('version', '2.0');
        $writer->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $writer->startElement('channel');
        $writer->writeElement('title', config('products.merchant_feed.title'));
        $writer->writeElement('link', $this->storefrontUrl());
        $writer->writeElement('description', config('products.merchant_feed.description'));

        $exported = 0;

        Product::query()
            ->with('media')
            ->where('state', 'active')
            ->where('product_type', 'simple')
            ->whereNotNull('sku')
            ->where('price', '>', 0)
            ->select([
                'id',
                'name',
                'title',
                'slug',
                'sku',
                'price',
                'excerpt',
                'description',
                'content',
                'make',
                'stock',
                'gtin',
                'length',
                'delivery_dates_in_stock',
                'delivery_dates_no_stock',
            ])
            ->chunkById(200, function ($products) use ($writer, &$exported): void {
                foreach ($products as $product) {
                    $this->writeProduct($writer, $product);
                    $exported++;
                }
            });

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        File::move($temporaryPath, $targetPath);

        $this->info("Generated Google Merchant feed with {$exported} products.");

        return self::SUCCESS;
    }

    private function writeProduct(XMLWriter $writer, Product $product): void
    {
        $title = $this->title($product);
        $description = $this->description($product, $title);
        $price = number_format((float) $product->price, 2, '.', '').' EUR';
        $hasGtin = filled($product->gtin);

        $writer->startElement('item');
        $writer->writeElementNs('g', 'id', null, (string) $product->sku);
        $writer->writeElementNs('g', 'title', null, $title);
        $writer->writeElementNs('g', 'description', null, $description);
        $writer->writeElementNs('g', 'link', null, $this->productUrl($product));

        if ($imageUrl = $this->imageUrl($product)) {
            $writer->writeElementNs('g', 'image_link', null, $imageUrl);
        }

        $writer->writeElementNs('g', 'condition', null, 'new');
        $writer->writeElementNs('g', 'availability', null, (float) $product->stock > 0 ? 'in_stock' : 'out_of_stock');
        $writer->writeElementNs('g', 'price', null, $price);

        if ($hasGtin) {
            $writer->writeElementNs('g', 'gtin', null, (string) $product->gtin);
        }

        $writer->writeElementNs('g', 'identifier_exists', null, $hasGtin ? 'yes' : 'no');
        $writer->writeElementNs('g', 'mpn', null, (string) $product->sku);
        $writer->writeElementNs('g', 'brand', null, $this->brand($product));

        foreach (['NL', 'BE'] as $country) {
            $this->writeShipping($writer, $country, (float) $product->price, $product);
        }

        $this->writeHandling($writer, $product);

        $writer->endElement();
    }

    private function writeShipping(XMLWriter $writer, string $country, float $price, Product $product): void
    {
        $writer->startElementNs('g', 'shipping', null);
        $writer->writeElementNs('g', 'country', null, $country);
        $writer->writeElementNs('g', 'service', null, 'Standard');
        $writer->writeElementNs('g', 'price', null, $this->shippingPrice($price, $product));
        $writer->writeElementNs('g', 'min_transit_time', null, '1');
        $writer->writeElementNs('g', 'max_transit_time', null, '1');
        $writer->endElement();
    }

    private function writeHandling(XMLWriter $writer, Product $product): void
    {
        $inStock = (float) $product->stock > 0;
        $maxHandling = $inStock
            ? (int) ($product->delivery_dates_in_stock ?? 1)
            : (int) ($product->delivery_dates_no_stock ?? 5);

        $writer->writeElementNs('g', 'min_handling_time', null, $inStock ? '0' : '2');
        $writer->writeElementNs('g', 'max_handling_time', null, (string) max($inStock ? 1 : 2, $maxHandling));
        $writer->writeElementNs('g', 'handling_cutoff_time', null, '13:00');
        $writer->writeElementNs('g', 'handling_cutoff_timezone', null, 'Europe/Amsterdam');
    }

    private function shippingPrice(float $price, Product $product): string
    {
        if ((float) $product->length > 1600) {
            return '30.00 EUR';
        }

        if ($price >= 75) {
            return '0.00 EUR';
        }

        return '5.49 EUR';
    }

    private function title(Product $product): string
    {
        return Str::limit((string) ($product->title ?: $product->name ?: $product->sku), 150, '');
    }

    private function description(Product $product, string $fallback): string
    {
        $description = collect([
            $product->description,
            $product->excerpt,
            $product->content,
            $fallback,
        ])->first(fn ($value) => filled($value));

        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $description))), 5000, '');
    }

    private function brand(Product $product): string
    {
        $brandProperty = $product->propertyValues->first(fn ($val) => in_array($val->property->slug, ['brand', 'merk', 'product-brand', 'product_brand']));
        if ($brandProperty) {
            return (string) $brandProperty->title;
        }

        return (string) config('products.merchant_feed.brand');
    }

    private function productUrl(Product $product): string
    {
        $slug = filled($product->slug)
            ? (string) $product->slug
            : Str::slug((string) ($product->name ?: $product->sku));

        return $this->storefrontUrl().'/products/'.$slug;
    }

    private function imageUrl(Product $product): ?string
    {
        if (! method_exists($product, 'getFirstMediaUrl')) {
            return null;
        }

        $url = $product->getFirstMediaUrl('main');

        return Str::startsWith($url, ['http://', 'https://']) ? $url : null;
    }

    private function storefrontUrl(): string
    {
        return rtrim((string) config('products.merchant_feed.storefront_url'), '/');
    }
}

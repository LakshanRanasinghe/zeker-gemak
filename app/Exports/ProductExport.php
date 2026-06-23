<?php

namespace App\Exports;

use App\Models\MasterProduct;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromCollection, WithHeadings
{
    protected static array $configKeys = [
        'brand',
        'material_code',
        'finishing',
        'glue',
        'druktype',
        'printer_type',
        'meta_width',
        'meta_height',
        'kern',
        'buitendia',
        'detectie',
        'merken',
    ];

    protected static array $headingTranslations = [
        'en' => [
            'ID' => 'ID',
            'Product Type' => 'Product Type',
            'Name' => 'Name',
            'Title' => 'Title',
            'Subtitle' => 'Subtitle',
            'Slug' => 'Slug',
            'SKU' => 'SKU',
            'Article Number' => 'Article Number',
            'State' => 'State',
            'Price' => 'Price',
            'Original Price' => 'Original Price',
            'Stock' => 'Stock',
            'Material' => 'Material',
            'Categories' => 'Categories',
            'Product Template' => 'Product Template',
            'Description' => 'Description',
            'Excerpt' => 'Excerpt',
            'Content' => 'Content',
            'Product Information' => 'Product Information',
            'Material Information' => 'Material Information',
            'Packaging Unit' => 'Packaging Unit',
            'Delivery Days (In Stock)' => 'Delivery Days (In Stock)',
            'Delivery Days (No Stock)' => 'Delivery Days (No Stock)',
            'Packing Group' => 'Packing Group',
            'Weight' => 'Weight',
            'Height' => 'Height',
            'Width' => 'Width',
            'Length' => 'Length',
            'Meta Fields' => 'Meta Fields',
            'Main Image' => 'Main Image',
            'Gallery Images' => 'Gallery Images',
            'Variant SKU' => 'Variant SKU',
            'Variant Price' => 'Variant Price',
            'Variant Stock' => 'Variant Stock',
            'Variant Attributes' => 'Variant Attributes',
            'Created At' => 'Created At',
            'Updated At' => 'Updated At',
        ],
        'nl' => [
            'ID' => 'ID',
            'Product Type' => 'Producttype',
            'Name' => 'Naam',
            'Title' => 'Titel',
            'Subtitle' => 'Ondertitel',
            'Slug' => 'Slug',
            'SKU' => 'SKU',
            'Article Number' => 'Artikelnummer',
            'State' => 'Status',
            'Price' => 'Prijs',
            'Original Price' => 'Originele prijs',
            'Stock' => 'Voorraad',
            'Material' => 'Materiaal',
            'Categories' => 'Categorieën',
            'Product Template' => 'Productsjabloon',
            'Description' => 'Beschrijving',
            'Excerpt' => 'Samenvatting',
            'Content' => 'Inhoud',
            'Product Information' => 'Productinformatie',
            'Material Information' => 'Materiaalinformatie',
            'Packaging Unit' => 'Verpakkingseenheid',
            'Delivery Days (In Stock)' => 'Levertijd (Op voorraad)',
            'Delivery Days (No Stock)' => 'Levertijd (Niet op voorraad)',
            'Packing Group' => 'Verpakkingsgroep',
            'Weight' => 'Gewicht',
            'Height' => 'Hoogte',
            'Width' => 'Breedte',
            'Length' => 'Lengte',
            'Meta Fields' => 'Metavelden',
            'Main Image' => 'Hoofdafbeelding',
            'Gallery Images' => 'Galerij-afbeeldingen',
            'Variant SKU' => 'Variant SKU',
            'Variant Price' => 'Variant prijs',
            'Variant Stock' => 'Variant voorraad',
            'Variant Attributes' => 'Variant attributen',
            'Created At' => 'Aangemaakt op',
            'Updated At' => 'Bijgewerkt op',
        ],
    ];

    public function __construct(
        protected ?array $rowIds = null,
        protected string $locale = 'en',
        protected string $headingLocale = 'en',
    ) {}

    public function collection(): Collection
    {
        [$simpleIds, $variableIds] = $this->parseRowIds();

        $rows = collect();

        $simpleProducts = $this->querySimpleProducts($simpleIds);
        foreach ($simpleProducts as $product) {
            $rows->push($this->mapSimpleProduct($product));
        }

        $variableProducts = $this->queryVariableProducts($variableIds);
        foreach ($variableProducts as $master) {
            $variants = $master->variants->whereNull('deleted_at');
            $rows->push($this->mapVariableProduct($master, $variants));
        }

        return $rows;
    }

    public function headings(): array
    {
        $isBoth = $this->locale === 'both';

        $headings = [$this->h('ID'), $this->h('Product Type')];

        if ($isBoth) {
            $headings = array_merge($headings, [
                $this->h('Name').' (EN)', $this->h('Name').' (NL)',
                $this->h('Title').' (EN)', $this->h('Title').' (NL)',
                $this->h('Subtitle').' (EN)', $this->h('Subtitle').' (NL)',
                $this->h('Slug').' (EN)', $this->h('Slug').' (NL)',
            ]);
        } else {
            $headings = array_merge($headings, [
                $this->h('Name'), $this->h('Title'), $this->h('Subtitle'), $this->h('Slug'),
            ]);
        }

        $headings = array_merge($headings, [
            $this->h('SKU'),
            $this->h('Article Number'),
            $this->h('State'),
            $this->h('Price'),
            $this->h('Original Price'),
            $this->h('Stock'),
            $this->h('Material'),
            $this->h('Categories'),
            $this->h('Product Template'),
        ]);

        if ($isBoth) {
            $headings = array_merge($headings, [
                $this->h('Description').' (EN)', $this->h('Description').' (NL)',
                $this->h('Excerpt').' (EN)', $this->h('Excerpt').' (NL)',
                $this->h('Content').' (EN)', $this->h('Content').' (NL)',
            ]);
        } else {
            $headings = array_merge($headings, [
                $this->h('Description'), $this->h('Excerpt'), $this->h('Content'),
            ]);
        }

        return array_merge($headings, [
            $this->h('Product Information'),
            $this->h('Material Information'),
            $this->h('Packaging Unit'),
            $this->h('Delivery Days (In Stock)'),
            $this->h('Delivery Days (No Stock)'),
            $this->h('Packing Group'),
            $this->h('Weight'),
            $this->h('Height'),
            $this->h('Width'),
            $this->h('Length'),
            $this->h('Meta Fields'),
            $this->h('Main Image'),
            $this->h('Gallery Images'),
            $this->h('Variant SKU'),
            $this->h('Variant Price'),
            $this->h('Variant Stock'),
            $this->h('Variant Attributes'),
            $this->h('Created At'),
            $this->h('Updated At'),
        ]);
    }

    protected function h(string $key): string
    {
        return static::$headingTranslations[$this->headingLocale][$key] ?? $key;
    }

    protected function parseRowIds(): array
    {
        if ($this->rowIds === null) {
            return [null, null];
        }

        $simpleIds = [];
        $variableIds = [];

        foreach ($this->rowIds as $rowId) {
            [$type, $id] = explode('_', $rowId, 2);

            if ($type === 'simple') {
                $simpleIds[] = (int) $id;
            } else {
                $variableIds[] = (int) $id;
            }
        }

        return [
            empty($simpleIds) ? [] : $simpleIds,
            empty($variableIds) ? [] : $variableIds,
        ];
    }

    protected function querySimpleProducts(?array $ids): Collection
    {
        $query = Product::query()
            ->whereNull('deleted_at')
            ->with(['metas', 'taxons', 'media']);

        if ($this->locale !== 'en') {
            $query->with('translations');
        }

        if ($ids !== null) {
            if (empty($ids)) {
                return collect();
            }
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    protected function queryVariableProducts(?array $ids): Collection
    {
        $query = MasterProduct::query()
            ->whereNull('deleted_at')
            ->with([
                'metas',
                'taxons',
                'media',
                'variants' => fn ($q) => $q->with('propertyValues.property'),
            ]);

        if ($this->locale !== 'en') {
            $query->with('translations');
        }

        if ($ids !== null) {
            if (empty($ids)) {
                return collect();
            }
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    protected function mapSimpleProduct(Product $product): array
    {
        $t = $this->getTranslatedFields($product);

        $row = [
            $product->id,
            'Simple',
        ];

        $row = array_merge($row, $this->buildTranslatableColumns($product, $t, ['name', 'title', 'subtitle', 'slug']));

        $row = array_merge($row, [
            $product->sku ?? '',
            $product->article_number ?? '',
            $this->resolveState($product->state),
            $product->price,
            $product->original_price,
            $product->stock,
            '',
            $this->resolveCategories($product),
            $product->product_template ?? '',
        ]);

        $row = array_merge($row, $this->buildTranslatableColumns($product, $t, ['description', 'excerpt', 'content'], true));

        return array_merge($row, [
            strip_tags($product->product_information ?? ''),
            strip_tags($product->material_information ?? ''),
            $product->packaging_unit,
            $product->delivery_dates_in_stock,
            $product->delivery_dates_no_stock,
            $product->packing_group,
            $product->weight,
            $product->height,
            $product->width,
            $product->length,
            $this->resolveMetaFields($product->metas),
            $this->resolveMainImage($product),
            $this->resolveGalleryImages($product),
            '', // Variant SKU
            '', // Variant Price
            '', // Variant Stock
            '', // Variant Attributes
            $product->created_at?->format('d/m/Y H:i:s'),
            $product->updated_at?->format('d/m/Y H:i:s'),
        ]);
    }

    protected function mapVariableProduct(MasterProduct $master, Collection $variants): array
    {
        $t = $this->getTranslatedFields($master);

        $row = [
            $master->id,
            'Variable',
        ];

        $row = array_merge($row, $this->buildTranslatableColumns($master, $t, ['name', 'title', 'subtitle', 'slug']));

        $row = array_merge($row, [
            '', // SKU (master level)
            $master->article_number ?? '',
            $this->resolveState($master->state),
            $master->price,
            $master->original_price,
            '', // Stock (master level)
            '',
            $this->resolveCategories($master),
            $master->product_template ?? '',
        ]);

        $row = array_merge($row, $this->buildTranslatableColumns($master, $t, ['description', 'excerpt', 'content'], true));

        return array_merge($row, [
            strip_tags($master->product_information ?? ''),
            strip_tags($master->material_information ?? ''),
            $master->packaging_unit,
            $master->delivery_dates_in_stock,
            $master->delivery_dates_no_stock,
            $master->packing_group,
            $master->weight,
            $master->height,
            $master->width,
            $master->length,
            $this->resolveMetaFields($master->metas),
            $this->resolveMainImage($master),
            $this->resolveGalleryImages($master),
            $variants->pluck('sku')->filter()->join(' | '),
            $variants->pluck('price')->filter()->join(' | '),
            $variants->pluck('stock')->join(' | '),
            $variants->map(fn ($v) => $this->resolveVariantAttributes($v))->filter()->join(' | '),
            $master->created_at?->format('d/m/Y H:i:s'),
            $master->updated_at?->format('d/m/Y H:i:s'),
        ]);
    }

    protected function getTranslatedFields($model): array
    {
        if ($this->locale === 'en') {
            return [];
        }

        $translation = $model->translations->where('language', 'nl')->first();

        if (! $translation) {
            return [];
        }

        $fields = is_array($translation->fields) ? $translation->fields : [];
        $fields['name'] = $translation->name ?? ($fields['name'] ?? null);
        $fields['slug'] = $translation->slug ?? ($fields['slug'] ?? null);

        return $fields;
    }

    protected function buildTranslatableColumns($model, array $nlFields, array $fields, bool $stripTags = false): array
    {
        $columns = [];

        foreach ($fields as $field) {
            $enValue = $model->{$field} ?? '';
            if ($stripTags) {
                $enValue = strip_tags($enValue);
            }

            if ($this->locale === 'both') {
                $nlValue = $nlFields[$field] ?? $enValue;
                if ($stripTags) {
                    $nlValue = strip_tags($nlValue);
                }
                $columns[] = $enValue;
                $columns[] = $nlValue;
            } elseif ($this->locale === 'nl') {
                $nlValue = $nlFields[$field] ?? $enValue;
                if ($stripTags) {
                    $nlValue = strip_tags($nlValue);
                }
                $columns[] = $nlValue;
            } else {
                $columns[] = $enValue;
            }
        }

        return $columns;
    }

    protected function resolveState($state): string
    {
        if (is_object($state) && method_exists($state, 'value')) {
            return ucfirst($state->value());
        }

        return ucfirst((string) ($state ?? ''));
    }

    protected function resolveCategories($product): string
    {
        return $product->taxons->pluck('name')->join(', ');
    }

    protected function resolveMetaFields($metas): string
    {
        if ($metas->isEmpty()) {
            return '';
        }

        return $metas
            ->filter(fn ($meta) => filled($meta->meta_key) && filled($meta->meta_value))
            ->map(function ($meta) {
                $key = $meta->meta_key;
                $value = $meta->meta_value;

                if (in_array($key, static::$configKeys)) {
                    $value = config("products.{$key}.{$value}", $value);
                }

                return "{$key}: {$value}";
            })
            ->join(' | ');
    }

    protected function resolveMainImage($product): string
    {
        $media = $product->getFirstMedia('main');

        return $media ? $media->getUrl() : '';
    }

    protected function resolveGalleryImages($product): string
    {
        $gallery = $product->getMedia('gallery');

        if ($gallery->isEmpty()) {
            return '';
        }

        return $gallery->map(fn ($media) => $media->getUrl())->join('|');
    }

    protected function resolveVariantAttributes($variant): string
    {
        if (! $variant->relationLoaded('propertyValues') || $variant->propertyValues->isEmpty()) {
            return '';
        }

        return $variant->propertyValues
            ->map(function ($pv) {
                $propertyName = $pv->property?->name ?? '';
                $value = $pv->value ?? $pv->title ?? '';

                return "{$propertyName}: {$value}";
            })
            ->join(', ');
    }
}

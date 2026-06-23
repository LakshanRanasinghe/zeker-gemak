<?php

namespace App\Exports;

use App\Models\Material;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MaterialExport implements FromQuery, WithHeadings, WithMapping
{
    protected static array $headingTranslations = [
        'en' => [
            'ID' => 'ID',
            'Title' => 'Title',
            'Subtitle' => 'Subtitle',
            'Slug' => 'Slug',
            'Code' => 'Code',
            'Brand' => 'Brand',
            'Status' => 'Status',
            'Print Method' => 'Print Method',
            'Base Material' => 'Base Material',
            'Finish' => 'Finish',
            'Adhesive' => 'Adhesive',
            'Supplier' => 'Supplier',
            'Supplier Reference' => 'Supplier Reference',
            'Price per m²' => 'Price per m²',
            'Certificate' => 'Certificate',
            'Specifications' => 'Specifications',
            'Category' => 'Category',
            'Created At' => 'Created At',
            'Updated At' => 'Updated At',
        ],
        'nl' => [
            'ID' => 'ID',
            'Title' => 'Titel',
            'Subtitle' => 'Ondertitel',
            'Slug' => 'Slug',
            'Code' => 'Code',
            'Brand' => 'Merk',
            'Status' => 'Status',
            'Print Method' => 'Drukmethode',
            'Base Material' => 'Basismateriaal',
            'Finish' => 'Afwerking',
            'Adhesive' => 'Lijm',
            'Supplier' => 'Leverancier',
            'Supplier Reference' => 'Leveranciersreferentie',
            'Price per m²' => 'Prijs per m²',
            'Certificate' => 'Certificaat',
            'Specifications' => 'Specificaties',
            'Category' => 'Categorie',
            'Created At' => 'Aangemaakt op',
            'Updated At' => 'Bijgewerkt op',
        ],
    ];

    public function __construct(
        protected ?array $ids = null,
        protected string $locale = 'en',
        protected string $headingLocale = 'en',
    ) {
    }

    public function query()
    {
        $query = Material::query()->with('taxons');

        if ($this->locale !== 'en') {
            $query->with('translations');
        }

        if ($this->ids !== null) {
            $query->whereIn('id', $this->ids);
        }

        return $query;
    }

    public function headings(): array
    {
        $isBoth = $this->locale === 'both';

        $headings = [$this->h('ID')];

        if ($isBoth) {
            $headings = array_merge($headings, [
                $this->h('Title') . ' (EN)',
                $this->h('Title') . ' (NL)',
                $this->h('Subtitle') . ' (EN)',
                $this->h('Subtitle') . ' (NL)',
                $this->h('Slug') . ' (EN)',
                $this->h('Slug') . ' (NL)',
            ]);
        } else {
            $headings = array_merge($headings, [
                $this->h('Title'),
                $this->h('Subtitle'),
                $this->h('Slug'),
            ]);
        }

        $headings = array_merge($headings, [
            $this->h('Code'),
            $this->h('Brand'),
            $this->h('Status'),
            $this->h('Print Method'),
            $this->h('Base Material'),
            $this->h('Finish'),
            $this->h('Adhesive'),
            $this->h('Supplier'),
            $this->h('Supplier Reference'),
            $this->h('Price per m²'),
            $this->h('Certificate'),
        ]);

        if ($isBoth) {
            $headings = array_merge($headings, [
                $this->h('Specifications') . ' (EN)',
                $this->h('Specifications') . ' (NL)',
            ]);
        } else {
            $headings[] = $this->h('Specifications');
        }

        return array_merge($headings, [
            $this->h('Category'),
            $this->h('Created At'),
            $this->h('Updated At'),
        ]);
    }

    public function map($material): array
    {
        $nlFields = $this->getTranslatedFields($material);

        $row = [$material->id];

        $row = array_merge($row, $this->buildTranslatableColumns($material, $nlFields, ['title', 'subtitle', 'slug']));

        $row = array_merge($row, [
            $material->code,
            $material->brand,
            ucfirst($material->status ?? ''),
            $material->print_method,
            $material->base_material,
            $material->finish,
            $material->adhesive,
            $material->supplier,
            $material->supplier_reference,
            $material->price_per_sq_meter,
            $material->certificate,
        ]);

        $row = array_merge($row, $this->buildSpecificationsColumns($material, $nlFields));

        return array_merge($row, [
            $material->category?->name ?? '',
            $material->created_at?->format('d/m/Y H:i:s'),
            $material->updated_at?->format('d/m/Y H:i:s'),
        ]);
    }

    protected function h(string $key): string
    {
        return static::$headingTranslations[$this->headingLocale][$key] ?? $key;
    }

    protected function getTranslatedFields(Material $material): array
    {
        if ($this->locale === 'en') {
            return [];
        }

        $translation = $material->translations->where('language', 'nl')->first();

        if (!$translation) {
            return [];
        }

        $fields = is_array($translation->fields) ? $translation->fields : [];
        $fields['name'] = $translation->name ?? ($fields['name'] ?? null);
        $fields['slug'] = $translation->slug ?? ($fields['slug'] ?? null);

        if (isset($fields['name']) && !isset($fields['title'])) {
            $fields['title'] = $fields['name'];
        }

        return $fields;
    }

    protected function buildTranslatableColumns(Material $material, array $nlFields, array $fields): array
    {
        $columns = [];

        foreach ($fields as $field) {
            $enValue = $material->{$field} ?? '';

            if ($this->locale === 'both') {
                $columns[] = $enValue;
                $columns[] = $nlFields[$field] ?? '';
            } elseif ($this->locale === 'nl') {
                $columns[] = $nlFields[$field] ?? $enValue;
            } else {
                $columns[] = $enValue;
            }
        }

        return $columns;
    }

    protected function buildSpecificationsColumns(Material $material, array $nlFields): array
    {
        $enSpecs = $this->formatSpecifications($material->specifications['material_specs'] ?? []);

        if ($this->locale === 'both') {
            $nlSpecsData = $nlFields['specifications']['material_specs'] ?? ($nlFields['specifications'] ?? null);
            $nlSpecs = is_array($nlSpecsData) ? $this->formatSpecifications($nlSpecsData) : '';

            return [$enSpecs, $nlSpecs];
        }

        if ($this->locale === 'nl') {
            $nlSpecsData = $nlFields['specifications']['material_specs'] ?? ($nlFields['specifications'] ?? null);

            return [is_array($nlSpecsData) ? $this->formatSpecifications($nlSpecsData) : $enSpecs];
        }

        return [$enSpecs];
    }

    protected function formatSpecifications(array $specs): string
    {
        if (empty($specs)) {
            return '';
        }

        return collect($specs)
            ->map(fn($spec) => ($spec['label'] ?? '') . ': ' . ($spec['value'] ?? ''))
            ->join(' | ');
    }
}

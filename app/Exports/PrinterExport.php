<?php

namespace App\Exports;

use App\Models\Post;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PrinterExport implements FromQuery, WithHeadings, WithMapping
{
    protected static array $headingTranslations = [
        'en' => [
            'ID' => 'ID',
            'Title' => 'Title',
            'Slug' => 'Slug',
            'Status' => 'Status',
            'Subtitle' => 'Subtitle',
            'Kern' => 'Kern',
            'Label Breedte' => 'Label Breedte',
            'Label Type' => 'Label Type',
            'Max Buiten Diameter' => 'Max Buiten Diameter',
            'Width' => 'Width',
            'Druktype' => 'Druktype',
            'Buiten Diameter' => 'Buiten Diameter',
            'Detectie' => 'Detectie',
            'Featured' => 'Featured',
            'Printer URL' => 'Printer URL',
            'Excerpt' => 'Excerpt',
            'Template' => 'Template',
            'Created At' => 'Created At',
            'Updated At' => 'Updated At',
        ],
        'nl' => [
            'ID' => 'ID',
            'Title' => 'Titel',
            'Slug' => 'Slug',
            'Status' => 'Status',
            'Subtitle' => 'Ondertitel',
            'Kern' => 'Kern',
            'Label Breedte' => 'Label Breedte',
            'Label Type' => 'Label Type',
            'Max Buiten Diameter' => 'Max Buiten Diameter',
            'Width' => 'Breedte',
            'Druktype' => 'Druktype',
            'Buiten Diameter' => 'Buiten Diameter',
            'Detectie' => 'Detectie',
            'Featured' => 'Uitgelicht',
            'Printer URL' => 'Printer URL',
            'Excerpt' => 'Samenvatting',
            'Template' => 'Sjabloon',
            'Created At' => 'Aangemaakt op',
            'Updated At' => 'Bijgewerkt op',
        ],
    ];

    public function __construct(
        protected ?array $ids = null,
        protected string $locale = 'en',
        protected string $headingLocale = 'en',
    ) {}

    public function query()
    {
        $query = Post::query()
            ->where('post_type', 'printer')
            ->with('meta');

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
                $this->h('Title').' (EN)',
                $this->h('Title').' (NL)',
                $this->h('Slug').' (EN)',
                $this->h('Slug').' (NL)',
            ]);
        } else {
            $headings = array_merge($headings, [$this->h('Title'), $this->h('Slug')]);
        }

        $headings = array_merge($headings, [
            $this->h('Status'),
            $this->h('Subtitle'),
            $this->h('Kern'),
            $this->h('Label Breedte'),
            $this->h('Label Type'),
            $this->h('Max Buiten Diameter'),
            $this->h('Width'),
            $this->h('Druktype'),
            $this->h('Buiten Diameter'),
            $this->h('Detectie'),
            $this->h('Featured'),
            $this->h('Printer URL'),
        ]);

        if ($isBoth) {
            $headings = array_merge($headings, [
                $this->h('Excerpt').' (EN)',
                $this->h('Excerpt').' (NL)',
                $this->h('Template').' (EN)',
                $this->h('Template').' (NL)',
            ]);
        } else {
            $headings = array_merge($headings, [$this->h('Excerpt'), $this->h('Template')]);
        }

        return array_merge($headings, [
            $this->h('Created At'),
            $this->h('Updated At'),
        ]);
    }

    public function map($printer): array
    {
        $meta = $printer->meta->pluck('meta_value', 'meta_key');
        $nlFields = $this->getTranslatedFields($printer);

        $configLabels = [
            'kern' => config('printers.kern', []),
            'width' => config('printers.width', []),
            'druktype' => config('printers.druktype', []),
            'buiten_diameter' => config('printers.buiten_diameter', []),
            'detectie' => config('printers.detectie', []),
        ];

        $row = [$printer->id];

        $row = array_merge($row, $this->buildTranslatableColumns($printer, $nlFields, ['title', 'slug']));

        $row = array_merge($row, [
            ucfirst($printer->status),
            $meta->get('subtitle', ''),
            $configLabels['kern'][$meta->get('kern', '')] ?? $meta->get('kern', ''),
            $meta->get('label_breedte', ''),
            $meta->get('label_type', ''),
            $meta->get('max_buiten_diameter', ''),
            $meta->get('width', '') ?? implode(', ', $meta->get('width', '')),
            $meta->get('druktype', '') ?? implode(', ', $meta->get('druktype', '')),
            $meta->get('buiten_diameter', '') ?? implode(', ', $meta->get('buiten_diameter', '')),
            $meta->get('detectie', '') ?? implode(', ', $meta->get('detectie', '')),
            $meta->get('featured') ? 'Yes' : 'No',
            $meta->get('printer_url', ''),
        ]);

        $row = array_merge($row, $this->buildTranslatableColumns($printer, $nlFields, ['excerpt', 'template']));

        return array_merge($row, [
            $printer->created_at?->format('d/m/Y H:i:s'),
            $printer->updated_at?->format('d/m/Y H:i:s'),
        ]);
    }

    protected function h(string $key): string
    {
        return static::$headingTranslations[$this->headingLocale][$key] ?? $key;
    }

    protected function getTranslatedFields(Post $printer): array
    {
        if ($this->locale === 'en') {
            return [];
        }

        $translation = $printer->translations->where('language', 'nl')->first();

        if (! $translation) {
            return [];
        }

        $fields = is_array($translation->fields) ? $translation->fields : [];
        $fields['title'] = $fields['title'] ?? $fields['name'] ?? ($translation->name ?? null);
        $fields['slug'] = $translation->slug ?? ($fields['slug'] ?? null);

        return $fields;
    }

    protected function buildTranslatableColumns(Post $printer, array $nlFields, array $fields): array
    {
        $columns = [];

        foreach ($fields as $field) {
            $enValue = $printer->{$field} ?? '';

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
}

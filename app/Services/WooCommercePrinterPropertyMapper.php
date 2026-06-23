<?php

namespace App\Services;

use Illuminate\Support\Str;

class WooCommercePrinterPropertyMapper
{
    /**
     * @return array<string, array<int, string>>
     */
    public function fromPrinterData(array $data): array
    {
        $acf = $data['acf'] ?? [];

        return array_filter([
            'printmethode' => $this->normalizeTextValues($acf['druktype'] ?? null),
            'breedte' => $this->supportedWidths($acf['widths'] ?? null, $acf['label_breedte'] ?? null),
            'label-breedte-min' => $this->rangeBoundary($acf['label_breedte'] ?? null, 'min'),
            'label-breedte-max' => $this->rangeBoundary($acf['label_breedte'] ?? null, 'max'),
            'kern' => $this->kernValues($acf['kern_data'] ?? null, $acf['kern'] ?? null),
            'buiten-diameter' => $this->normalizeMixedValues($acf['buiten_diameter'] ?? null),
            'max-buiten-diameter' => $this->singleNumericValue($acf['max_buiten_diameter'] ?? null),
            'detectie' => $this->normalizeTextValues($acf['detectie'] ?? null),
            'labeltype' => $this->normalizeTextValues($acf['labeltype'] ?? null),
            'printer-subtitle' => $this->normalizeTextValues($acf['printers_sub_title'] ?? null),
            'printer-url' => $this->normalizeTextValues($acf['printer_kopen'] ?? null),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function supportedWidths(mixed $widths, mixed $labelWidth): array
    {
        $values = $this->normalizeNumericValues($widths);
        $range = $this->parseRange($labelWidth);

        if ($range !== null) {
            $values = array_merge($values, $this->expandRange($range['min'], $range['max']));
        }

        return $this->uniqueValues($values);
    }

    /**
     * @return array<int, string>
     */
    protected function kernValues(mixed $kernData, mixed $kern): array
    {
        $values = $this->normalizeMixedValues($kernData);

        if ($values !== []) {
            return $values;
        }

        return $this->normalizeMixedValues($kern);
    }

    /**
     * @return array<int, string>
     */
    protected function rangeBoundary(mixed $value, string $boundary): array
    {
        $range = $this->parseRange($value);

        if ($range === null) {
            return [];
        }

        return [$this->formatNumber($range[$boundary])];
    }

    /**
     * @return array<int, string>
     */
    protected function singleNumericValue(mixed $value): array
    {
        $number = $this->firstNumber($value);

        return $number === null ? [] : [$this->formatNumber($number)];
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeMixedValues(mixed $value): array
    {
        $values = [];

        foreach ($this->toList($value) as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $string = trim((string) $item);

            if ($string === '') {
                continue;
            }

            $numbers = $this->numbersFromString($string);

            if ($numbers !== []) {
                foreach ($numbers as $number) {
                    $values[] = $this->formatNumber($number);
                }

                continue;
            }

            $values[] = $string;
        }

        return $this->uniqueValues($values);
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeNumericValues(mixed $value): array
    {
        return $this->uniqueValues(
            collect($this->toList($value))
                ->filter(fn (mixed $item): bool => is_scalar($item))
                ->map(fn (mixed $item): ?float => $this->firstNumber($item))
                ->filter(fn (?float $number): bool => $number !== null)
                ->map(fn (float $number): string => $this->formatNumber($number))
                ->all()
        );
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeTextValues(mixed $value): array
    {
        return $this->uniqueValues(
            collect($this->toList($value))
                ->filter(fn (mixed $item): bool => is_scalar($item))
                ->map(fn (mixed $item): string => trim((string) $item))
                ->filter(fn (string $item): bool => $item !== '')
                ->all()
        );
    }

    /**
     * @return array{min: float, max: float}|null
     */
    protected function parseRange(mixed $value): ?array
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = (string) $value;
        $normalized = $this->normalizeDecimalSeparators($string);

        if (
            preg_match('/min\.?\s*([0-9]+(?:\.[0-9]+)?)/i', $normalized, $minMatch)
            && preg_match('/max\.?\s*([0-9]+(?:\.[0-9]+)?)/i', $normalized, $maxMatch)
        ) {
            return [
                'min' => (float) $minMatch[1],
                'max' => (float) $maxMatch[1],
            ];
        }

        $numbers = $this->numbersFromString($string);

        if (count($numbers) < 2) {
            return null;
        }

        return [
            'min' => min($numbers),
            'max' => max($numbers),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function expandRange(float $min, float $max): array
    {
        $values = [$this->formatNumber($min)];

        for ($current = (int) ceil($min); $current <= (int) floor($max); $current++) {
            $values[] = (string) $current;
        }

        if (floor($max) !== $max) {
            $values[] = $this->formatNumber($max);
        }

        return $this->uniqueValues($values);
    }

    protected function firstNumber(mixed $value): ?float
    {
        $numbers = $this->numbersFromString($value);

        return $numbers[0] ?? null;
    }

    /**
     * @return array<int, float>
     */
    protected function numbersFromString(mixed $value): array
    {
        if (! is_scalar($value)) {
            return [];
        }

        $normalized = $this->normalizeDecimalSeparators((string) $value);

        if (! preg_match_all('/[0-9]+(?:\.[0-9]+)?/', $normalized, $matches)) {
            return [];
        }

        return array_map('floatval', $matches[0]);
    }

    protected function normalizeDecimalSeparators(string $value): string
    {
        return preg_replace('/(\d),(\d)/', '$1.$2', $value) ?? $value;
    }

    protected function formatNumber(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * @return array<int, mixed>
     */
    protected function toList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $value === null ? [] : [$value];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    protected function uniqueValues(array $values): array
    {
        return collect($values)
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique(fn (string $value): string => Str::lower($value))
            ->values()
            ->all();
    }
}

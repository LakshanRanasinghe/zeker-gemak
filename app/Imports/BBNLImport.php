<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class BBNLImport implements ToModel, WithChunkReading
{
    /**
     * @return Model|null
     */
    public function model(array $row)
    {
        // Skip header row if it's there (ID column usually shows it)
        if ($row[0] === 'ID' || ! isset($row[13]) || $row[13] === 'Sku' || $row[13] === 'sku') {
            return null;
        }

        $sku = (string) $row[13];
        if (empty($sku)) {
            return null;
        }

        $productData = [
            'name' => (string) ($row[1] ?? ''),
            'price' => (float) ($row[14] ?? 0),
            'sku' => $sku,
            'type' => 'simple',
            'state' => 'active',
            'stock' => (float) ($row[18] ?? 0),
            'weight' => (float) ($row[120] ?? 0),
            'length' => (float) ($row[121] ?? 0),
            'width' => (float) ($row[122] ?? 0),
            'height' => (float) ($row[123] ?? 0),
            'excerpt' => (string) ($row[3] ?? ''),
            'description' => (string) ($row[2] ?? ''),
            'packaging_unit' => (int) ($row[164] ?? 1),
            'jeritech_stock' => (int) ($row[165] ?? 0),
            'subtitle' => (string) ($row[149] ?: $row[167] ?: $row[157] ?: ''),
        ];

        $product = Product::updateOrCreate(
            ['sku' => $sku],
            $productData
        );

        // Handle Custom Properties (Metas)
        $metas = [
            'brand' => $row[159] ?: $row[86] ?: '',
            'material' => $row[153] ?: $row[88] ?: '',
            'finishing' => $row[160] ?: $row[89] ?: '',
            'glue' => $row[162] ?: $row[90] ?: '',
            'article_number' => $row[163] ?: '',
            'printer_type' => $row[154] ?: $row[92] ?: '',
            'kern' => $row[180] ?: $row[176] ?: $row[95] ?: '',
            'label_breedte' => $row[177] ?: '',
            'label_type' => $row[178] ?: '',
            'buiten_diameter' => $row[183] ?: $row[96] ?: '',
            'detectie' => $row[184] ?: $row[97] ?: '',
            'video_link' => $row[152] ?: '',
        ];

        foreach ($metas as $key => $value) {
            if (filled($value)) {
                $product->metas()->updateOrCreate(
                    ['meta_key' => $key],
                    ['meta_value' => (string) $value]
                );
            }
        }

        return $product;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}

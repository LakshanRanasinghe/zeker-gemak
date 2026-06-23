<?php

use App\Services\WooCommercePrinterPropertyMapper;

test('it maps printer acf data to canonical Vanilo property slugs', function () {
    $properties = (new WooCommercePrinterPropertyMapper)->fromPrinterData([
        'acf' => [
            'printers_sub_title' => 'Industrial printer',
            'label_breedte' => 'Min 25,4 mm, Max 118 mm.',
            'kern' => '38 - 76,2 mm',
            'kern_data' => ['38', '76', 'Fan-fold'],
            'druktype' => ['TD', 'TT'],
            'buiten_diameter' => ['66', '75', '101', '127', '152', '203', 'Fan-fold'],
            'max_buiten_diameter' => '203 mm.',
            'detectie' => ['GAP', 'Blackmark', 'Endless', 'Sensor notch'],
            'labeltype' => 'Thermal Direct & Thermal Transfer',
            'printer_kopen' => 'https://example.com/printer',
        ],
    ]);

    expect($properties)
        ->toHaveKey('printmethode')
        ->toHaveKey('breedte')
        ->toHaveKey('label-breedte-min')
        ->toHaveKey('label-breedte-max')
        ->toHaveKey('kern')
        ->toHaveKey('buiten-diameter')
        ->toHaveKey('max-buiten-diameter')
        ->and($properties['printmethode'])->toBe(['TD', 'TT'])
        ->and($properties['label-breedte-min'])->toBe(['25.4'])
        ->and($properties['label-breedte-max'])->toBe(['118'])
        ->and($properties['breedte'])->toContain('25.4', '26', '118')
        ->and($properties['breedte'])->not->toContain('25')
        ->and($properties['kern'])->toBe(['38', '76', 'Fan-fold'])
        ->and($properties['buiten-diameter'])->toBe(['66', '75', '101', '127', '152', '203', 'Fan-fold'])
        ->and($properties['max-buiten-diameter'])->toBe(['203']);
});

test('it preserves dot decimal source values', function () {
    $properties = (new WooCommercePrinterPropertyMapper)->fromPrinterData([
        'acf' => [
            'label_breedte' => 'Min 25.4 mm, Max 118.5 mm',
            'kern' => '38 - 76.2 mm',
        ],
    ]);

    expect($properties['label-breedte-min'])->toBe(['25.4'])
        ->and($properties['label-breedte-max'])->toBe(['118.5'])
        ->and($properties['breedte'])->toContain('25.4', '118.5');
});

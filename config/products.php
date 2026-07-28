<?php

use App\Support\CatalogMetaFilters;

return [

    /*
    |--------------------------------------------------------------------------
    | Product Configuration Options
    |--------------------------------------------------------------------------
    |
    | Here you can configure the available options for the various
    | select dropdowns on the Product creation/editing form.
    |
    */

    'meta_fields' => CatalogMetaFilters::keys(),

    'product_template' => [
        'label' => 'Label',
        'general' => 'General',
    ],

    'finishing' => [
        'gloss' => 'Gloss',
        'matte' => 'Matte',
        'satin' => 'Satin',
        'uncoated' => 'Uncoated',
        'varnish' => 'Varnish',
    ],

    'glue' => [
        'permanent' => 'Permanent',
        'removable' => 'Removable',
        'freezer' => 'Freezer',
        'ultra_removable' => 'Ultra Removable',
        'high_tack' => 'High Tack',
    ],

    'brand' => [
        'zebra' => 'Zebra',
        'honeywell' => 'Honeywell',
        'tsc' => 'TSC',
        'sato' => 'SATO',
        'citizen' => 'Citizen',
    ],

    'druktype' => [
        'direct_thermal' => 'Direct Thermal',
        'thermal_transfer' => 'Thermal Transfer',
        'both' => 'Both',
        'inkjet' => 'Inkjet',
    ],

    'meta_width' => [
        '25mm' => '25 mm',
        '50mm' => '50 mm',
        '76mm' => '76 mm',
        '100mm' => '100 mm',
        '104mm' => '104 mm',
    ],

    'meta_height' => [
        '15mm' => '15 mm',
        '25mm' => '25 mm',
        '36mm' => '36 mm',
        '50mm' => '50 mm',
        '76mm' => '76 mm',
    ],

    'kern' => [
        '25mm' => '25 mm',
        '40mm' => '40 mm',
        '76mm' => '76 mm',
        '100mm' => '100 mm',
    ],

    'buitendia' => [
        '5_inch' => '5 inch (127mm)',
        '8_inch' => '8 inch (203mm)',
        '10_inch' => '10 inch (254mm)',
        '12_inch' => '12 inch (305mm)',
    ],

    'detectie' => [
        'gap' => 'Gap',
        'black_mark' => 'Black Mark',
        'continuous' => 'Continuous',
        'gap_black_mark' => 'Gap & Black Mark',
    ],

    'merken' => [
        'zebra' => 'Zebra',
        'honeywell' => 'Honeywell',
        'tsc' => 'TSC',
        'sato' => 'SATO',
        'citizen' => 'Citizen',
        'brother' => 'Brother',
    ],

    'merchant_feed' => [
        'storefront_url' => env('GOOGLE_MERCHANT_FEED_STOREFRONT_URL', 'https://bbnl-front.dayzsolutions.com'),
        'title' => env('GOOGLE_MERCHANT_FEED_TITLE', 'Zeker Gemak'),
        'description' => env('GOOGLE_MERCHANT_FEED_DESCRIPTION', 'Google Merchant Center product feed - Zeker Gemak'),
        'brand' => env('GOOGLE_MERCHANT_FEED_BRAND', 'Zeker Gemak'),
    ],

    'jaritech' => [
        'csv_url' => env('JARITECH_CSV_URL', 'https://pietervanhees.nl/stock/jaritech.csv'),
        'csv_output_path' => env('JARITECH_CSV_OUTPUT_PATH', public_path('jaritech.csv')),
    ],

    's2b' => [
        'xml_url' => env('S2B_XML_URL', 'https://pietervanhees.nl/stock/S2B-vrrd'),
    ],

];

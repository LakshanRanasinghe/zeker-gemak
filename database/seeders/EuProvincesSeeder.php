<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EuProvincesSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [

            /*
            |--------------------------------------------------------------------------
            | Austria
            |--------------------------------------------------------------------------
            */
            ['name' => 'Burgenland', 'country_id' => 'AT', 'type' => 'state', 'code' => '1'],
            ['name' => 'Carinthia', 'country_id' => 'AT', 'type' => 'state', 'code' => '2'],
            ['name' => 'Lower Austria', 'country_id' => 'AT', 'type' => 'state', 'code' => '3'],
            ['name' => 'Upper Austria', 'country_id' => 'AT', 'type' => 'state', 'code' => '4'],
            ['name' => 'Salzburg', 'country_id' => 'AT', 'type' => 'state', 'code' => '5'],
            ['name' => 'Styria', 'country_id' => 'AT', 'type' => 'state', 'code' => '6'],
            ['name' => 'Tyrol', 'country_id' => 'AT', 'type' => 'state', 'code' => '7'],
            ['name' => 'Vorarlberg', 'country_id' => 'AT', 'type' => 'state', 'code' => '8'],
            ['name' => 'Vienna', 'country_id' => 'AT', 'type' => 'state', 'code' => '9'],

            /*
            |--------------------------------------------------------------------------
            | Belgium
            |--------------------------------------------------------------------------
            */
            ['name' => 'Antwerp', 'country_id' => 'BE', 'type' => 'province', 'code' => 'VAN'],
            ['name' => 'East Flanders', 'country_id' => 'BE', 'type' => 'province', 'code' => 'VOV'],
            ['name' => 'Flemish Brabant', 'country_id' => 'BE', 'type' => 'province', 'code' => 'VBR'],
            ['name' => 'Limburg', 'country_id' => 'BE', 'type' => 'province', 'code' => 'VLI'],
            ['name' => 'West Flanders', 'country_id' => 'BE', 'type' => 'province', 'code' => 'VWV'],
            ['name' => 'Hainaut', 'country_id' => 'BE', 'type' => 'province', 'code' => 'WHT'],
            ['name' => 'Liège', 'country_id' => 'BE', 'type' => 'province', 'code' => 'WLG'],
            ['name' => 'Luxembourg', 'country_id' => 'BE', 'type' => 'province', 'code' => 'WLX'],
            ['name' => 'Namur', 'country_id' => 'BE', 'type' => 'province', 'code' => 'WNA'],
            ['name' => 'Walloon Brabant', 'country_id' => 'BE', 'type' => 'province', 'code' => 'WBR'],

            /*
            |--------------------------------------------------------------------------
            | Bulgaria
            |--------------------------------------------------------------------------
            */
            ['name' => 'Sofia City', 'country_id' => 'BG', 'type' => 'province', 'code' => '22'],
            ['name' => 'Plovdiv', 'country_id' => 'BG', 'type' => 'province', 'code' => '16'],
            ['name' => 'Varna', 'country_id' => 'BG', 'type' => 'province', 'code' => '03'],

            /*
            |--------------------------------------------------------------------------
            | Croatia
            |--------------------------------------------------------------------------
            */
            ['name' => 'Zagreb', 'country_id' => 'HR', 'type' => 'county', 'code' => '21'],
            ['name' => 'Split-Dalmatia', 'country_id' => 'HR', 'type' => 'county', 'code' => '17'],
            ['name' => 'Istria', 'country_id' => 'HR', 'type' => 'county', 'code' => '18'],

            /*
            |--------------------------------------------------------------------------
            | Cyprus
            |--------------------------------------------------------------------------
            */
            ['name' => 'Nicosia', 'country_id' => 'CY', 'type' => 'district', 'code' => '01'],
            ['name' => 'Limassol', 'country_id' => 'CY', 'type' => 'district', 'code' => '02'],
            ['name' => 'Larnaca', 'country_id' => 'CY', 'type' => 'district', 'code' => '03'],

            /*
            |--------------------------------------------------------------------------
            | Czech Republic
            |--------------------------------------------------------------------------
            */
            ['name' => 'Prague', 'country_id' => 'CZ', 'type' => 'region', 'code' => '10'],
            ['name' => 'South Moravian', 'country_id' => 'CZ', 'type' => 'region', 'code' => '64'],
            ['name' => 'Central Bohemian', 'country_id' => 'CZ', 'type' => 'region', 'code' => '20'],

            /*
            |--------------------------------------------------------------------------
            | Denmark
            |--------------------------------------------------------------------------
            */
            ['name' => 'Capital Region', 'country_id' => 'DK', 'type' => 'region', 'code' => '84'],
            ['name' => 'Central Denmark', 'country_id' => 'DK', 'type' => 'region', 'code' => '82'],
            ['name' => 'Southern Denmark', 'country_id' => 'DK', 'type' => 'region', 'code' => '83'],

            /*
            |--------------------------------------------------------------------------
            | Estonia
            |--------------------------------------------------------------------------
            */
            ['name' => 'Harju County', 'country_id' => 'EE', 'type' => 'county', 'code' => '37'],
            ['name' => 'Tartu County', 'country_id' => 'EE', 'type' => 'county', 'code' => '79'],

            /*
            |--------------------------------------------------------------------------
            | Finland
            |--------------------------------------------------------------------------
            */
            ['name' => 'Uusimaa', 'country_id' => 'FI', 'type' => 'region', 'code' => '18'],
            ['name' => 'Pirkanmaa', 'country_id' => 'FI', 'type' => 'region', 'code' => '11'],

            /*
            |--------------------------------------------------------------------------
            | France
            |--------------------------------------------------------------------------
            */
            ['name' => 'Île-de-France', 'country_id' => 'FR', 'type' => 'region', 'code' => 'IDF'],
            ['name' => 'Normandy', 'country_id' => 'FR', 'type' => 'region', 'code' => 'NOR'],
            ['name' => 'Occitania', 'country_id' => 'FR', 'type' => 'region', 'code' => 'OCC'],

            /*
            |--------------------------------------------------------------------------
            | Germany
            |--------------------------------------------------------------------------
            */
            ['name' => 'Baden-Württemberg', 'country_id' => 'DE', 'type' => 'state', 'code' => 'BW'],
            ['name' => 'Bavaria', 'country_id' => 'DE', 'type' => 'state', 'code' => 'BY'],
            ['name' => 'Berlin', 'country_id' => 'DE', 'type' => 'state', 'code' => 'BE'],

            /*
            |--------------------------------------------------------------------------
            | Greece
            |--------------------------------------------------------------------------
            */
            ['name' => 'Attica', 'country_id' => 'GR', 'type' => 'region', 'code' => 'I'],
            ['name' => 'Central Macedonia', 'country_id' => 'GR', 'type' => 'region', 'code' => 'B'],

            /*
            |--------------------------------------------------------------------------
            | Hungary
            |--------------------------------------------------------------------------
            */
            ['name' => 'Budapest', 'country_id' => 'HU', 'type' => 'county', 'code' => 'BU'],
            ['name' => 'Pest', 'country_id' => 'HU', 'type' => 'county', 'code' => 'PE'],

            /*
            |--------------------------------------------------------------------------
            | Ireland
            |--------------------------------------------------------------------------
            */
            ['name' => 'Dublin', 'country_id' => 'IE', 'type' => 'county', 'code' => 'D'],
            ['name' => 'Cork', 'country_id' => 'IE', 'type' => 'county', 'code' => 'CO'],

            /*
            |--------------------------------------------------------------------------
            | Italy
            |--------------------------------------------------------------------------
            */
            ['name' => 'Lombardy', 'country_id' => 'IT', 'type' => 'region', 'code' => '25'],
            ['name' => 'Lazio', 'country_id' => 'IT', 'type' => 'region', 'code' => '62'],
            ['name' => 'Sicily', 'country_id' => 'IT', 'type' => 'region', 'code' => '82'],

            /*
            |--------------------------------------------------------------------------
            | Latvia
            |--------------------------------------------------------------------------
            */
            ['name' => 'Riga', 'country_id' => 'LV', 'type' => 'municipality', 'code' => 'RIX'],

            /*
            |--------------------------------------------------------------------------
            | Lithuania
            |--------------------------------------------------------------------------
            */
            ['name' => 'Vilnius County', 'country_id' => 'LT', 'type' => 'county', 'code' => 'VL'],

            /*
            |--------------------------------------------------------------------------
            | Luxembourg
            |--------------------------------------------------------------------------
            */
            ['name' => 'Luxembourg', 'country_id' => 'LU', 'type' => 'district', 'code' => 'LU'],

            /*
            |--------------------------------------------------------------------------
            | Malta
            |--------------------------------------------------------------------------
            */
            ['name' => 'Northern Region', 'country_id' => 'MT', 'type' => 'region', 'code' => 'N'],
            ['name' => 'Southern Region', 'country_id' => 'MT', 'type' => 'region', 'code' => 'S'],

            /*
            |--------------------------------------------------------------------------
            | Netherlands
            |--------------------------------------------------------------------------
            */
            ['name' => 'Drenthe', 'country_id' => 'NL', 'type' => 'province', 'code' => 'DR'],
            ['name' => 'Flevoland', 'country_id' => 'NL', 'type' => 'province', 'code' => 'FL'],
            ['name' => 'Friesland', 'country_id' => 'NL', 'type' => 'province', 'code' => 'FR'],
            ['name' => 'Gelderland', 'country_id' => 'NL', 'type' => 'province', 'code' => 'GE'],
            ['name' => 'Groningen', 'country_id' => 'NL', 'type' => 'province', 'code' => 'GR'],
            ['name' => 'Limburg', 'country_id' => 'NL', 'type' => 'province', 'code' => 'LI'],
            ['name' => 'Noord-Brabant', 'country_id' => 'NL', 'type' => 'province', 'code' => 'NB'],
            ['name' => 'Noord-Holland', 'country_id' => 'NL', 'type' => 'province', 'code' => 'NH'],
            ['name' => 'Overijssel', 'country_id' => 'NL', 'type' => 'province', 'code' => 'OV'],
            ['name' => 'Utrecht', 'country_id' => 'NL', 'type' => 'province', 'code' => 'UT'],
            ['name' => 'Zeeland', 'country_id' => 'NL', 'type' => 'province', 'code' => 'ZE'],
            ['name' => 'Zuid-Holland', 'country_id' => 'NL', 'type' => 'province', 'code' => 'ZH'],

            /*
            |--------------------------------------------------------------------------
            | Poland
            |--------------------------------------------------------------------------
            */
            ['name' => 'Masovian Voivodeship', 'country_id' => 'PL', 'type' => 'voivodeship', 'code' => 'MZ'],
            ['name' => 'Lesser Poland Voivodeship', 'country_id' => 'PL', 'type' => 'voivodeship', 'code' => 'MA'],

            /*
            |--------------------------------------------------------------------------
            | Portugal
            |--------------------------------------------------------------------------
            */
            ['name' => 'Lisbon', 'country_id' => 'PT', 'type' => 'district', 'code' => 'LIS'],
            ['name' => 'Porto', 'country_id' => 'PT', 'type' => 'district', 'code' => 'POR'],

            /*
            |--------------------------------------------------------------------------
            | Romania
            |--------------------------------------------------------------------------
            */
            ['name' => 'Bucharest', 'country_id' => 'RO', 'type' => 'county', 'code' => 'B'],
            ['name' => 'Cluj', 'country_id' => 'RO', 'type' => 'county', 'code' => 'CJ'],

            /*
            |--------------------------------------------------------------------------
            | Slovakia
            |--------------------------------------------------------------------------
            */
            ['name' => 'Bratislava', 'country_id' => 'SK', 'type' => 'region', 'code' => 'BL'],
            ['name' => 'Košice', 'country_id' => 'SK', 'type' => 'region', 'code' => 'KI'],

            /*
            |--------------------------------------------------------------------------
            | Slovenia
            |--------------------------------------------------------------------------
            */
            ['name' => 'Ljubljana', 'country_id' => 'SI', 'type' => 'municipality', 'code' => '061'],

            /*
            |--------------------------------------------------------------------------
            | Spain
            |--------------------------------------------------------------------------
            */
            ['name' => 'Andalusia', 'country_id' => 'ES', 'type' => 'auton_community', 'code' => 'AN'],
            ['name' => 'Catalonia', 'country_id' => 'ES', 'type' => 'auton_community', 'code' => 'CT'],
            ['name' => 'Madrid', 'country_id' => 'ES', 'type' => 'auton_community', 'code' => 'MD'],

            /*
            |--------------------------------------------------------------------------
            | Sweden
            |--------------------------------------------------------------------------
            */
            ['name' => 'Stockholm County', 'country_id' => 'SE', 'type' => 'county', 'code' => 'AB'],
            ['name' => 'Skåne County', 'country_id' => 'SE', 'type' => 'county', 'code' => 'M'],

        ];

        DB::table('provinces')->upsert($provinces, ['country_id', 'code'], ['name', 'type']);
    }
}

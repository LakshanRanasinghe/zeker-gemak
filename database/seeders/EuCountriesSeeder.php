<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EuCountriesSeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            [
                'id' => 'AT',
                'name' => 'Austria',
                'phonecode' => 43,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'BE',
                'name' => 'Belgium',
                'phonecode' => 32,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'BG',
                'name' => 'Bulgaria',
                'phonecode' => 359,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'HR',
                'name' => 'Croatia',
                'phonecode' => 385,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'CY',
                'name' => 'Cyprus',
                'phonecode' => 357,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'CZ',
                'name' => 'Czech Republic',
                'phonecode' => 420,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'DK',
                'name' => 'Denmark',
                'phonecode' => 45,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'EE',
                'name' => 'Estonia',
                'phonecode' => 372,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'FI',
                'name' => 'Finland',
                'phonecode' => 358,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'FR',
                'name' => 'France',
                'phonecode' => 33,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'DE',
                'name' => 'Germany',
                'phonecode' => 49,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'GR',
                'name' => 'Greece',
                'phonecode' => 30,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'HU',
                'name' => 'Hungary',
                'phonecode' => 36,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'IE',
                'name' => 'Ireland',
                'phonecode' => 353,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'IT',
                'name' => 'Italy',
                'phonecode' => 39,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'LV',
                'name' => 'Latvia',
                'phonecode' => 371,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'LT',
                'name' => 'Lithuania',
                'phonecode' => 370,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'LU',
                'name' => 'Luxembourg',
                'phonecode' => 352,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'MT',
                'name' => 'Malta',
                'phonecode' => 356,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'NL',
                'name' => 'Netherlands',
                'phonecode' => 31,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'PL',
                'name' => 'Poland',
                'phonecode' => 48,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'PT',
                'name' => 'Portugal',
                'phonecode' => 351,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'RO',
                'name' => 'Romania',
                'phonecode' => 40,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'SK',
                'name' => 'Slovakia',
                'phonecode' => 421,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'SI',
                'name' => 'Slovenia',
                'phonecode' => 386,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'ES',
                'name' => 'Spain',
                'phonecode' => 34,
                'is_eu_member' => 1,
            ],
            [
                'id' => 'SE',
                'name' => 'Sweden',
                'phonecode' => 46,
                'is_eu_member' => 1,
            ],
        ];

        DB::table('countries')->upsert($countries, ['id'], ['name', 'phonecode', 'is_eu_member']);
    }
}

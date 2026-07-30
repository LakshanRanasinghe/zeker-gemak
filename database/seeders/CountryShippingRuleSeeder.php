<?php

namespace Database\Seeders;

use App\Models\CountryShippingRule;
use Illuminate\Database\Seeder;

class CountryShippingRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CountryShippingRule::query()->upsert([
            [
                'country_code' => 'NL',
                'country_name' => 'Netherlands',
                'shipping_cost' => '6.95',
                'free_shipping_threshold' => '60.00',
                'is_active' => true,
            ],
            [
                'country_code' => 'BE',
                'country_name' => 'Belgium',
                'shipping_cost' => '9.50',
                'free_shipping_threshold' => '120.00',
                'is_active' => true,
            ],
        ], ['country_code'], ['country_name', 'shipping_cost', 'free_shipping_threshold', 'is_active']);
    }
}

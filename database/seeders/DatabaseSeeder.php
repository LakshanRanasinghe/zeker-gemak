<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Konekt\Address\Seeds\Countries;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (DB::table('countries')->count() === 0) {
            $this->call(Countries::class);
        }

        $this->call([
            EuCountriesSeeder::class,
            EuProvincesSeeder::class,
            CountryShippingRuleSeeder::class,
            UserSeeder::class,
        ]);
    }
}

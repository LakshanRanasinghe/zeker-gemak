<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        User::firstOrCreate(
            [
                'email' => 'info@dayzsolutions.com',
            ],
            [
                'name' => 'Dayz Solutions',
                'password' => bcrypt('U4LHivwu0R1LCEX0iR5J'),
            ]
        );

    }
}

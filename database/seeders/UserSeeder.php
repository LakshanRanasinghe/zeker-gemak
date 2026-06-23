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
        if (app()->environment('local')) {

            $user = User::firstOrCreate(
                [
                    'email' => 'hirushan@dayzsolutions.com',
                ],
                [
                    'name' => 'Hirushan Perera',
                    'password' => bcrypt('elakiri123'),
                ]
            );

            $user->assignRole('admin');
        }
    }
}

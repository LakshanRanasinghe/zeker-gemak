<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserRoles extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate([
            'name' => 'admin',
        ]);

        $dashboard = Permission::firstOrCreate([
            'name' => 'view dashboard',
        ]);

        $customer = Role::firstOrCreate(['name' => 'customer']);

        $admin->givePermissionTo($dashboard);
    }
}

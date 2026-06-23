<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::firstOrCreate(['name' => 'edit pages']);
        Permission::firstOrCreate(['name' => 'delete pages']);
        Permission::firstOrCreate(['name' => 'create pages']);
        Permission::firstOrCreate(['name' => 'update pages']);

        // update cache to know about the newly created permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create roles and assign created permissions
        Role::firstOrCreate(['name' => 'shop-manager'])
            ->givePermissionTo(['edit pages', 'create pages', 'update pages', 'delete pages']);

        Role::firstOrCreate(['name' => 'admin'])
            ->givePermissionTo(['edit pages', 'create pages', 'update pages', 'delete pages']);

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());
    }
}

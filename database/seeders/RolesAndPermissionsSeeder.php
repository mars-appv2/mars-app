<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Permission pakai guard 'web'
        Permission::findOrCreate('manage mikrotik', 'web');
        Permission::findOrCreate('manage billing', 'web');

        // Roles
        $admin = Role::findOrCreate('admin', 'web');
        $admin->givePermissionTo(Permission::all());

        $operator = Role::findOrCreate('operator', 'web');
        $operator->syncPermissions(['manage mikrotik', 'manage billing']);

        $teknisi = Role::findOrCreate('teknisi', 'web');
        $teknisi->syncPermissions(['manage mikrotik']);
    }
}

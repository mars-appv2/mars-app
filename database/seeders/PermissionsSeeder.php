<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Daftar permission yang akan dibuat.
     * Tambahkan/modifikasi sesuai kebutuhan.
     */
    protected $permissions = [
        // Network / infra
        'manage mikrotik',
        // Traffic / NMS
        'view traffic',
        // Radius
        'manage radius',
        // Billing / Finance
        'manage billing',
        'manage finance',
        // Settings
        'manage settings',
        // Communication (new)
        'manage telegram',
        'manage whatsapp',
        // Role/user management
        'manage users',
        // Dashboard
        'view dashboard',
        // lainnya bisa ditambahkan di sini...
    ];

    public function run()
    {
        $this->command->info('Starting PermissionsSeeder...');

        // Prefer Spatie Permission jika ada
        if (class_exists(\Spatie\Permission\Models\Permission::class)
            && class_exists(\Spatie\Permission\Models\Role::class)) {

            $this->command->info('Detected Spatie Permission package. Creating permissions/roles using Spatie.');

            $roleClass = \Spatie\Permission\Models\Role::class;
            $permClass = \Spatie\Permission\Models\Permission::class;

            foreach ($this->permissions as $p) {
                $permClass::firstOrCreate(['name' => $p]);
                $this->command->info("Permission ensured: {$p}");
            }

            // Pastikan role 'admin' ada, lalu assign semua permission ke admin
            $admin = $roleClass::firstOrCreate(['name' => 'admin']);
            $admin->givePermissionTo($this->permissions);
            $this->command->info('Assigned all permissions to role: admin');

            // Jika ada role 'operator' buat subset permission (opsional)
            $operator = $roleClass::firstOrCreate(['name' => 'operator']);
            $operatorPerms = [
                'view dashboard',
                'view traffic',
                'manage radius',
            ];
            $operator->givePermissionTo($operatorPerms);
            $this->command->info('Assigned limited permissions to role: operator');

            $this->command->info('PermissionsSeeder finished (Spatie).');
            return;
        }

        // Fallback: jika ada tabel 'permissions' sederhana (custom implementation)
        if (\Schema::hasTable('permissions')) {
            $this->command->info('No Spatie detected but found permissions table. Seeding basic permissions into table.');

            foreach ($this->permissions as $p) {
                \DB::table('permissions')->insertOrIgnore([
                    'name' => $p,
                    'guard_name' => config('auth.defaults.guard', 'web'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Permission ensured (table): {$p}");
            }

            // Tidak otomatis assign ke role karena struktur tabel bisa berbeda
            $this->command->info('Permissions seeded into permissions table. Please assign them to roles manually if needed.');
            return;
        }

        // Jika tidak ada Spatie dan tidak ada table permissions
        $this->command->info('Spatie Permission package not found and no permissions table exists.');
        $this->command->info('Please install spatie/laravel-permission or adapt this seeder to your ACL implementation.');
    }
}

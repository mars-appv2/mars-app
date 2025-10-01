<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SetAdmin extends Command
{
    protected $signature = 'user:set-admin {email} {--password=}';
    protected $description = 'Create/Update admin user and assign full permissions';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: 'Mars12345';

        // daftar permission inti (tambahkan lagi di sini kalau ada yang baru)
        $perms = [
            'view dashboard',
            'mikrotik.view','mikrotik.manage',
            'pppoe.view','pppoe.manage',
            'ipstatic.view','ipstatic.manage',
            'traffic.view','traffic.manage',
            'billing.view','billing.manage',
            'payment.manage',
            'radius.view','radius.manage',
            'settings.manage','role.manage',
        ];
        foreach ($perms as $p) Permission::firstOrCreate(['name'=>$p]);

        $role = Role::firstOrCreate(['name'=>'admin']);
        // admin pegang semua permission yang ada saat ini
        $role->syncPermissions(Permission::all());

        $u = User::updateOrCreate(
            ['email'=>$email],
            ['name'=>'Admin Mars','password'=>Hash::make($password)]
        );
        if (!$u->hasRole('admin')) $u->assignRole('admin');

        $this->info("OK: {$email} set as admin. Password: {$password}");
    }
}

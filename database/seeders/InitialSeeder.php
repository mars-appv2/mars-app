<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class InitialSeeder extends Seeder{
  public function run(){
    $perms=['view dashboard','manage mikrotik','view traffic','manage billing','manage radius','manage settings'];
    foreach($perms as $p){ Permission::firstOrCreate(['name'=>$p]); }
    $admin=Role::firstOrCreate(['name'=>'admin']); $teknisi=Role::firstOrCreate(['name'=>'teknisi']); $staff=Role::firstOrCreate(['name'=>'staff']);
    $admin->syncPermissions($perms);
    $teknisi->syncPermissions(['view dashboard','manage mikrotik','view traffic','manage radius']);
    $staff->syncPermissions(['view dashboard','manage billing']);
    $email=env('ADMIN_EMAIL','solikin@mdnet.co.id'); $pass=env('ADMIN_PASSWORD','Mars#2025!');
    $u=User::firstOrCreate(['email'=>$email],['name'=>'Admin Mars','password'=>bcrypt($pass)]);
    if(!$u->hasRole('admin')) $u->assignRole('admin');
  }
}

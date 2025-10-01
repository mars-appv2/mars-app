<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RadiusService
{
    public function upsertUser(string $username, string $password, ?string $group = null, ?string $rateLimit = null): void
    {
        if (!Schema::hasTable('radcheck')) return;

        // Cleartext-Password
        DB::table('radcheck')
          ->updateOrInsert(['username'=>$username,'attribute'=>'Cleartext-Password'], ['op'=>':=','value'=>$password]);

        // Group mapping
        if ($group && Schema::hasTable('radusergroup')) {
            DB::table('radusergroup')->updateOrInsert(['username'=>$username,'groupname'=>$group], ['priority'=>1]);
        }

        // Rate limit (opsional) di radreply/groupreply
        if ($rateLimit && Schema::hasTable('radreply')) {
            DB::table('radreply')->updateOrInsert(
                ['username'=>$username,'attribute'=>'Mikrotik-Rate-Limit'],
                ['op'=>':=','value'=>$rateLimit]
            );
        }
    }

    public function suspend(string $username): void
    {
        // cara sederhana: hapus password → user tak bisa auth
        if (Schema::hasTable('radcheck')) {
            DB::table('radcheck')->where('username',$username)->delete();
        }
    }
}

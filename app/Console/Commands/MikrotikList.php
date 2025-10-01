<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Mikrotik;

class MikrotikList extends Command {
    protected $signature = 'mikrotik:list';
    protected $description = 'List Mikrotik devices from DB';
    public function handle() {
        $rows = Mikrotik::select('id','name','host','port','username','password','updated_at')->get();
        if ($rows->isEmpty()) { $this->warn('No devices. Add one in UI or DB.'); return 0; }
        $out = $rows->map(function($r){
            $pwd = (string)$r->password;
            $isHash = (strpos($pwd,'$2y$')===0?'yes':'no');
            return [
                'id'=>$r->id,'name'=>$r->name,'host'=>$r->host,'port'=>$r->port,
                'user'=>$r->username,'pwd_len'=>strlen($pwd),'bcrypt?'=>$isHash,'updated'=>$r->updated_at,
            ];
        });
        $this->table(['id','name','host','port','user','pwd_len','bcrypt?','updated'],$out);
        return 0;
    }
}

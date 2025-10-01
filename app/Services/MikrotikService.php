<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MikrotikService
{
    protected function pick($row, array $keys, $default=null) {
        foreach ($keys as $k) if (isset($row->$k)) return $row->$k;
        return $default;
    }

    protected function client($router)
    {
        if (!class_exists(\RouterOS\Client::class)) return null;

        $host = $this->pick($router, ['host','ip','ip_address','address']);
        $user = $this->pick($router, ['api_user','username','user']);
        $pass = $this->pick($router, ['api_pass','password','pass']);
        $port = (int)($this->pick($router, ['api_port','port'], 8728));

        $cfg  = (new \RouterOS\Config())
                  ->set('host',$host)->set('user',$user)->set('pass',$pass)->set('port',$port)
                  ->set('timeout',3)->set('attempts',3);
        return new \RouterOS\Client($cfg);
    }

    public function upsertPppSecret(int $mikrotikId, string $username, string $password, string $service='pppoe', ?string $profile=null): void
    {
        if (!Schema::hasTable('mikrotiks')) return;
        $router = DB::table('mikrotiks')->where('id',$mikrotikId)->first();
        if (!$router) return;

        $client = $this->client($router);
        if (!$client) return; // library belum terpasang → skip aman

        // cek ada?
        $resp = $client->query('/ppp/secret/print', ['?name'=>$username])->read();
        if (!empty($resp)) {
            $id = $resp[0]['.id'];
            $set = ['name'=>$username,'password'=>$password,'service'=>$service];
            if ($profile) $set['profile']=$profile;
            $client->query('/ppp/secret/set', array_merge($set, ['.id'=>$id]))->read();
        } else {
            $add = ['name'=>$username,'password'=>$password,'service'=>$service,'comment'=>"created by staff portal"];
            if ($profile) $add['profile']=$profile;
            $client->query('/ppp/secret/add', $add)->read();
        }
    }

    public function removePppSecret(int $mikrotikId, string $username): void
    {
        if (!Schema::hasTable('mikrotiks')) return;
        $router = DB::table('mikrotiks')->where('id',$mikrotikId)->first();
        if (!$router) return;

        $client = $this->client($router);
        if (!$client) return;

        $resp = $client->query('/ppp/secret/print', ['?name'=>$username])->read();
        if (!empty($resp)) {
            $client->query('/ppp/secret/remove', ['.id'=>$resp[0]['.id']])->read();
        }
    }
}

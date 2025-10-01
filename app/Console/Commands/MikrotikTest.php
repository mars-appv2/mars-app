<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Mikrotik;
use RouterOS\Client;
use RouterOS\Exceptions\ClientException;

class MikrotikTest extends Command {
    protected $signature = 'mikrotik:test {id} {--user=} {--pass=} {--host=} {--port=}';
    protected $description = 'Test login to Mikrotik (uses same RouterOS client as app)';
    public function handle() {
        $id = (int)$this->argument('id');
        $m = Mikrotik::find($id);
        if (!$m && !$this->option('host')) {
            $this->error("Device id {$id} not found. Use --host/--user/--pass/--port to override.");
            return 1;
        }
        $host = $this->option('host') ?: $m->host;
        $user = $this->option('user') ?: $m->username;
        $pass = $this->option('pass') ?: $m->password; // must be plaintext
        $port = (int)($this->option('port') ?: ($m->port ?: 8728));

        $this->info("Test connect -> host={$host} port={$port} user={$user} pass_len=".strlen($pass));
        try {
        \Log::info("ROS connect UI", ["host"=>$mikrotik->host??$host??null, "port"=>$mikrotik->port??$port??null, "user"=>$mikrotik->username??$user??null, "pwd_len"=>isset($mikrotik)?strlen($mikrotik->password):(isset($pass)?strlen($pass):null)]);
            $client = new Client(['host'=>$host,'user'=>$user,'pass'=>$pass,'port'=>$port,'timeout'=>5,'attempts'=>1]);
            $r = $client->query('/system/identity/print')->read();
            $name = $r[0]['name'] ?? 'OK';
            $this->info("CONNECTED. Identity: {$name}");
            return 0;
        } catch (ClientException $e) {
            $this->error('LOGIN FAILED: '.$e->getMessage());
            return 2;
        } catch (\Throwable $e) {
            $this->error('ERROR: '.$e->getMessage());
            return 3;
        }
    }
}

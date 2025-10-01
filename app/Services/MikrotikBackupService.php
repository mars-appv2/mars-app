<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use App\Models\Mikrotik;
use App\Models\MikrotikBackup;
use RouterOS\Client;
use RouterOS\Query;

class MikrotikBackupService
{
    public function runForDevice(Mikrotik $m, array $modes = null)
    {
        $modes = $modes ?: $this->defaultModes();
        $saved = [];

        $baseDir = "backups/mikrotik/{$m->id}";
        Storage::makeDirectory($baseDir);

        $client = null;
        try {
            $client = new Client([
                'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                'port'=>$m->port ?: 8728,'timeout'=>10,'attempts'=>1,
            ]);
        } catch (\Throwable $e) {
            // export/bin membutuhkan API; radius-json tetap jalan tanpa API
        }

        $ts = now()->format('Ymd-His');

        // 1) radius-json: snapshot user dari RADIUS
        if (in_array('radius-json', $modes, true)) {
            $json = $this->buildRadiusSnapshot($m);
            $fname = "radius-{$ts}.json";
            $path  = "{$baseDir}/{$fname}";
            Storage::put($path, json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            $saved[] = $this->record($m, 'radius-json', $path, ['count'=>count($json['users'] ?? [])]);
        }

        // 2) export-rsc (opsional)
        if ($client && in_array('export-rsc', $modes, true)) {
            try {
                $name = "export-{$ts}";
                $client->query((new Query('/export'))->equal('file',$name))->read();
                $got = $this->scpFromRouter($m, "{$name}.rsc", storage_path("app/{$baseDir}/{$name}.rsc"));
                if ($got) {
                    $saved[] = $this->record($m, 'export-rsc', "{$baseDir}/{$name}.rsc");
                    try { $client->query((new Query('/file/remove'))->equal('numbers',"{$name}.rsc"))->read(); } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {}
        }

        // 3) backup-bin (opsional)
        if ($client && in_array('backup-bin', $modes, true)) {
            try {
                $name = "backup-{$ts}";
                $client->query((new Query('/system/backup/save'))->equal('name',$name))->read();
                $got = $this->scpFromRouter($m, "{$name}.backup", storage_path("app/{$baseDir}/{$name}.backup"));
                if ($got) {
                    $saved[] = $this->record($m, 'backup-bin', "{$baseDir}/{$name}.backup");
                    try { $client->query((new Query('/file/remove'))->equal('numbers',"{$name}.backup"))->read(); } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {}
        }

        return $saved;
    }

    public function defaultModes(): array
    {
        $raw = env('MIKROTIK_BACKUP_MODES','radius-json,export-rsc,backup-bin');
        $parts = array_map('trim', explode(',', $raw));
        $modes = [];
        foreach ($parts as $p) {
            if ($p !== '') $modes[] = $p;
        }
        if (empty($modes)) $modes = ['radius-json'];
        return $modes;
    }

    protected function record(Mikrotik $m, string $type, string $storagePath, array $meta = [])
    {
        $full = storage_path("app/{$storagePath}");
        $size = is_file($full) ? filesize($full) : 0;
        $sha1 = is_file($full) ? sha1_file($full) : null;

        return MikrotikBackup::create([
            'mikrotik_id' => $m->id,
            'type'        => $type,
            'filename'    => $storagePath,
            'size'        => $size,
            'sha1'        => $sha1,
            'meta'        => $meta ?: null,
        ]);
    }

    protected function scpFromRouter(Mikrotik $m, string $remoteFile, string $destFullPath): bool
    {
        $sshpass = trim((string) shell_exec('command -v sshpass || which sshpass'));
        if ($sshpass === '') return false;

        $sshPort = (int) env('MIKROTIK_SSH_PORT', 22);
        $user    = $m->username;
        $pass    = $m->password;
        $host    = $m->host;

        @mkdir(dirname($destFullPath), 0775, true);

        $cmd = [
            $sshpass, '-p', $pass,
            'scp', '-P', (string)$sshPort, '-o', 'StrictHostKeyChecking=no',
            "{$user}@{$host}:{$remoteFile}", $destFullPath
        ];

        try {
            Process::fromShellCommandline(implode(' ', array_map('escapeshellarg', $cmd)), null, null, null, 60)->mustRun();
            return is_file($destFullPath) && filesize($destFullPath) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function buildRadiusSnapshot(Mikrotik $m): array
    {
        $subUsers = DB::table('subscriptions')->where('mikrotik_id',$m->id)->pluck('username')->all();
        $acctUsers = DB::connection('radius')->table('radacct')
            ->where('nasipaddress',$m->host)->distinct()->pluck('username')->all();

        $usernames = array_values(array_unique(array_filter(array_merge($subUsers,$acctUsers))));
        if (empty($usernames)) {
            $usernames = DB::connection('radius')->table('radcheck')
                ->where('attribute','Cleartext-Password')->distinct()->pluck('username')->all();
        }

        $passRows = DB::connection('radius')->select("
            SELECT username, MAX(value) as pw
            FROM radcheck
            WHERE attribute='Cleartext-Password' AND username IN (". $this->inPlaceholders($usernames) .")
            GROUP BY username
        ", $usernames);
        $passMap = [];
        foreach ($passRows as $r) $passMap[$r->username] = (string) $r->pw;

        $grpRows = DB::connection('radius')->table('radusergroup')
            ->whereIn('username', $usernames)->pluck('groupname','username');
        $grpMap = [];
        foreach ($grpRows as $u=>$g) $grpMap[$u] = (string)$g;

        $rejRows = DB::connection('radius')->table('radcheck')->whereIn('username',$usernames)
            ->where('attribute','Auth-Type')->where('op',':=')->where('value','Reject')
            ->pluck('username')->all();
        $rejMap = array_fill_keys($rejRows, true);

        $users = [];
        foreach ($usernames as $u) {
            $users[] = [
                'username' => $u,
                'password' => $passMap[$u] ?? null,
                'plan'     => $grpMap[$u] ?? null,
                'status'   => isset($rejMap[$u]) ? 'inactive' : 'active',
            ];
        }

        return [
            'device' => ['id'=>$m->id,'name'=>$m->name,'host'=>$m->host],
            'generated_at' => now()->toDateTimeString(),
            'users'  => $users,
        ];
    }

    protected function inPlaceholders(array $arr): string
    {
        if (empty($arr)) return "''";
        return implode(',', array_fill(0, count($arr), '?'));
    }

    /**
     * Restore PPP secrets di Mikrotik dari backup radius-json.
     * - Upsert (add/set) saja secara default.
     * - Jika $replace = true, akan menghapus secrets yang TIDAK ada di backup.
     *
     * @return array [processed, added, updated, deleted, failed]
     */
    public function restoreFromJson(Mikrotik $m, MikrotikBackup $backup, $replace = false)
    {
        if ($backup->type !== 'radius-json') {
            throw new \RuntimeException('Backup type must be radius-json.');
        }
        if (!Storage::exists($backup->filename)) {
            throw new \RuntimeException('Backup file not found.');
        }

        $raw = Storage::get($backup->filename);
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['users']) || !is_array($data['users'])) {
            throw new \RuntimeException('Invalid JSON backup content.');
        }

        $client = new Client([
            'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
            'port'=>$m->port ?: 8728,'timeout'=>10,'attempts'=>1,
        ]);

        // daftar profile
        $profiles = [];
        try {
            $rows = $client->query((new Query('/ppp/profile/print'))->equal('.proplist','name'))->read();
            foreach ($rows as $r) if (!empty($r['name'])) $profiles[$r['name']] = true;
        } catch (\Throwable $e) {}

        // daftar secrets eksisting (untuk opsi replace)
        $existing = [];
        if ($replace) {
            try {
                $rows = $client->query((new Query('/ppp/secret/print'))->equal('.proplist','name'))->read();
                foreach ($rows as $r) if (!empty($r['name'])) $existing[$r['name']] = true;
            } catch (\Throwable $e) {}
        }

        $processed=0; $added=0; $updated=0; $deleted=0; $failed=0;
        $keepNames = [];

        foreach ($data['users'] as $u) {
            $username = trim((string)($u['username'] ?? ''));
            if ($username === '') continue;

            $processed++;
            $keepNames[$username] = true;

            $pwd   = (string)($u['password'] ?? '');
            $plan  = (string)($u['plan'] ?? '');
            $stat  = (string)($u['status'] ?? 'active');
            $dis   = strtolower($stat) === 'inactive' ? 'yes' : 'no';

            if ($plan === '' || !isset($profiles[$plan])) {
                // fallback ke default, atau buat profile baru sesuai env
                $createProf = (bool) env('RESTORE_CREATE_PROFILE', false);
                if ($plan !== '' && $createProf) {
                    try {
                        $client->query((new Query('/ppp/profile/add'))->equal('name',$plan))->read();
                        $profiles[$plan] = true;
                    } catch (\Throwable $e) {
                        $plan = 'default';
                    }
                } else {
                    $plan = 'default';
                }
            }

            try {
                $res = $client->query((new Query('/ppp/secret/print'))->where('name',$username)->equal('.proplist','.id'))->read();

                if (empty($res)) {
                    $q = (new Query('/ppp/secret/add'))
                        ->equal('name',$username)
                        ->equal('password', $pwd !== '' ? $pwd : bin2hex(random_bytes(4)))
                        ->equal('profile',$plan)
                        ->equal('disabled',$dis);
                    $client->query($q)->read();
                    $added++;
                } else {
                    $id = $res[0]['.id'];
                    $q = (new Query('/ppp/secret/set'))
                        ->equal('.id',$id)
                        ->equal('password', $pwd !== '' ? $pwd : bin2hex(random_bytes(4)))
                        ->equal('profile',$plan)
                        ->equal('disabled',$dis);
                    $client->query($q)->read();
                    $updated++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        if ($replace && !empty($existing)) {
            // hapus secrets yang tidak ada di backup
            foreach ($existing as $name => $_) {
                if (!isset($keepNames[$name])) {
                    try {
                        $rows = $client->query((new Query('/ppp/secret/print'))->where('name',$name)->equal('.proplist','.id'))->read();
                        if (!empty($rows)) {
                            $client->query((new Query('/ppp/secret/remove'))->equal('.id',$rows[0]['.id']))->read();
                            $deleted++;
                        }
                    } catch (\Throwable $e) {
                        // abaikan error hapus tertentu
                    }
                }
            }
        }

        return compact('processed','added','updated','deleted','failed');
    }
}

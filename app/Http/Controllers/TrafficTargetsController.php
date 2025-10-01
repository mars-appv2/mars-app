<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Mikrotik;

class TrafficTargetsController extends Controller
{
    /* ================== Utils skema dinamis ================== */

    protected function targetTable()
    {
        foreach (['traffic_targets', 'monitor_targets', 'targets'] as $name) {
            if (Schema::hasTable($name)) return $name;
        }
        return null;
    }

    protected function pickColumn(string $table, array $candidates)
    {
        foreach ($candidates as $c) {
            if (Schema::hasColumn($table, $c)) return $c;
        }
        return null;
    }

    protected function mapRow($row)
    {
        $id = $row->id ?? ($row->target_id ?? null);

        $mikrotik_id = $row->mikrotik_id
            ?? $row->device_id
            ?? $row->mikrotik
            ?? $row->device
            ?? null;

        $target = $row->target
            ?? $row->target_key
            ?? $row->key
            ?? $row->iface
            ?? $row->name
            ?? $row->ip
            ?? $row->pppoe
            ?? null;

        $type = $row->type
            ?? $row->target_type
            ?? $row->category
            ?? $row->kind
            ?? null;

        $label = $row->label
            ?? $row->title
            ?? $row->alias
            ?? null;

        $enabled = $row->enabled
            ?? $row->is_enabled
            ?? $row->active
            ?? null;

        return (object)[
            'id'          => $id,
            'mikrotik_id' => $mikrotik_id,
            'target'      => $target,
            'type'        => $type,
            'label'       => $label,
            'enabled'     => $enabled,
        ];
    }

    /* ================== Queue helper (IP) ================== */

    protected function ensureQueueForIp(Mikrotik $m, string $ip, ?string $label = null): string
    {
        $name = 'md-'.preg_replace('/\/\d+$/','', $ip); // md-103.68.216.19

        $c = new \RouterOS\Client([
            'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
            'port'=>$m->port ?: 8728,'timeout'=>5,'attempts'=>1
        ]);

        // cek ada?
        $check = (new \RouterOS\Query('/queue/simple/print'))
            ->where('name',$name)->equal('.proplist','.id');
        $exists = $c->query($check)->read();

        if (empty($exists)) {
            $add = (new \RouterOS\Query('/queue/simple/add'))
                ->equal('name',$name)
                ->equal('target',$ip)          // 1.2.3.4/32
                ->equal('max-limit','0/0');    // monitor only
            if ($label) $add->equal('comment',$label);
            $c->query($add)->read();
        }

        return $name;
    }

    /* ================== Actions ================== */

    public function index()
    {
        if (!auth()->check()) abort(401);

        $devices = Mikrotik::select('id','name','host')->orderBy('name')->get();

        $table = $this->targetTable();
        $targets = collect();

        if ($table) {
            $rows = DB::table($table)->orderBy('id','desc')->limit(200)->get();
            $targets = $rows->map(fn($r) => $this->mapRow($r));
        }

        return view('traffic.targets', [
            'devices'  => $devices,
            'targets'  => $targets,
            'hasTable' => (bool)$table,
        ]);
    }

    public function store(Request $r)
    {
        if (!auth()->check()) abort(401);

        $table = $this->targetTable();
        if (!$table) return back()->withErrors('Tabel target belum ada. Buat salah satu: traffic_targets / monitor_targets / targets.');

        $data = $r->validate([
            'mikrotik_id' => ['required','integer'],
            'target_type' => ['nullable','string','max:50'],
            'target_key'  => ['required','string','max:191'],
            'label'       => ['nullable','string','max:191'],
            'enabled'     => ['nullable'],
            'auto_queue'  => ['nullable'], // checkbox
        ]);

        $colDevice  = $this->pickColumn($table, ['mikrotik_id','device_id','mikrotik','device']);
        $colTarget  = $this->pickColumn($table, ['target','target_key','key','iface','name','ip','pppoe']);
        $colType    = $this->pickColumn($table, ['type','target_type','category','kind']);
        $colLabel   = $this->pickColumn($table, ['label','title','alias','name_alias']);
        $colEnabled = $this->pickColumn($table, ['enabled','is_enabled','active']);
        $colCreated = Schema::hasColumn($table, 'created_at') ? 'created_at' : null;
        $colUpdated = Schema::hasColumn($table, 'updated_at') ? 'updated_at' : null;
        $colQueueNm = $this->pickColumn($table, ['queue_name']); // mungkin ada

        if (!$colDevice || !$colTarget) {
            return back()->withErrors('Skema tabel target perlu kolom device_id/mikrotik_id dan target/target_key/key/iface/name.');
        }

        $enabled = $r->boolean('enabled', true);

        $insert = [
            $colDevice => (int)$data['mikrotik_id'],
            $colTarget => trim($data['target_key']),
        ];
        if ($colType)    $insert[$colType]    = $data['target_type'] ?? null;
        if ($colLabel)   $insert[$colLabel]   = $data['label'] ?? null;
        if ($colEnabled) $insert[$colEnabled] = $enabled ? 1 : 0;
        if ($colCreated) $insert[$colCreated] = now();
        if ($colUpdated) $insert[$colUpdated] = now();

        // simpan & ambil id
        try {
            $newId = DB::table($table)->insertGetId($insert);
        } catch (\Throwable $e) {
            DB::table($table)->insert($insert);
            // fallback ambil id terakhir (tidak ideal, tapi cukup untuk mayoritas skema auto-increment)
            $newId = DB::table($table)->orderBy('id','desc')->value('id');
        }

        // Jika target IP dan minta auto_queue → buat queue & simpan nama queue
        if (($data['target_type'] ?? '') === 'ip' && $r->boolean('auto_queue', true)) {
            $mik   = Mikrotik::findOrFail($data['mikrotik_id']);
            $qname = $this->ensureQueueForIp($mik, $data['target_key'], $data['label'] ?? null);

            if ($colQueueNm && $newId) {
                DB::table($table)->where('id', $newId)->update([
                    $colQueueNm => $qname,
                    $colUpdated ? $colUpdated : 'updated_at' => now(),
                ]);
            }
        }

        return back()->with('ok','Target ditambahkan.');
    }

    public function show($id)
    {
        if (!auth()->check()) abort(401);
        $table = $this->targetTable();
        if (!$table) return back()->withErrors('Tabel target belum ada.');

        $row = DB::table($table)->where('id',$id)->first();
        if (!$row) return back()->withErrors('Target tidak ditemukan.');

        $m = $this->mapRow($row);

        if (!$m->mikrotik_id) {
            $colDevice = $this->pickColumn($table, ['mikrotik_id','device_id','mikrotik','device']);
            if ($colDevice && isset($row->$colDevice)) $m->mikrotik_id = $row->$colDevice;
        }
        if (!$m->target) {
            $colTarget = $this->pickColumn($table, ['target','target_key','key','iface','name','ip','pppoe']);
            if ($colTarget && isset($row->$colTarget)) $m->target = $row->$colTarget;
        }
        if (!$m->mikrotik_id || !$m->target) {
            return back()->withErrors('Kolom mikrotik_id/target tidak lengkap.');
        }

        $url = url('/traffic/target/view')
            . '?mikrotik_id=' . urlencode($m->mikrotik_id)
            . '&target='      . urlencode($m->target);

        return redirect()->to($url);
    }

    public function toggle($id)
    {
        if (!auth()->check()) abort(401);
        $table = $this->targetTable();
        if (!$table) return back()->withErrors('Tabel target belum ada.');

        $row = DB::table($table)->where('id',$id)->first();
        if (!$row) return back()->withErrors('Target tidak ditemukan.');

        $flagCol = $this->pickColumn($table, ['enabled','is_enabled','active']);
        if (!$flagCol) return back()->withErrors('Kolom status enable tidak ditemukan pada tabel.');

        $curr = (int)($row->$flagCol ?? 0);
        $upd  = [$flagCol => $curr ? 0 : 1];
        if (Schema::hasColumn($table,'updated_at')) $upd['updated_at'] = now();

        DB::table($table)->where('id',$id)->update($upd);

        return back()->with('ok', $curr ? 'Target dinonaktifkan.' : 'Target diaktifkan.');
    }

    public function destroy($id)
    {
        if (!auth()->check()) abort(401);
        $table = $this->targetTable();
        if (!$table) return back()->withErrors('Tabel target belum ada.');

        $row = DB::table($table)->where('id',$id)->first();
        if (!$row) return back()->withErrors('Target tidak ditemukan.');

        // Ambil field-field penting secara dinamis
        $colType   = $this->pickColumn($table, ['type','target_type','category','kind']);
        $colTarget = $this->pickColumn($table, ['target','target_key','key','iface','name','ip','pppoe']);
        $colDev    = $this->pickColumn($table, ['mikrotik_id','device_id','mikrotik','device']);
        $colQueue  = $this->pickColumn($table, ['queue_name']);

        $type   = $colType   ? ($row->$colType ?? null)   : null;
        $target = $colTarget ? ($row->$colTarget ?? null) : null;
        $devId  = $colDev    ? ($row->$colDev ?? null)    : null;
        $qname  = $colQueue  ? ($row->$colQueue ?? null)  : null;

        // Hapus queue jika ini target IP
        if ($type === 'ip' && $devId && $target) {
            $m = Mikrotik::find($devId);
            if ($m) {
                try {
                    $queue = $qname ?: ('md-'.preg_replace('/\/\d+$/','', $target));
                    $c = new \RouterOS\Client([
                        'host'=>$m->host,'user'=>$m->username,'pass'=>$m->password,
                        'port'=>$m->port ?: 8728,'timeout'=>5,'attempts'=>1
                    ]);
                    $find = (new \RouterOS\Query('/queue/simple/print'))
                        ->where('name',$queue)->equal('.proplist','.id');
                    $res = $c->query($find)->read();
                    if (!empty($res[0]['.id'])) {
                        $del = (new \RouterOS\Query('/queue/simple/remove'))
                            ->equal('.id',$res[0]['.id']);
                        $c->query($del)->read();
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Remove queue fail: '.$e->getMessage());
                }
            }
        }

        DB::table($table)->where('id',$id)->delete();

        return back()->with('ok', 'Target telah dihapus.');
    }

    public function export($id)
    {
        if (!auth()->check()) abort(401);
        $table = $this->targetTable();
        if (!$table) return back()->withErrors('Tabel target belum ada.');

        $row = DB::table($table)->where('id',$id)->first();
        if (!$row) return back()->withErrors('Target tidak ditemukan.');

        $m = $this->mapRow($row);

        if (!$m->mikrotik_id) {
            $colDevice = $this->pickColumn($table, ['mikrotik_id','device_id','mikrotik','device']);
            if ($colDevice && isset($row->$colDevice)) $m->mikrotik_id = $row->$colDevice;
        }
        if (!$m->target) {
            $colTarget = $this->pickColumn($table, ['target','target_key','key','iface','name','ip','pppoe']);
            if ($colTarget && isset($row->$colTarget)) $m->target = $row->$colTarget;
        }
        if (!$m->mikrotik_id || !$m->target) {
            return back()->withErrors('Kolom mikrotik_id/target tidak lengkap.');
        }

        return redirect()->route('traffic.export.pdf', [
            'mikrotik_id' => $m->mikrotik_id,
            'target'      => $m->target,
        ]);
    }
}

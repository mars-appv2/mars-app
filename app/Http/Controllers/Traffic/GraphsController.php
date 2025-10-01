<?php

namespace App\Http\Controllers\Traffic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class GraphsController extends Controller
{
    /* ===== Helpers ===== */

    private function devicesForUser()
    {
        // ambil semua; jika kamu punya table pivot user-device, adaptasi di sini
        if (Schema::hasTable('mikrotiks')) {
            return DB::table('mikrotiks')->orderBy('name')->get();
        }
        return collect();
    }

    private function keyForRow($row)
    {
        $base = isset($row->target_key) && $row->target_key ? $row->target_key : (isset($row->label) && $row->label ? $row->label : (string)$row->id);
        return Str::slug($base, '_');
    }

    private function groupForRow($row)
    {
        if (isset($row->target_type)) {
            if ($row->target_type === 'interface') return 'interfaces';
            if ($row->target_type === 'pppoe')     return 'pppoe';
            return 'ip';
        }
        return 'content';
    }

    private function pngSet($group, $key)
    {
        $base = storage_path('app/traffic/rrd/png/'.$group.'/'.$key);
        $set  = array(
            'day'   => $base.'/day.png',
            'week'  => $base.'/week.png',
            'month' => $base.'/month.png',
            'year'  => $base.'/year.png',
        );
        foreach ($set as $k=>$p) if (!is_file($p)) $set[$k] = null;
        return $set;
    }

    /* ===== Pages ===== */

    public function index()
    {
        return view('traffic.graphs.index');
    }

    public function interfaces()
    {
        $devices = $this->devicesForUser();
        $rows = DB::table('traffic_targets')->where('target_type','interface')->orderBy('label')->get();
        return view('traffic.graphs.interfaces', compact('devices','rows'));
    }

    public function pppoe()
    {
        $devices = $this->devicesForUser();
        $rows = DB::table('traffic_targets')->where('target_type','pppoe')->orderBy('label')->get();

        $top = array();
        if (Schema::hasTable('traffic_app_hourly')) {
            $top = DB::table('traffic_app_hourly')
                ->select('host_ip', DB::raw('SUM(bytes) as bytes'))
                ->whereDate('bucket', date('Y-m-d'))
                ->where('host_ip','!=','*')
                ->groupBy('host_ip')->orderBy('bytes','desc')->limit(50)->get();
        }
        return view('traffic.graphs.pppoe', compact('devices','rows','top'));
    }

    public function ip()
    {
        $devices = $this->devicesForUser();
        $rows = DB::table('traffic_targets')->where('target_type','ip')->orderBy('label')->get();
        return view('traffic.graphs.ip', compact('devices','rows'));
    }

    public function content()
    {
        $apps = array();
        if (Schema::hasTable('traffic_content_map')) {
            $apps = DB::table('traffic_content_map')->where('enabled',1)->orderBy('name')->get();
        }

        $top = array();
        if (Schema::hasTable('traffic_app_hourly')) {
            $top = DB::table('traffic_app_hourly')
                ->select('app', DB::raw('SUM(bytes) as bytes'))
                ->whereDate('bucket', date('Y-m-d'))
                ->groupBy('app')->orderBy('bytes','desc')->limit(50)->get();
        }

        $latency = array();
        if (Schema::hasTable('traffic_latency')) {
            $latency = DB::table('traffic_latency')
                ->select('target_ip',
                    DB::raw('AVG(rtt_ms) as avg_rtt'),
                    DB::raw('SUM(CASE WHEN success=0 THEN 1 ELSE 0 END) as timeouts'),
                    DB::raw('COUNT(*) as total'))
                ->where('created_at','>=', now()->subMinutes(10))
                ->groupBy('target_ip')->get();
        }

        return view('traffic.graphs.content', array(
            'apps'    => $apps,
            'items'   => $apps,    // kompat lama
            'top'     => $top,
            'latency' => $latency,
        ));
    }

    /* ===== Detail ===== */

    public function show($id)
    {
        $t = DB::table('traffic_targets')->where('id',(int)$id)->first();
        if (!$t) abort(404);
        $group = $this->groupForRow($t);
        $key   = $this->keyForRow($t);
        $png   = $this->pngSet($group,$key);
        $label = $t->label ? $t->label : $t->target_key;

        return view('traffic.graphs.show', array(
            'row'=>$t, 'group'=>$group, 'key'=>$key, 'png'=>$png, 'label'=>$label,
        ));
    }

    public function contentDetail($id)
    {
        $c = DB::table('traffic_content_map')->where('id',(int)$id)->first();
        if (!$c) abort(404);

        // key pakai IP (lebih stabil) kalau ada, selain itu pakai name
        $baseKey = $c->cidr ? $c->cidr : $c->name;
        $key   = Str::slug($baseKey, '_');
        $group = 'content';
        $png   = $this->pngSet($group,$key);
        $label = $c->name.' — '.($c->cidr ?: '');

        // buat objek tiruan agar view show.blade bisa pakai properti seragam
        $row = (object) array(
            'id' => $c->id,
            'target_type' => 'content',
            'target_key'  => $baseKey,
            'label'       => $c->name,
        );

        return view('traffic.graphs.show', array(
            'row'=>$row, 'group'=>$group, 'key'=>$key, 'png'=>$png, 'label'=>$label,
        ));
    }

    /* ===== Utilities ===== */

    public function saveSnapshot(Request $r)
    {
        $group = $r->input('group'); $key = $r->input('key'); $period = $r->input('period','day');
        $png = storage_path('app/traffic/rrd/png/'.$group.'/'.$key.'/'.$period.'.png');
        if (!is_file($png)) return back()->with('err','PNG not found');
        $ts = date('Ymd_His');
        $dstDir = storage_path('app/traffic/snapshots/'.$group.'/'.$key.'/'.$period);
        if (!is_dir($dstDir)) @mkdir($dstDir, 0775, true);
        $dst = $dstDir.'/'.$ts.'.png'; @copy($png,$dst);

        if (Schema::hasTable('traffic_snapshots')) {
            DB::table('traffic_snapshots')->insert(array(
                'group'=>$group,'key'=>$key,'period'=>$period,
                'png_path'=>str_replace(storage_path('app').DIRECTORY_SEPARATOR,'',$dst),
                'created_at'=>now(),'updated_at'=>now(),
            ));
        }
        return back()->with('ok','Snapshot saved');
    }

    public function png($group,$key,$period)
    {
        $allowG = array('interfaces','pppoe','ip','content');
        $allowP = array('day','week','month','year');
        if (!in_array($group,$allowG,true) || !in_array($period,$allowP,true)) abort(404);
        $png = storage_path('app/traffic/rrd/png/'.$group.'/'.$key.'/'.$period.'.png');
        if (!is_file($png)) abort(404);
        return response()->file($png);
    }

    public function pollOne($id)
    {
        try { Artisan::call('traffic:poll', array('--id'=>(int)$id)); }
        catch (\Throwable $e) { return back()->with('err','Poll error: '.$e->getMessage()); }
        return back()->with('ok','Grafik diperbarui.');
    }

    public function pingOne($id)
    {
        try { Artisan::call('traffic:ping', array('--id'=>(int)$id)); }
        catch (\Throwable $e) { return back()->with('err','Ping error: '.$e->getMessage()); }
        return back()->with('ok','Ping dijalankan.');
    }
}

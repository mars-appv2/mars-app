<?php

namespace App\Http\Controllers\TrafficGraphs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TrafficAppHourly;
use App\Models\TrafficSnapshot;

class TrafficGraphsController extends Controller
{
    public function index(Request $r) {
        return view('traffic_graphs.index');
    }

    public function interfaces(Request $r) {
        $root = 'traffic/rrd/png';
        $files = collect(Storage::allFiles($root))
            ->filter(fn($p)=>preg_match('~/(day|week|month|year)\.png$~',$p))
            ->groupBy(function($p){
                return preg_replace('~/[a-z]+\.png$~','',$p);
            })
            ->map(function($group){
                $base = $group->first();
                $base = preg_replace('~/[a-z]+\.png$~','',$base);
                return [
                    'base' => $base,
                    'day' => "$base/day.png",
                    'week'=> "$base/week.png",
                    'month'=>"$base/month.png",
                    'year'=> "$base/year.png",
                ];
            })
            ->values();

        return view('traffic_graphs.interfaces', compact('files'));
    }

    public function pppoe(Request $r) {
        $date = $r->input('date', Carbon::now()->toDateString());
        $rows = TrafficAppHourly::select('host_ip', DB::raw('SUM(bytes) as bytes'))
            ->whereDate('bucket',$date)->where('host_ip','!=','*')
            ->groupBy('host_ip')->orderByDesc('bytes')->limit(50)->get();
        return view('traffic_graphs.pppoe', compact('rows','date'));
    }

    public function content(Request $r) {
        $date = $r->input('date', Carbon::now()->toDateString());
        $rows = TrafficAppHourly::select('app', DB::raw('SUM(bytes) as bytes'))
            ->whereDate('bucket',$date)
            ->groupBy('app')->orderByDesc('bytes')->limit(50)->get();
        return view('traffic_graphs.content', compact('rows','date'));
    }

    public function savePng(Request $r) {
        $type = $r->input('type');
        $pngBase64 = $r->input('png');
        $ts = Carbon::now()->format('Ymd_His');
        $name = $type.'_'.$ts.'.png';

        if ($pngBase64 && preg_match('~^data:image/png;base64,~', $pngBase64)) {
            $data = base64_decode(substr($pngBase64, strlen('data:image/png;base64,')));
            Storage::put('traffic/snapshots/'.$name, $data);
            TrafficSnapshot::create(['type'=>$type,'path'=>'traffic/snapshots/'.$name,'meta'=>json_encode(['via'=>'client'])]);
            return back()->with('ok','Snapshot saved.');
        }
        return back()->with('err','No PNG provided');
    }

    public function image($path) {
        $full = storage_path('app/'.$path);
        if (!is_file($full)) abort(404);
        return response()->file($full);
    }
}

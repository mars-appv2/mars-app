<?php
namespace App\Http\Controllers\Traffic;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\TrafficAppHourly;
use App\Models\TrafficSnapshot;

class TrafficController extends Controller {
  public function index(Request $r){ return view('traffic.index'); }
  public function interfaces(Request $r){
    $files = collect(Storage::files('traffic/rrd/png'))->filter(fn($p)=>preg_match('~\.png$~',$p))->values();
    return view('traffic.interfaces', compact('files'));
  }
  public function pppoe(Request $r){
    $date = $r->input('date', Carbon::now()->toDateString());
    $rows = TrafficAppHourly::select('host_ip', DB::raw('SUM(bytes) as bytes'))->whereDate('bucket',$date)->groupBy('host_ip')->orderByDesc('bytes')->limit(100)->get();
    return view('traffic.pppoe', compact('rows','date'));
  }
  public function content(Request $r){
    $date = $r->input('date', Carbon::now()->toDateString());
    $rows = TrafficAppHourly::select('app', DB::raw('SUM(bytes) as bytes'))->whereDate('bucket',$date)->groupBy('app')->orderByDesc('bytes')->get();
    return view('traffic.content', compact('rows','date'));
  }
  public function savePng(Request $r){
    $type=$r->input('type'); $b=$r->input('png'); $ts=date('Ymd_His'); $name=$type.'_'.$ts.'.png';
    if($b && preg_match('~^data:image/png;base64,~',$b)){ $data=base64_decode(substr($b,22)); Storage::put('traffic/snapshots/'.$name,$data); TrafficSnapshot::create(['type'=>$type,'path'=>'traffic/snapshots/'.$name,'meta'=>json_encode(['via'=>'client'])]); return back()->with('ok','Snapshot saved'); }
    return back()->with('err','No PNG provided');
  }
  public function image($path){ $full=storage_path('app/'.$path); if(!is_file($full)) abort(404); return response()->file($full); }
}
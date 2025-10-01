<?php
namespace App\Http\Controllers\Traffic;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecordsController extends Controller
{
    public function index()
    {
        $groups = ['interfaces','pppoe','ip','content'];
        $counts = [];
        if (Schema::hasTable('traffic_snapshots')) {
            foreach ($groups as $g) {
                $counts[$g] = DB::table('traffic_snapshots')->where('group',$g)->count();
            }
        }
        return view('traffic.records.index', compact('groups','counts'));
    }

    public function group($group)
    {
        $rows = [];
        if (Schema::hasTable('traffic_snapshots')) {
            $rows = DB::table('traffic_snapshots')->where('group',$group)
                ->orderByDesc('created_at')->limit(500)->get();
        }
        return view('traffic.records.group', compact('group','rows'));
    }

    public function detail($group, $key)
    {
        $rows = [];
        if (Schema::hasTable('traffic_snapshots')) {
            $rows = DB::table('traffic_snapshots')->where('group',$group)->where('key',$key)
                ->orderByDesc('created_at')->limit(500)->get();
        }
        return view('traffic.records.detail', compact('group','key','rows'));
    }
}

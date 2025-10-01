<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NocController extends Controller
{
    public function index()
    {
        // Top 10 sesi aktif
        $sessions = [];
        if (Schema::hasTable('radacct')) {
            try {
                $sessions = DB::table('radacct')
                    ->select('username','callingstationid','framedipaddress','acctsessiontime','nasipaddress','acctstarttime')
                    ->whereNull('acctstoptime')
                    ->orderByDesc('acctstarttime')
                    ->limit(10)->get();
            } catch (\Throwable $e) { $sessions = []; }
        }
        return view('staff.noc', compact('sessions'));
    }

    // JSON untuk mini-chart: total sesi per jam (24h)
    public function sessions()
    {
        $labels = []; $vals = [];
        for ($i=23;$i>=0;$i--){
            $labels[] = now()->subHours($i)->format('H');
            $vals[] = 0;
        }
        if (Schema::hasTable('radacct')) {
            try {
                // hitung sesi aktif per jam by acctstarttime
                for ($i=0;$i<24;$i++){
                    $h = now()->subHours(23-$i);
                    $cnt = DB::table('radacct')
                        ->whereBetween('acctstarttime', [$h->copy()->startOfHour(), $h->copy()->endOfHour()])
                        ->count();
                    $vals[$i] = $cnt;
                }
            } catch (\Throwable $e) {}
        }
        return response()->json(['labels'=>$labels,'values'=>$vals]);
    }
}

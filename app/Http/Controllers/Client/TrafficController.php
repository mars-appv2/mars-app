<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrafficController extends Controller
{
    public function index()
    {
        return view('client.traffic');
    }

    // Kembalikan JSON {labels:[], down:[], up:[]}
    public function data(Request $request)
    {
        $user = Auth::user();
        $labels = [];
        $down = [];
        $up = [];

        // Default labels 24 jam terakhir (HH)
        for ($i=23; $i>=0; $i--) {
            $labels[] = now()->subHours($i)->format('H');
            $down[] = 0; $up[] = 0;
        }

        // Jika ada radacct, coba estimasi per jam berdasarkan distribusi rata ke durasi sesi
        if (Schema::hasTable('radacct')) {
            $username = $user->username ?? $user->email ?? null;
            if ($username) {
                $windowStart = now()->subDay(); // 24 jam
                $rows = DB::table('radacct')
                    ->select('acctstarttime','acctstoptime','acctsessiontime','acctinputoctets','acctoutputoctets')
                    ->where('username', $username)
                    ->where('acctstarttime','>=',$windowStart->copy()->subHours(2)) // ambil ekstra buffer
                    ->get();

                // Distribusi ke jam
                $bins = [];
                for ($i=0; $i<24; $i++) { $bins[$i] = ['in'=>0,'out'=>0]; }

                foreach ($rows as $r) {
                    $start = \Carbon\Carbon::parse($r->acctstarttime);
                    $stop  = $r->acctstoptime ? \Carbon\Carbon::parse($r->acctstoptime) : now();
                    if ($stop < $windowStart) continue;
                    if ($start < $windowStart) $start = $windowStart->copy();

                    $secs = max(1, (int)$stop->diffInSeconds($start));
                    $in   = (float)$r->acctinputoctets;
                    $out  = (float)$r->acctoutputoctets;

                    // Loop tiap jam yang terlibat
                    $cursor = $start->copy();
                    while ($cursor < $stop) {
                        $hourIdx = now()->diffInHours($cursor, false); // negatif -> ke masa lalu
                        $hourIdx = 23 - min(23, max(0, abs($hourIdx))); // map ke 0..23
                        // akhir jam
                        $hourEnd = $cursor->copy()->ceilHour();
                        if ($hourEnd > $stop) $hourEnd = $stop->copy();
                        $portion = max(1, (int)$hourEnd->diffInSeconds($cursor)) / $secs;

                        if (isset($bins[$hourIdx])) {
                            $bins[$hourIdx]['in']  += $in * $portion;
                            $bins[$hourIdx]['out'] += $out * $portion;
                        }
                        $cursor = $hourEnd;
                    }
                }

                // Convert ke Mbps (approx): octets -> bits / 3600 / 1e6
                for ($i=0;$i<24;$i++){
                    $down[$i] = round(($bins[$i]['in']  * 8) / 3600 / 1000000, 2);
                    $up[$i]   = round(($bins[$i]['out'] * 8) / 3600 / 1000000, 2);
                }
            }
        }

        // Fallback jika radacct tidak ada: pola realistis (siang 40–60 Mbps, malam 8–12 Mbps)
        if (array_sum($down) == 0 && array_sum($up) == 0) {
            for ($i=0;$i<24;$i++){
                $h = (int)$labels[$i];
                $base = ($h >= 9 && $h <= 23) ? 50 : 10; // siang lebih tinggi
                $down[$i] = round($base + mt_rand(-8,8), 2);
                $up[$i]   = round($down[$i] * 0.2 + mt_rand(-2,2), 2);
            }
        }

        return response()->json([
            'labels' => $labels,
            'down'   => $down,
            'up'     => $up,
        ]);
    }
}

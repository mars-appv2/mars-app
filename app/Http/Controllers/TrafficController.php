<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrafficController extends Controller
{
    // POST /traffic/sample  -> simpan sampel RX/TX
    public function sampleStore(Request $r)
    {
        $data = $r->validate([
            'mikrotik_id' => ['required','integer'],
            'target'      => ['required','string','max:191'],
            'rx_bps'      => ['required','integer','min:0'],
            'tx_bps'      => ['required','integer','min:0'],
        ]);

        DB::table('traffic_samples')->insert([
            'mikrotik_id' => (int)$data['mikrotik_id'],
            'target'      => $data['target'],
            'rx_bps'      => (int)$data['rx_bps'],
            'tx_bps'      => (int)$data['tx_bps'],
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['ok'=>true]);
    }

    // GET /traffic/series?mikrotik_id=1&target=ether1&range=24h
    public function series(Request $r)
    {
        $r->validate([
            'mikrotik_id' => ['required','integer'],
            'target'      => ['required','string','max:191'],
            'range'       => ['nullable','string'],
            'step'        => ['nullable','string'],
        ]);

        $mikrotik_id = (int)$r->mikrotik_id;
        $target      = $r->target;
        $range       = $r->range ?: '24h';

        $from = now();
        if ($range==='1h')      $from = now()->subHour();
        elseif ($range==='24h') $from = now()->subDay();
        elseif ($range==='7d')  $from = now()->subDays(7);
        elseif ($range==='30d') $from = now()->subDays(30);
        elseif ($range==='365d')$from = now()->subDays(365);

        $step = $r->step ?: (in_array($range,['1h','24h']) ? 'minute' : (in_array($range,['7d','30d'])?'hour':'day'));
        $fmt  = $step==='minute' ? '%Y-%m-%d %H:%i:00' : ($step==='hour' ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d 00:00:00');

        $rows = DB::table('traffic_samples')
            ->selectRaw("DATE_FORMAT(created_at,'{$fmt}') AS t, AVG(rx_bps) AS rx, AVG(tx_bps) AS tx")
            ->where('mikrotik_id',$mikrotik_id)
            ->where('target',$target)
            ->where('created_at','>=',$from)
            ->groupBy('t')
            ->orderBy('t')
            ->get();

        return response()->json(['ok'=>true,'range'=>$range,'step'=>$step,'series'=>$rows]);
    }

    // GET /traffic/target/view?mikrotik_id=..&target=..
    public function view(Request $r)
    {
        $r->validate([
            'mikrotik_id' => ['required','integer'],
            'target'      => ['required','string','max:191'],
        ]);

        return view('traffic.target_view', [
            'mikrotik_id' => (int)$r->mikrotik_id,
            'target'      => $r->target,
        ]);
    }

    // GET /traffic/export/pdf?mikrotik_id=..&target=..
    public function exportPdf(Request $r)
    {
        $r->validate([
            'mikrotik_id' => ['required','integer'],
            'target'      => ['required','string','max:191'],
        ]);

        $ranges = [
            'Harian (24 jam)'   => ['from'=>now()->subDay(),   'fmt'=>'%Y-%m-%d %H:%i:00'],
            'Mingguan (7 hari)' => ['from'=>now()->subDays(7), 'fmt'=>'%Y-%m-%d %H:00:00'],
            'Bulanan (30 hari)' => ['from'=>now()->subDays(30),'fmt'=>'%Y-%m-%d %H:00:00'],
            'Tahunan (365 hari)'=> ['from'=>now()->subDays(365),'fmt'=>'%Y-%m-%d 00:00:00'],
        ];

        $sections = [];
        foreach ($ranges as $label=>$cfg) {
            $rows = DB::table('traffic_samples')
                ->selectRaw("DATE_FORMAT(created_at,'{$cfg['fmt']}') AS t, AVG(rx_bps) AS rx, AVG(tx_bps) AS tx, MAX(rx_bps) AS rx_max, MAX(tx_bps) AS tx_max")
                ->where('mikrotik_id',(int)$r->mikrotik_id)
                ->where('target',$r->target)
                ->where('created_at','>=',$cfg['from'])
                ->groupBy('t')
                ->orderBy('t')
                ->get();
            $sections[] = ['label'=>$label,'rows'=>$rows];
        }

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('traffic.pdf', [
                'mikrotik_id'=>(int)$r->mikrotik_id,
                'target'=>$r->target,
                'sections'=>$sections
            ])->setPaper('a4','portrait');
            return $pdf->download("traffic_{$r->mikrotik_id}_{$r->target}.pdf");
        }

        return view('traffic.pdf', [
            'mikrotik_id'=>(int)$r->mikrotik_id,
            'target'=>$r->target,
            'sections'=>$sections
        ]);
    }
}

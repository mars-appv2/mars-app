<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use App\Models\Mikrotik;
use App\Services\RouterOSService;
use Carbon\Carbon;

class RadiusApiController extends Controller
{
    // JSON untuk auto-refresh sessions
    public function sessionsJson(Request $r)
    {
        $this->middleware('auth');
        $user = auth()->user();

        $devices = Mikrotik::forUser($user)
            ->where('radius_enabled',1)
            ->orderBy('name')
            ->get(['id','name','host','username','password','port']);

        $sel = (int)$r->query('mikrotik_id',0);
        if ($sel && !$devices->pluck('id')->contains($sel)) {
            return response()->json(['ok'=>false,'err'=>'unauthorized device'],403);
        }

        $q = trim((string)$r->query('q',''));

        $rad = DB::connection('radius');
        $radRows = $rad->table('radacct')
            ->select([
                'username','nasipaddress','framedipaddress',
                'acctstarttime','acctsessiontime','acctinputoctets','acctoutputoctets',
                'callingstationid'
            ])
            ->when($q!=='' , fn($qq)=>$qq->where('username','like',"%{$q}%"))
            ->when($sel>0, function($qq) use($devices,$sel){
                $host = optional($devices->firstWhere('id',$sel))->host;
                if ($host) $qq->where('nasipaddress',$host);
            })
            ->whereNull('acctstoptime')
            ->orderByDesc('acctstarttime')
            ->limit(1000)
            ->get()
            ->map(function($x){
                $x->source = 'radius';
                return $x;
            });

        $sess = collect($radRows);

        // Tambahkan fallback dari Mikrotik /ppp/active jika ada device terpilih
        if ($sel > 0) {
            $mk = $devices->firstWhere('id',$sel);
            if ($mk) {
                try {
                    $svc = new RouterOSService($mk);
                    $active = $svc->pppActive();

                    foreach ($active as $a) {
                        $uptime = $a['uptime'] ?? '0s';
                        $secs = $this->uptimeToSeconds($uptime);
                        $start = Carbon::now()->subSeconds($secs);

                        $row = (object)[
                            'username'         => $a['name'] ?? '',
                            'nasipaddress'     => $mk->host,
                            'framedipaddress'  => $a['address'] ?? null,
                            'acctstarttime'    => $start->toDateTimeString(),
                            'acctsessiontime'  => $secs,
                            'acctinputoctets'  => null,
                            'acctoutputoctets' => null,
                            'callingstationid' => $a['caller-id'] ?? null,
                            'source'           => 'router',
                        ];
                        $sess->push($row);
                    }
                } catch (\Throwable $e) {
                    // biarin
                }
            }
        }

        $uniq = $sess->unique(function($x){
            return ($x->username ?? '').'|'.($x->framedipaddress ?? '');
        })->values();

        return response()->json(['ok'=>true,'rows'=>$uniq],200);
    }

    // CoA disconnect by username (radclient)
    public function coaDisconnect(Request $r)
    {
        $this->middleware('auth');

        $r->validate(['username'=>'required|string|max:255']);

        $host   = env('RADIUS_HOST','127.0.0.1');
        $port   = (int) env('RADIUS_COA_PORT', 3799);
        $secret = (string) env('RADIUS_SECRET','');

        $username = $r->input('username');

        try {
            $cmd = sprintf(
                'printf "User-Name := %s\n" | timeout 3 radclient -x %s:%d disconnect %s 2>&1',
                escapeshellarg($username),
                escapeshellarg($host),
                $port,
                escapeshellarg($secret)
            );
            $p = Process::fromShellCommandline($cmd);
            $p->setTimeout(5);
            $p->run();

            $out = $p->getOutput().$p->getErrorOutput();
            $ok  = (strpos($out,'Disconnect-ACK') !== false) || (strpos($out,'received CoA-ACK') !== false);
            return back()->with($ok ? 'ok' : 'err', $ok ? 'CoA disconnect terkirim.' : ('CoA gagal: '.$out));
        } catch (\Throwable $e) {
            return back()->with('err','CoA error: '.$e->getMessage());
        }
    }

    private function uptimeToSeconds(string $uptime): int
    {
        $pattern = '/(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?/i';
        if (preg_match($pattern, $uptime, $m)) {
            $d = (int)($m[1] ?? 0);
            $h = (int)($m[2] ?? 0);
            $i = (int)($m[3] ?? 0);
            $s = (int)($m[4] ?? 0);
            return $d*86400 + $h*3600 + $i*60 + $s;
        }
        return 0;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use App\Models\Mikrotik;
use App\Services\RouterOSService;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $r)
    {
        $user = auth()->user();

        // ===== Devices visible to current user =====
        $devices = Mikrotik::forUser($user)
            ->orderBy('name')
            ->get(['id','name','host','username','password','port']);

        $selId = (int) $r->query('mikrotik_id', $devices->first()->id ?? 0);
        if ($selId && !$devices->pluck('id')->contains($selId)) {
            $selId = $devices->first()->id ?? 0;
        }

        $hosts = $devices->pluck('host')->filter()->values()->all();

        // ===== RADIUS status (cached 20s) =====
        $rad = Cache::remember('dash.radius.status', 20, function () {
            return $this->radiusStatus();
        });
        $radiusUp   = (bool) ($rad['ok']  ?? false);
        $radiusErr  = (string) ($rad['msg'] ?? '');
        $radiusHost = (string) ($rad['host'] ?? '');

        // ===== Active Users (gabung radacct + /ppp/active), unik per username|IP =====
        // Catatan:
        // - Tabel/kolom radacct berbeda-beda implementasi. Untuk akurasi, kita gabung:
        //   a) Base: acctstoptime NULL / '' / '0000-00-00 00:00:00'
        //   b) Interim (jika kolom acctupdatetime ada): update dalam <= 10 menit
        // - Lalu merge dengan data /ppp/active dari masing-masing router (fallback) dan dedup.
        $cacheActiveKey = 'dash.active.users.'.md5(json_encode($hosts));
        $useActiveCache = (bool) env('DASH_ACTIVE_CACHE', true);
        $activeUsers = $useActiveCache
            ? Cache::remember($cacheActiveKey, (int) env('DASH_ACTIVE_CACHE_TTL', 15), function () use ($hosts, $devices) {
                return $this->computeActiveUsers($hosts, $devices);
            })
            : $this->computeActiveUsers($hosts, $devices);

        // ===== Billing totals (COALESCE total/amount) dibatasi ke perangkat user jika ada) =====
        $paidTotal = 0;
        $unpaidTotal = 0;
        try {
            $invQ = DB::table('invoices as i')
                ->leftJoin('subscriptions as s','s.id','=','i.subscription_id');

            if ($devices->isNotEmpty()) {
                $ids = $devices->pluck('id')->all();
                $invQ->where(function ($q) use ($ids) {
                    $q->whereIn('i.mikrotik_id', $ids)
                      ->orWhereIn('s.mikrotik_id', $ids);
                });
            }

            $paidTotal   = (int) (clone $invQ)->where('i.status','paid')
                ->sum(DB::raw('COALESCE(i.total, i.amount, 0)'));
            $unpaidTotal = (int) (clone $invQ)->where('i.status','unpaid')
                ->sum(DB::raw('COALESCE(i.total, i.amount, 0)'));
        } catch (\Throwable $e) {
            $paidTotal = 0; $unpaidTotal = 0;
            Log::warning('[DASH] billing totals error: '.$e->getMessage());
        }

        // ===== Interfaces untuk device terpilih (buat dropdown awal) =====
        $interfaces = [];
        if ($selId) {
            $mk = $devices->firstWhere('id', $selId);
            if ($mk) {
                try {
                    $svc = new RouterOSService($mk);
                    $ifs = $svc->interfaces();
                    foreach ($ifs as $row) {
                        if (!empty($row['name'])) $interfaces[] = $row['name'];
                    }
                } catch (\Throwable $e) {
                    // biarkan kosong
                    Log::notice('[DASH] interfaces fetch fail for device '.$mk->id.': '.$e->getMessage());
                }
            }
        }

        return view('home', [
            'devices'     => $devices,
            'selId'       => $selId,
            'interfaces'  => $interfaces,
            'radiusUp'    => $radiusUp,
            'radiusErr'   => $radiusErr,
            'radiusHost'  => $radiusHost,
            'activeUsers' => $activeUsers,
            'paidTotal'   => $paidTotal,
            'unpaidTotal' => $unpaidTotal,
        ]);
    }

    /**
     * Hitung jumlah user aktif sekarang dengan sumber:
     *   1) radius.radacct (base + interim)
     *   2) /ppp/active dari RouterOS (fallback)
     * Hasil akhir dedup per "username|framedipaddress".
     */
    private function computeActiveUsers(array $hosts, $devices): int
    {
        $radacctRows = $this->fetchActiveFromRadacct($hosts);
        $pppRows     = $this->fetchActiveFromRouters($devices);

        // Gabungkan & unik berdasarkan username|IP
        $all  = collect($radacctRows)->concat($pppRows);
        $uniq = $all->unique(function ($x) {
            $u  = is_object($x) ? ($x->username ?? '')        : ($x['username'] ?? '');
            $ip = is_object($x) ? ($x->framedipaddress ?? '') : ($x['framedipaddress'] ?? '');
            return $u.'|'.$ip;
        });

        return $uniq->count();
    }

    /**
     * Ambil baris aktif dari radacct:
     * - Base   : stoptime NULL/''/'0000-00-00 00:00:00'
     * - Interim: jika kolom acctupdatetime ada, pilih update dalam <= 10 menit
     * Filter opsional: hanya nasipaddress yang ada di daftar host user.
     * Return: Collection of stdClass { username, nasipaddress, framedipaddress, acctstarttime }
     */
    private function fetchActiveFromRadacct(array $hosts)
    {
        $tbl = env('RADIUS_RADACCT_TABLE', 'radacct');
        $rowsBase = collect();
        $rowsInterim = collect();

        // BASE: stoptime null/zero
        try {
            $rowsBase = DB::connection('radius')->table($tbl)
                ->select(['username','nasipaddress','framedipaddress','acctstarttime'])
                ->when(!empty($hosts), function ($q) use ($hosts) {
                    $q->whereIn('nasipaddress', $hosts);
                })
                ->where(function ($q) {
                    $q->whereNull('acctstoptime')
                      ->orWhere('acctstoptime', '')
                      ->orWhere('acctstoptime', '0000-00-00 00:00:00');
                })
                ->orderByDesc('acctstarttime')
                ->limit(20000)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[DASH] radacct base query fail: '.$e->getMessage());
        }

        // INTERIM: acctupdatetime (jika ada) dalam 10 menit terakhir
        try {
            $dbName = config('database.connections.radius.database');
            $hasCol = DB::connection('radius')->select("
                SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'acctupdatetime'
                LIMIT 1
            ", [$dbName, $tbl]);

            if ($hasCol) {
                $rowsInterim = DB::connection('radius')->table($tbl)
                    ->select(['username','nasipaddress','framedipaddress','acctstarttime'])
                    ->when(!empty($hosts), function ($q) use ($hosts) {
                        $q->whereIn('nasipaddress', $hosts);
                    })
                    ->whereNotNull('username')
                    ->where('username','!=','')
                    ->whereRaw("acctupdatetime >= (NOW() - INTERVAL 10 MINUTE)")
                    ->orderByDesc('acctstarttime')
                    ->limit(20000)
                    ->get();
            }
        } catch (\Throwable $e) {
            Log::notice('[DASH] radacct interim query skip/fail: '.$e->getMessage());
        }

        // Gabung base + interim lalu dedup di tingkat record (username|ip)
        $all = $rowsBase->concat($rowsInterim);
        return $all->unique(function ($x) {
            return ($x->username ?? '').'|'.($x->framedipaddress ?? '');
        })->values();
    }

    /**
     * Ambil daftar aktif dari tiap router (/ppp/active). Return collection objek seragam
     * { username, nasipaddress, framedipaddress, acctstarttime } — acctstarttime null.
     */
    private function fetchActiveFromRouters($devices)
    {
        $list = collect();
        foreach ($devices as $m) {
            try {
                $svc  = new RouterOSService($m);
                $rows = $svc->pppActive(); // name, address, uptime, caller-id, ...
                foreach ($rows as $x) {
                    $u = (string)($x['name'] ?? '');
                    if ($u !== '') {
                        $list->push((object) [
                            'username'        => $u,
                            'nasipaddress'    => $m->host,
                            'framedipaddress' => $x['address'] ?? null,
                            'acctstarttime'   => null,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // lewati device yang gagal diambil
                Log::notice('[DASH] pppActive fail for device '.$m->id.': '.$e->getMessage());
            }
        }
        return $list;
    }

    /**
     * Cek RADIUS via Status-Server (radclient). Fallback: pesan error yang ramah.
     * Mengembalikan: ['ok'=>bool, 'msg'=>string, 'host'=>string]
     */
    private function radiusStatus(): array
    {
        $host    = env('RADIUS_HOST', '127.0.0.1');
        $port    = (int) env('RADIUS_AUTH_PORT', 1812);
        $secret  = (string) env('RADIUS_SECRET', '');
        $timeout = (int) env('RADIUS_STATUS_TIMEOUT', 3);

        try {
            // Kirim Status-Server (Message-Authenticator boleh 0x00)
            $cmd = sprintf(
                'printf "Message-Authenticator = 0x00\n" | timeout %d radclient -x %s:%d status %s 2>&1',
                max(1, $timeout),
                escapeshellarg($host),
                $port,
                escapeshellarg($secret)
            );

            $p = Process::fromShellCommandline($cmd);
            $p->setTimeout($timeout + 2);
            $p->run();

            $out = (string)$p->getOutput() . (string)$p->getErrorOutput();

            if ($this->strContains($out, 'Access-Accept')) {
                return ['ok' => true, 'msg' => 'OK', 'host' => $host.':'.$port];
            }

            // Beberapa pola error yang umum
            $msg = 'Unknown';
            if ($this->strContains($out, 'No reply from server')) $msg = 'No reply from server';
            elseif ($this->strContains($out, 'timeout'))           $msg = 'Timeout';
            elseif ($this->strContains($out, 'Access-Reject'))     $msg = 'Access-Reject';
            elseif (preg_match('/(refused|unreachable|denied)/i', $out)) $msg = trim($out);

            return ['ok' => false, 'msg' => $msg, 'host' => $host.':'.$port];
        } catch (\Throwable $e) {
            // Jika radclient tidak ada / gagal eksekusi
            return ['ok' => false, 'msg' => $e->getMessage(), 'host' => $host.':'.$port];
        }
    }

    // Polyfill kecil untuk PHP 7.4
    private function strContains($haystack, $needle)
    {
        if ($needle === '') return true;
        return strpos((string)$haystack, (string)$needle) !== false;
    }
}

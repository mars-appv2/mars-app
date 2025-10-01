<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\WaStaff;
use App\Models\Ticket;
use App\Models\Mikrotik;
use App\Services\RouterOSService;
use App\Services\WaService;
use App\Services\ProvisioningService;

class WaBotController extends Controller
{
    /** (Legacy) Header lokal — TIDAK dipakai lagi untuk menghindari header dobel.
     *  Header sekarang ditambahkan di App\Services\WaService (melalui WA_MSG_HEADER / WA_HEADER_ENABLE).
     */
    private function msgHeader(): string
    {
        $h = trim((string) env('WA_MSG_HEADER', 'PT MARS DATA TELEKOMUNIKASI'));
        return $h === '' ? '' : ('*'.$h."*\n");
    }

    /** Satu pintu kirim WA (HEADER ditangani WaService) */
    private function send(string $phone, string $text): void
    {
        $wa = new WaService();
        $wa->sendText(preg_replace('/\D/','', $phone), ltrim($text));
    }

    /** Kirim ke banyak nomor (HEADER ditangani WaService) */
    private function sendMany(array $phones, string $text): void
    {
        if (empty($phones)) return;
        $wa = new WaService();
        foreach ($phones as $p) {
            $num = preg_replace('/\D/', '', (string)$p); // hanya digit
            if (!$num) continue;
            $wa->sendText($num, ltrim($text));
            usleep(200 * 1000); // 0.2s
        }
    }

    private function getPhonesByRoles(array $roles): array
    {
        $want = collect($roles)->map(fn($r) => mb_strtolower($r, 'UTF-8'))->all();

        return WaStaff::where('active', 1)
            ->whereIn(DB::raw('LOWER(role)'), $want)   // roles boleh NOC/noc/Teknisi/TEKNISI
            ->pluck('phone')
            ->map(fn($p) => preg_replace('/\D/', '', (string)$p))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** === NEW: kirim ke roles tertentu (noc, teknisi, staff) === */
    private function notifyRoles(array $roles, string $msg): void
    {
        $this->sendMany($this->getPhonesByRoles($roles), $msg);
    }

    /** === NEW: kirim ke pembuat tiket (WaStaff.created_by) atau fallback customer_phone === */
    private function notifyTicketCreator(object $t, string $msg): void
    {
        // by creator staff id
        $phone = null;
        if (isset($t->created_by) && $t->created_by) {
            $phone = WaStaff::where('id', $t->created_by)->value('phone');
        }
        // fallback customer_phone (kalau diisi)
        if (!$phone && !empty($t->customer_phone)) {
            $phone = $t->customer_phone;
        }
        if ($phone) {
            $this->send(preg_replace('/\D/','',(string)$phone), $msg);
        }
    }

    /** === NEW: broadcast notifikasi tiket CLOSED ke NOC/teknisi/staff + creator === */
    private function broadcastTicketClosed(object $t, string $note, string $byName): void
    {
        $emoji = '✅';
        $title = strtoupper($t->type ?? 'TIKET');
        $msg =
            "{$emoji} [TIKET {$t->code}] {$title} CLOSED oleh {$byName}\n" .
            "Catatan: " . ($note !== '' ? $note : '—');

        // kirim ke NOC + teknisi + staff
        $this->notifyRoles(['noc','teknisi','staff'], $msg);
        // kirim ke pembuat tiket
        $this->notifyTicketCreator($t, $msg);
    }

    // ========================  WEBHOOK  ========================
    public function webhook(Request $r)
    {
        try {
            $fromJid = (string)($r->input('from') ?? '');
            $text    = trim((string)($r->input('text') ?? ''));

            Log::info('[WA-WEBHOOK] incoming', [
                'ip'   => $r->ip(),
                'ua'   => $r->userAgent(),
                'from' => $fromJid,
                'text' => mb_substr($text, 0, 2000),
            ]);

            if ($text === '' || $fromJid === '') {
                return response()->json(['ok'=>true]);
            }

            // normalisasi msisdn dari JID
            $msisdn = preg_replace('/@s\.whatsapp\.net$/', '', $fromJid);
            $msisdn = preg_replace('/\D/', '', $msisdn ?: '');

            // hanya nomor terdaftar/aktif yang boleh pakai command
            $staff = WaStaff::where('phone', $msisdn)->where('active', 1)->first();

            if (!$staff) {
                $this->send($msisdn, "Nomor ini belum terdaftar. Hubungi admin untuk akses bot.");
                return response()->json(['ok'=>true]);
            }

            // routing command
            $reply = $this->handleCommand($staff, $text);
            if ($reply) {
                $this->send($msisdn, $reply);
            }

            return response()->json(['ok'=>true]);

        } catch (\Throwable $e) {
            Log::error('[WA-WEBHOOK] exception: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['ok'=>true]);
        }
    }

    // ======================  COMMAND HANDLER  ======================
    private function handleCommand(WaStaff $staff, string $raw): string
    {
        $txt = trim($raw);
        $UP  = mb_strtoupper($txt, 'UTF-8');

        $starts = function (string $haystack, string $needle): bool {
            return mb_strpos($haystack, $needle, 0, 'UTF-8') === 0;
        };

        // HELP / MENU
        if ($UP === 'HELP' || $UP === 'MENU' || $UP === '?') {
            return $this->helpText();
        }

        // ======= AKTIF (gabungan radacct + /ppp/active) =======
        if ($UP === 'AKTIF' || $UP === 'AKTIFDBG') {
            try {
                $window = (int) env('RADIUS_ONLINE_WINDOW_MINUTES', 10);
                if ($window <= 0) $window = 10;

                $devices = Mikrotik::orderBy('name')->get(['id','name','host','username','password','port']);
                $hosts   = $devices->pluck('host')->filter()->values()->all();

                [$rowsBase, $rowsInterim] = $this->fetchActiveFromRadacct($hosts, $window);
                $rowsPpp = $this->fetchActiveFromRouters($devices);

                $all  = collect($rowsBase)->concat($rowsInterim)->concat($rowsPpp);
                $uniq = $all->unique(function ($x) {
                    $u  = is_object($x) ? ($x->username ?? '')        : ($x['username'] ?? '');
                    $ip = is_object($x) ? ($x->framedipaddress ?? '') : ($x['framedipaddress'] ?? '');
                    return $u.'|'.$ip;
                });
                $total = $uniq->count();

                if ($UP === 'AKTIFDBG') {
                    $cBase  = $this->countDistinctByUserIp($rowsBase);
                    $cInter = $this->countDistinctByUserIp($rowsInterim);
                    $cPpp   = $this->countDistinctByUserIp($rowsPpp);
                    return "Diagnostik AKTIF:\n- radacct (stoptime null/zero) = {$cBase}\n- radacct (acctupdatetime >= {$window}m UTC) = {$cInter}\n- ppp/active (router) = {$cPpp}\nDipakai (dedup username|IP) = {$total}";
                }

                return "User online saat ini: *{$total}*.";
            } catch (\Throwable $e) {
                return "Gagal cek AKTIF: {$e->getMessage()}";
            }
        }

        // ======= PSBOK <KODE> (teknisi konfirmasi; sistem verifikasi online dulu) =======
        if ($starts($UP, 'PSBOK ')) {
            $code = trim(mb_substr($txt, 6, null, 'UTF-8'));
            if ($code === '') return "Format salah. Contoh: PSBOK TCK20250907-0001";

            $t = DB::table('tickets')->where('code',$code)->first();
            if (!$t) return "Tiket *{$code}* tidak ditemukan.";
            if ($t->type !== 'psb') return "Tiket *{$code}* bukan PSB.";
            if ($t->status === 'closed') return "Tiket *{$code}* sudah CLOSED.";

            $username = (string)($t->username ?? '');
            if ($username === '') return "Tiket *{$code}* tidak memiliki username. Tutup manual di web.";

            $isOn = $this->isUserOnline($username);
            if (!$isOn) {
                return "User *{$username}* belum terbaca aktif. Pastikan ONU/PPPoE sudah connect. Coba lagi beberapa detik: PSBOK {$code}.";
            }

            $note = "Konfirmasi teknisi: user {$username} sudah aktif (PSBOK).";
            $this->closeTicketById($t->id, $note);
            // === NEW: broadcast close ke NOC/teknisi/staff + creator
            $this->broadcastTicketClosed($t, $note, $staff->name);

            return "Tiket *{$t->code}* ditutup. User *{$username}* terdeteksi AKTIF.";
        }

        // ======= TUTUP/CLOSE <KODE>|[CATATAN] =======
        if ($starts($UP, 'TUTUP ') || $starts($UP, 'CLOSE ')) {
            $payload = trim(mb_substr($txt, 6, null, 'UTF-8'));
            if ($payload === '') {
                return "Format salah.\nContoh: TUTUP TCK20250907-0001|Sudah normal kembali.";
            }
            $parts = array_map('trim', explode('|', $payload));
            $code  = $parts[0];
            $note  = $parts[1] ?? ('Ditutup via WA oleh '.$staff->name);

            $t = DB::table('tickets')->where('code', $code)->first();
            if (!$t) return "Tiket dengan kode *{$code}* tidak ditemukan.";
            if ($t->status === 'closed') return "Tiket *{$code}* sudah CLOSED sebelumnya.";

            $this->closeTicketById($t->id, $note, $staff->id);
            // === NEW: broadcast close ke NOC/teknisi/staff + creator
            $this->broadcastTicketClosed($t, $note, $staff->name);

            return "Tiket *{$t->code}* berhasil ditutup.";
        }

        // ======= NONAKTIF =======
        if ($UP === 'NONAKTIF' || $UP === 'TIDAKAKTIF') {
            try {
                $totalUsers = DB::connection('radius')->table('radcheck')
                    ->where('attribute','Cleartext-Password')
                    ->distinct('username')->count('username');

                $devices = Mikrotik::orderBy('name')->get(['id','name','host','username','password','port']);
                $hosts   = $devices->pluck('host')->filter()->values()->all();
                $window  = (int) env('RADIUS_ONLINE_WINDOW_MINUTES', 10);
                if ($window <= 0) $window = 10;

                [$rowsBase, $rowsInterim] = $this->fetchActiveFromRadacct($hosts, $window);
                $rowsPpp = $this->fetchActiveFromRouters($devices);

                $all  = collect($rowsBase)->concat($rowsInterim)->concat($rowsPpp);
                $uniq = $all->unique(function ($x) {
                    $u  = is_object($x) ? ($x->username ?? '')        : ($x['username'] ?? '');
                    $ip = is_object($x) ? ($x->framedipaddress ?? '') : ($x['framedipaddress'] ?? '');
                    return $u.'|'.$ip;
                });
                $online = $uniq->count();

                $non = max(0, $totalUsers - $online);
                return "User tidak aktif: *{$non}* (Total {$totalUsers}, Online {$online}).";
            } catch (\Throwable $e) {
                return "Gagal cek NONAKTIF: {$e->getMessage()}";
            }
        }

        // ======= CEK <username> =======
        if (preg_match('/^CEK\s+([^\s]+)$/i', $txt, $m)) {
            return $this->checkUser($m[1]);
        }

        // ======= ADDUSER <username> <password> [plan] =======
        if (preg_match('/^ADDUSER\s+(\S+)\s+(\S+)(?:\s+(\S+))?/i', $txt, $m)) {
            $username = $m[1];
            $pass     = $m[2];
            $plan     = $m[3] ?? null;

            // 1) RADIUS + subscriptions
            $msg = $this->addUserCore($username, $pass, $plan);

            // 2) Tiket PSB (OPEN) + notif awal (🚨)
            $code = $this->createPsbTicketAndNotify($username, $staff, $plan);

            // 3) Provision PPPoE ke MikroTik
            $prov = new ProvisioningService();
            $mk   = $prov->resolveMikrotikForUser($username, $plan);
            if (!$mk) {
                return $msg."\n\n⚠️ Tidak ada MikroTik target untuk provisioning. Set DEFAULT_MIKROTIK_ID di .env atau isi mikrotik_id di plan/subscription.\nTiket: *{$code}* (OPEN).";
            }
            $profile = $plan ?: env('DEFAULT_PPPOE_PROFILE', null);
            $res = $prov->provisionPppoe($mk, $username, $pass, $profile);

            if (!$res['ok']) {
                return $msg."\n\n❌ Provisioning PPPoE gagal di MikroTik {$mk->name} ({$mk->host}): {$res['msg']}\nTiket: *{$code}* (OPEN).";
            }

            // 4) Info teknisi: akun siap; tiket tetap OPEN sampai user aktif
            $this->notifyTeknisiOnly(
                "[PSB {$code}] Akun PPPoE siap dipasang\n".
                "User: {$username}\nPass: {$pass}\nPlan/Profile: ".($profile ?: '—')."\nRouter: {$mk->name} ({$mk->host})\n".
                "Setelah connect & internet OK, kirim: PSBOK {$code}"
            );

            return $msg."\n\n✅ PPPoE dibuat/diupdate di MikroTik {$mk->name} ({$mk->host}).\nTiket PSB *{$code}* **tetap OPEN** sampai user benar-benar AKTIF.\nTeknisi kirim: *PSBOK {$code}* jika sudah connect.";
        }

        // ======= PSB manual & KOMPLAIN =======
        if ($starts($UP, 'PSB ')) {
            return $this->openTicket('psb', trim(mb_substr($txt, 4, null, 'UTF-8')), $staff);
        }

        // === KOMPLAIN <pesan> (format cepat) ===
        if ($starts($UP, 'KOMPLAIN ')) {
            $pesan = trim(mb_substr($txt, 8, null, 'UTF-8'));
            if ($pesan === '') return "Format salah. Contoh: KOMPLAIN Internet lambat di Perum X";
            $code = $this->createComplainTicketAndNotify($staff, $pesan);
            return "🚨 Tiket komplain *{$code}* dibuat.\nPesan: {$pesan}";
        }
        if ($UP === 'KOMPLAIN') {
            return "Format salah. Contoh: KOMPLAIN Internet lambat di Perum X";
        }

        return "Perintah tidak dikenali.\n\n".$this->helpText();
    }
    
    private function helpText(): string
    {
        return
"Daftar perintah:
• HELP — lihat bantuan
• AKTIF — jumlah user online (radacct + router)
• NONAKTIF — jumlah user tidak aktif
• CEK <username> — status user & uptime
• ADDUSER <username> <password> [plan] — tambah user & provisioning PPPoE (tiket PSB tetap OPEN)
• PSBOK <KODE> — tutup tiket PSB jika user sudah AKTIF
• PSB username|Nama|HP|Alamat|Catatan — tiket pasang baru (manual)
• KOMPLAIN <pesan> — tiket komplain cepat
• TUTUP <KODE>|[catatan] — tutup tiket (manual)
• AKTIFDBG — diagnostik hitung online";
    }

    // ======================  ONLINE CHECK (dashboard parity)  ======================
    private function isUserOnline(string $username): bool
    {
        $window = (int) env('RADIUS_ONLINE_WINDOW_MINUTES', 10);
        if ($window <= 0) $window = 10;

        $devices = Mikrotik::orderBy('name')->get(['id','name','host','username','password','port']);
        $hosts   = $devices->pluck('host')->filter()->values()->all();

        $tbl = env('RADIUS_RADACCT_TABLE', 'radacct');

        // base: stoptime null/zero
        try {
            $base = DB::connection('radius')->table($tbl)
                ->when(!empty($hosts), fn($q) => $q->whereIn('nasipaddress', $hosts))
                ->where('username', $username)
                ->where(function ($q) {
                    $q->whereNull('acctstoptime')
                      ->orWhere('acctstoptime','')
                      ->orWhere('acctstoptime','0000-00-00 00:00:00');
                })
                ->limit(1)->exists();
            if ($base) return true;
        } catch (\Throwable $e) {}

        // interim-update window (UTC)
        try {
            $dbName = config('database.connections.radius.database');
            $hasCol = DB::connection('radius')->select("
                SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'acctupdatetime'
                LIMIT 1
            ", [$dbName, $tbl]);
            if ($hasCol) {
                $interim = DB::connection('radius')->table($tbl)
                    ->when(!empty($hosts), fn($q) => $q->whereIn('nasipaddress', $hosts))
                    ->where('username', $username)
                    ->whereRaw("acctupdatetime >= (UTC_TIMESTAMP() - INTERVAL ? MINUTE)", [$window])
                    ->limit(1)->exists();
                if ($interim) return true;
            }
        } catch (\Throwable $e) {}

        // router live ppp/active
        foreach ($devices as $m) {
            try {
                $svc  = new RouterOSService($m);
                $rows = $svc->pppActive();
                foreach ($rows as $x) {
                    $u = (string)($x['name'] ?? '');
                    if ($u !== '' && strcasecmp($u, $username) === 0) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {}
        }

        return false;
    }

    // ======================  AKTIF HELPERS  ======================
    private function fetchActiveFromRadacct(array $hosts, int $windowMinutes): array
    {
        $tbl = env('RADIUS_RADACCT_TABLE', 'radacct');
        $rowsBase = collect();
        $rowsInterim = collect();

        try {
            $rowsBase = DB::connection('radius')->table($tbl)
                ->select(['username','nasipaddress','framedipaddress','acctstarttime'])
                ->when(!empty($hosts), fn($q) => $q->whereIn('nasipaddress', $hosts))
                ->where(function ($q) {
                    $q->whereNull('acctstoptime')->orWhere('acctstoptime','')->orWhere('acctstoptime','0000-00-00 00:00:00');
                })
                ->orderByDesc('acctstarttime')
                ->limit(20000)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('[WA] radacct base query fail: '.$e->getMessage());
        }

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
                    ->when(!empty($hosts), fn($q) => $q->whereIn('nasipaddress', $hosts))
                    ->whereNotNull('username')
                    ->where('username','!=','')
                    ->whereRaw("acctupdatetime >= (UTC_TIMESTAMP() - INTERVAL ? MINUTE)", [$windowMinutes])
                    ->orderByDesc('acctstarttime')
                    ->limit(20000)
                    ->get();
            }
        } catch (\Throwable $e) {
            Log::notice('[WA] radacct interim query skip/fail: '.$e->getMessage());
        }

        $rowsBase    = collect($rowsBase)->unique(fn($x) => ($x->username ?? '').'|'.($x->framedipaddress ?? ''))->values();
        $rowsInterim = collect($rowsInterim)->unique(fn($x) => ($x->username ?? '').'|'.($x->framedipaddress ?? ''))->values();
        return [$rowsBase, $rowsInterim];
    }

    private function fetchActiveFromRouters($devices)
    {
        $list = collect();
        foreach ($devices as $m) {
            try {
                $svc  = new RouterOSService($m);
                $rows = $svc->pppActive();
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
                Log::notice('[WA] pppActive fail for device '.$m->id.': '.$e->getMessage());
            }
        }
        return $list->unique(fn($x) => ($x->username ?? '').'|'.($x->framedipaddress ?? ''))->values();
    }

    private function countDistinctByUserIp($rows): int
    {
        return (int) collect($rows)->unique(function ($x) {
            $u  = is_object($x) ? ($x->username ?? '')        : ($x['username'] ?? '');
            $ip = is_object($x) ? ($x->framedipaddress ?? '') : ($x['framedipaddress'] ?? '');
            return $u.'|'.$ip;
        })->count();
    }

    // ======================  ADDUSER CORE ======================
    private function addUserCore(string $username, string $pass, ?string $plan): string
    {
        DB::beginTransaction();
        try {
            // radcheck
            $exists = DB::connection('radius')->table('radcheck')
                ->where('username',$username)
                ->where('attribute','Cleartext-Password')
                ->first();

            if ($exists) {
                DB::connection('radius')->table('radcheck')
                    ->where('id',$exists->id)
                    ->update(['value'=>$pass,'op'=>':=']);
            } else {
                DB::connection('radius')->table('radcheck')->insert([
                    'username'=>$username,'attribute'=>'Cleartext-Password','op'=>':=','value'=>$pass
                ]);
            }

            // radusergroup (plan opsional)
            if ($plan) {
                $has = DB::connection('radius')->table('radusergroup')
                    ->where('username',$username)->exists();
                if ($has) {
                    DB::connection('radius')->table('radusergroup')
                        ->where('username',$username)->update(['groupname'=>$plan,'priority'=>0]);
                } else {
                    DB::connection('radius')->table('radusergroup')->insert([
                        'username'=>$username,'groupname'=>$plan,'priority'=>0
                    ]);
                }
            }

            // Subscriptions (Laravel)
            $planId = null;
            if ($plan) $planId = DB::table('plans')->where('name',$plan)->value('id');

            $dataSubs = [
                'plan_id'    => $planId,
                'status'     => 'active',
                'updated_at' => now(),
            ];
            if (!DB::table('subscriptions')->where('username',$username)->exists()) {
                $dataSubs['created_at'] = now();
            }
            if (Schema::hasColumn('subscriptions','mikrotik_id')) {
                $def = (int) env('DEFAULT_MIKROTIK_ID', 0);
                if ($def > 0) $dataSubs['mikrotik_id'] = $def;
            }

            DB::table('subscriptions')->updateOrInsert(
                ['username'=>$username],
                $dataSubs
            );

            DB::commit();
            $ts = now()->format('Y-m-d H:i:s');
            return "User berhasil dibuat:\nUsername: *{$username}*\nPassword: *{$pass}*\nPlan: ".($plan ?: '—')."\nWaktu: {$ts}";
        } catch (\Throwable $e) {
            DB::rollBack();
            return "Gagal ADDUSER: {$e->getMessage()}";
        }
    }

    /** ====== Tiket PSB auto & notif awal (pakai 🚨) ====== */
    private function createPsbTicketAndNotify(string $username, WaStaff $creator, ?string $plan): string
    {
        $code = $this->nextTicketCode();
        DB::table('tickets')->insert([
            'code'          => $code,
            'type'          => 'psb',
            'username'      => $username,
            'customer_name' => null,
            'customer_phone'=> null,
            'address'       => null,
            'description'   => 'PSB auto dari ADDUSER via WA (plan: '.($plan ?: '—').')',
            'status'        => 'open',
            'created_by'    => $creator->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->notifyRoles(['noc','teknisi','staff'],
            "🚨 [TIKET {$code}] PSB (auto)\n".
            "User: {$username}\n".
            "Plan: ".($plan ?: '—')."\n".
            "Status: provisioning…"
        );
        return $code;
    }

    /** ====== Komplain cepat + notif 🚨 ====== */
    private function createComplainTicketAndNotify(WaStaff $creator, string $pesan): string
    {
        $code = $this->nextTicketCode();

        DB::table('tickets')->insert([
            'code'           => $code,
            'type'           => 'complain',
            'username'       => null,
            'customer_name'  => $creator->name ?? null,
            'customer_phone' => $creator->phone ?? null,
            'address'        => null,
            'description'    => $pesan,
            'status'         => 'open',
            'created_by'     => $creator->id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $nama  = $creator->name ?: 'Staff';
        $phone = preg_replace('/\D/', '', (string)($creator->phone ?? ''));
        $this->notifyRoles(['noc','teknisi','staff'],
            "🚨 [TIKET {$code}] KOMPLAIN\n".
            "Dari: {$nama}".($phone ? " ({$phone})" : "")."\n".
            "Isi: {$pesan}"
        );

        return $code;
    }

    private function closeTicketById(int $ticketId, string $note = '', ?int $closedBy = null): void
    {
        $t = DB::table('tickets')->where('id',$ticketId)->first();
        if (!$t || $t->status === 'closed') return;

        $data = ['status'=>'closed','updated_at'=>now()];
        if (Schema::hasColumn('tickets','closed_at')) $data['closed_at'] = now();
        if (Schema::hasColumn('tickets','closed_by')) $data['closed_by'] = $closedBy ?? 0;
        if ($note !== '') {
            if (Schema::hasColumn('tickets','resolution')) {
                $data['resolution'] = $note;
            } else {
                $data['description'] = trim((string)$t->description."\n[Closed] ".$note);
            }
        }
        DB::table('tickets')->where('id',$ticketId)->update($data);
    }
  
    private function notifyNocTeknisi(string $msg): void
    {
        $phones = \App\Models\WaStaff::where('active', 1)
            ->where(function ($q) {
                $q->whereRaw('LOWER(role) = ?', ['noc'])
                  ->orWhereRaw('LOWER(role) = ?', ['teknisi']);
            })
            ->pluck('phone')->all();

        \Log::info('[WA] notifyNocTeknisi', ['count' => count($phones)]);
        $this->sendMany($phones, $msg);
    }

    private function notifyTeknisiOnly(string $msg): void
    {
        $phones = \App\Models\WaStaff::where('active', 1)
            ->whereRaw('LOWER(role) = ?', ['teknisi'])
            ->pluck('phone')->all();

        \Log::info('[WA] notifyTeknisiOnly', ['count' => count($phones)]);
        $this->sendMany($phones, $msg);
    }

    private function nextTicketCode(): string
    {
        $prefix = 'TCK' . date('Ymd') . '-';
        $last = DB::table('tickets')->where('code','like',$prefix.'%')
            ->orderByDesc('id')->value('code');

        $seq = 1;
        if ($last && preg_match('/-(\d{4})$/', $last, $m)) {
            $seq = ((int)$m[1]) + 1;
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    /** PSB manual & complain generic (opsional) */
    private function openTicket(string $type, string $payload, WaStaff $creator): string
    {
        $type = strtolower($type);

        if ($type === 'psb') {
            $parts = array_map('trim', explode('|', $payload));
            $username = $parts[0] ?? '';
            $nama     = $parts[1] ?? null;
            $hp       = $parts[2] ?? null;
            $alamat   = $parts[3] ?? null;
            $catatan  = $parts[4] ?? null;

            if ($username === '') {
                return "Format salah. Contoh:\nPSB username|Nama|HP|Alamat|Catatan";
            }

            $code = $this->nextTicketCode();
            DB::table('tickets')->insert([
                'code'           => $code,
                'type'           => 'psb',
                'username'       => $username,
                'customer_name'  => $nama,
                'customer_phone' => $hp,
                'address'        => $alamat,
                'description'    => $catatan,
                'status'         => 'open',
                'created_by'     => $creator->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $this->notifyRoles(['noc','teknisi','staff'],
                "🚨 [TIKET {$code}] PSB (manual)\n".
                "User: {$username}\n".
                "Nama: ".($nama ?: '—')."\n".
                "HP: ".($hp ?: '—')."\n".
                "Alamat: ".($alamat ?: '—')."\n".
                "Catatan: ".($catatan ?: '—')
            );
            return "Tiket PSB *{$code}* dibuat untuk user *{$username}*.";
        }

        if ($type === 'complain') {
            // payload bebas; jika format username|keluhan, rapikan jadi satu kalimat
            $parts = array_map('trim', explode('|', $payload, 2));
            $pesan = $payload;
            if (count($parts) === 2) {
                $pesan = "User: {$parts[0]} — Keluhan: {$parts[1]}";
            }
            $code = $this->createComplainTicketAndNotify($creator, $pesan);
            return "🚨 Tiket komplain *{$code}* dibuat.\nPesan: {$pesan}";
        }

        return "Jenis tiket tidak dikenali.";
    }

    private function checkUser(string $username): string
    {
        try {
            $grp = DB::connection('radius')->table('radusergroup')
                ->where('username',$username)->value('groupname');

            $tbl = env('RADIUS_RADACCT_TABLE', 'radacct');
            $acct = DB::connection('radius')->table($tbl)
                ->where('username',$username)
                ->orderByDesc('acctstarttime')
                ->first();

            if ($acct && ($acct->acctstoptime === null || $acct->acctstoptime === '0000-00-00 00:00:00' || $acct->acctstoptime === '')) {
                $start = strtotime($acct->acctstarttime ?: 'now');
                $upt   = max(0, time() - $start);
                $uptS  = $this->fmtDur($upt);
                $nas   = $acct->nasipaddress ?? '-';
                $ip    = $acct->framedipaddress ?? '-';
                return "*{$username}*: ONLINE\nUptime: {$uptS}\nNAS: {$nas}\nIP: {$ip}\nPlan: ".($grp ?: '—');
            } else {
                $last = $acct ? ($acct->acctstoptime ?: $acct->acctstarttime) : null;
                return "*{$username}*: OFFLINE\nPlan: ".($grp ?: '—')."\nLast seen: ".($last ?: '—');
            }
        } catch (\Throwable $e) {
            return "Gagal CEK user: {$e->getMessage()}";
        }
    }

    private function fmtDur(int $sec): string
    {
        $h = floor($sec / 3600);
        $m = floor(($sec % 3600) / 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

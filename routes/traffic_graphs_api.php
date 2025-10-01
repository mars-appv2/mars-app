<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// Semua endpoint ini butuh login
Route::middleware(['auth'])->prefix('traffic/api')->group(function () {

    // === Dropdown PPPoE user (per Mikrotik) ===
    Route::get('/mikrotik/{id}/pppoe-users', function ($id) {
        $m = DB::table('mikrotiks')->where('id', (int) $id)->first();
        if (!$m) abort(404);

        try {
            $c = new \RouterOS\Client([
                'host'     => $m->host,
                'user'     => $m->username,
                'pass'     => $m->password,
                'port'     => $m->port ?: 8728,
                'timeout'  => 5,
                'attempts' => 1,
            ]);

            $q    = new \RouterOS\Query('/ppp/active/print');
            $rows = $c->query($q)->read();

            $users = [];
            foreach ($rows as $r) {
                if (!empty($r['name'])) $users[] = $r['name'];
            }
            $users = array_values(array_unique($users));
            sort($users, SORT_NATURAL | SORT_FLAG_CASE);

            return response()->json($users);
        } catch (\Throwable $e) {
            \Log::error('traffic/api pppoe-users: ' . $e->getMessage());
            return response()->json([], 500);
        }
    })->name('traffic.api.pppoe_users');

    // === Dropdown Simple Queue (per Mikrotik) untuk IP Public ===
    Route::get('/mikrotik/{id}/queues', function ($id) {
        $m = DB::table('mikrotiks')->where('id', (int) $id)->first();
        if (!$m) abort(404);

        try {
            $c = new \RouterOS\Client([
                'host'     => $m->host,
                'user'     => $m->username,
                'pass'     => $m->password,
                'port'     => $m->port ?: 8728,
                'timeout'  => 5,
                'attempts' => 1,
            ]);

            $q    = new \RouterOS\Query('/queue/simple/print');
            $rows = $c->query($q)->read();

            $out = [];
            foreach ($rows as $r) {
                $target = isset($r['target']) ? $r['target'] : '';
                $name   = isset($r['name']) ? $r['name'] : $target;

                if ($target) {
                    // ambil IP pertama dari target
                    $first = preg_split('/[, ]+/', $target)[0];
                    $ip    = preg_replace('/\/\d+$/', '', $first);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $out[] = ['ip' => $ip, 'name' => $name, 'target' => $target];
                    }
                }
            }
            return response()->json($out);
        } catch (\Throwable $e) {
            \Log::error('traffic/api queues: ' . $e->getMessage());
            return response()->json([], 500);
        }
    })->name('traffic.api.queues');

    // === Hapus Content Target (dipakai tombol Hapus di Content Apps) ===
    Route::delete('/content/targets/{id}', function ($id) {
        DB::table('traffic_content_map')->where('id', (int) $id)->delete();
        return back()->with('ok', 'Content target dihapus.');
    })->name('traffic.content.targets.destroy');

});

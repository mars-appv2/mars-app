<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user   = Auth::user();
        $status = 'Unknown';
        $plan   = '-';
        $quota  = '-';

        // ===== Status koneksi dari radacct (acctstoptime NULL = online)
        if (Schema::hasTable('radacct')) {
            $username = $user->username ?? $user->email ?? null;
            if ($username) {
                try {
                    $active = DB::table('radacct')
                        ->where('username', $username)
                        ->whereNull('acctstoptime')
                        ->exists();
                    $status = $active ? 'Online' : 'Offline';
                } catch (\Throwable $e) { /* keep default */ }
            }
        }

        // ===== Ambil paket aktif dari subscriptions (schema-agnostic)
        if (Schema::hasTable('subscriptions')) {
            try {
                $subsCols = Schema::getColumnListing('subscriptions');

                // Tentukan cara "mengaitkan" subscription dengan user saat ini
                $subQuery = DB::table('subscriptions');

                // 1) lewat ID user (berbagai nama kolom)
                $idCols = ['user_id','customer_id','account_id','userid','userId'];
                $matched = false;
                foreach ($idCols as $col) {
                    if (in_array($col, $subsCols)) {
                        $subQuery->where($col, $user->id);
                        $matched = true;
                        break;
                    }
                }

                // 2) fallback lewat identity (username/email) bila kolomnya ada
                if (!$matched) {
                    $identity = $user->username ?? null;
                    if ($identity && in_array('username', $subsCols)) {
                        $subQuery->where('username', $identity);
                        $matched = true;
                    } elseif (in_array('email', $subsCols)) {
                        $subQuery->where('email', $user->email);
                        $matched = true;
                    }
                }

                // Urutan paling masuk akal
                if (in_array('updated_at', $subsCols)) {
                    $subQuery->orderByDesc('updated_at');
                } elseif (in_array('created_at', $subsCols)) {
                    $subQuery->orderByDesc('created_at');
                } elseif (in_array('id', $subsCols)) {
                    $subQuery->orderByDesc('id');
                }

                $sub = $subQuery->first();

                if ($sub) {
                    // Bangun label plan senyaman mungkin
                    $planLabel = null;

                    // Jika ada plan_id / package_id dan ada tabel plans, coba join
                    if (Schema::hasTable('plans')) {
                        $planIdCol = null;
                        foreach (['plan_id','package_id','plan'] as $c) {
                            if (in_array($c, $subsCols)) { $planIdCol = $c; break; }
                        }
                        if ($planIdCol && is_numeric($sub->{$planIdCol})) {
                            $planRow = DB::table('plans')->where('id', $sub->{$planIdCol})->first();
                            if ($planRow) {
                                $nm = $planRow->name ?? 'Plan';
                                $spd = $planRow->speed ?? ($planRow->price_month ?? $planRow->price ?? '');
                                $planLabel = trim($nm.' '.(is_string($spd)||is_numeric($spd)?'— '.$spd:''));
                            }
                        }
                    }

                    // Jika belum ketemu, gunakan kolom string yang ada di subscriptions
                    if (!$planLabel) {
                        foreach (['plan_name','plan','package_name','package','product','name'] as $c) {
                            if (in_array($c, $subsCols) && !empty($sub->{$c})) {
                                $planLabel = $sub->{$c};
                                break;
                            }
                        }
                    }

                    // Tambahkan speed/price jika tersedia
                    foreach (['speed','bandwidth','price_month','price'] as $c) {
                        if (in_array($c, $subsCols) && !empty($sub->{$c})) {
                            $val = $sub->{$c};
                            $planLabel = trim(($planLabel ?: 'Plan').' — '.$val);
                            break;
                        }
                    }

                    // Fallback terakhir
                    $plan = $planLabel ?: ('Subscription #'.($sub->id ?? ''));
                }
            } catch (\Throwable $e) {
                // keep defaults
            }
        }

        // ===== Estimasi kuota bulan berjalan dari radacct
        if (Schema::hasTable('radacct')) {
            $username = $user->username ?? $user->email ?? null;
            if ($username) {
                try {
                    $start = now()->startOfMonth();
                    $end   = now()->endOfMonth();
                    $rows = DB::table('radacct')
                        ->select('acctinputoctets','acctoutputoctets','acctstarttime')
                        ->where('username', $username)
                        ->whereBetween('acctstarttime', [$start, $end])
                        ->get();

                    $total = 0;
                    foreach ($rows as $r) {
                        $total += (int)$r->acctinputoctets + (int)$r->acctoutputoctets;
                    }
                    $gb = $total / 1024 / 1024 / 1024;
                    $quota = number_format($gb, 2, ',', '.') . ' GB';
                } catch (\Throwable $e) { /* keep default */ }
            }
        }

        return view('client.dashboard', compact('status','plan','quota','user'));
    }
}

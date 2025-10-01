<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;

class WifiController extends Controller
{
    public function form()
    {
        return view('client.wifi');
    }

    public function update(Request $request)
    {
        $request->validate([
            'ssid'     => 'required|string|max:32',
            'password' => 'required|string|min:8|max:64',
        ]);

        // Simpan request ke tabel jika ada; jika belum ada, buatkan migrasinya (di bawah)
        if (Schema::hasTable('client_wifi_requests')) {
            DB::table('client_wifi_requests')->insert([
                'user_id'  => Auth::id(),
                'ssid'     => $request->ssid,
                'password' => Crypt::encryptString($request->password),
                'status'   => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // TODO: kalau kamu sudah ada service Mikrotik, panggil di sini.
        // Contoh (pseudo):
        // app('mikrotik')->forUser(Auth::user())->setSsid($request->ssid, $request->password);

        return back()->with('ok', 'SSID berhasil diajukan. Perubahan akan diterapkan.');
    }
}

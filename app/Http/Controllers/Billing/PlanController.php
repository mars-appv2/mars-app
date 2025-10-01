<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\Mikrotik;
use App\Services\RouterOSService;

class PlanController extends Controller
{
    public function index(Request $r)
    {
        $devices = Mikrotik::forUser(auth()->user())
            ->orderBy('name')
            ->get(['id','name','host']);

        $mikrotikId = $r->query('mikrotik_id');
        $q          = trim((string)$r->query('q',''));

        $plans = DB::table('plans')
            ->when($mikrotikId, fn($qq)=>$qq->where('mikrotik_id', $mikrotikId))
            ->when($q !== '',   fn($qq)=>$qq->where('name','like',"%{$q}%"))
            ->orderBy('name')
            ->get();

        return view('billing.plans', compact('devices','plans'));
    }

    public function store(Request $r)
    {
        $r->validate([
            'name'        => 'required|string|max:255',
            'mikrotik_id' => 'nullable|integer',
            'price'       => 'nullable|numeric|min:0',
        ]);

        DB::table('plans')->insert([
            'name'        => $r->name,
            'mikrotik_id' => $r->mikrotik_id ?: null,
            'price'       => (float)($r->price ?? 0),
            'price_month' => (float)($r->price ?? 0),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('ok','Plan ditambahkan');
    }

    public function update(Request $r, $id)
    {
        $r->validate([
            'name'        => 'required|string|max:255',
            'mikrotik_id' => 'nullable|integer',
            'price'       => 'nullable|numeric|min:0',
        ]);

        DB::table('plans')->where('id',$id)->update([
            'name'        => $r->name,
            'mikrotik_id' => $r->mikrotik_id ?: null,
            'price'       => (float)($r->price ?? 0),
            'price_month' => (float)($r->price ?? 0),
            'updated_at'  => now(),
        ]);

        return back()->with('ok','Plan disimpan');
    }

    public function delete($id)
    {
        DB::table('plans')->where('id',$id)->delete();
        return back()->with('ok','Plan dihapus');
    }

    /**
     * Import PPP profiles dari MikroTik -> table plans
     * - Ambil fields: name, rate-limit (jika ada)
     * - Harga diambil dari comment bila ada "price=xxx; price_month=yyy" jika tidak ada set 0
     */
    public function import(Request $r)
    {
        $r->validate(['mikrotik_id'=>'required|integer']);
        $m = Mikrotik::forUser(auth()->user())->findOrFail((int)$r->mikrotik_id);

        $svc = new RouterOSService($m);
        $profiles = $svc->pppProfiles(); // array dari router

        $new = 0; $upd = 0; $warn = [];

        foreach ($profiles as $p) {
            $name = $p['name'] ?? null;
            if (!$name) continue;

            // default harga 0, bisa dibaca dari comment "price=150000; price_month=150000"
            $price = 0.0; $priceMonth = 0.0;
            if (!empty($p['comment'])) {
                parse_str(str_replace([';', ' '], ['&', ''], $p['comment']), $kv);
                if (isset($kv['price']) && is_numeric($kv['price'])) {
                    $price = (float)$kv['price'];
                }
                if (isset($kv['price_month']) && is_numeric($kv['price_month'])) {
                    $priceMonth = (float)$kv['price_month'];
                }
            }
            if ($priceMonth <= 0) $priceMonth = $price; // sinkron minimal

            $payload = [
                'name'        => $name,
                'mikrotik_id' => $m->id,
                'rate_limit'  => $p['rate-limit'] ?? null,
                'price'       => $price,
                'price_month' => $priceMonth,
                'updated_at'  => now(),
            ];

            $exists = DB::table('plans')
                ->where('name',$name)
                ->where('mikrotik_id',$m->id)
                ->first();

            if ($exists) {
                DB::table('plans')->where('id',$exists->id)->update($payload);
                $upd++;
            } else {
                $payload['created_at'] = now();
                DB::table('plans')->insert($payload);
                $new++;
            }
        }

        $msg = "Import plans selesai. New: {$new}, Updated: {$upd}";
        if ($warn) $msg .= " | Warning: ".implode(' | ', $warn);
        return back()->with('ok', $msg);
    }
}


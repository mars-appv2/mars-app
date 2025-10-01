<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class DevicesController extends Controller
{
    protected function rt(): ?string
    {
        if (Schema::hasTable('mikrotiks')) return 'mikrotiks';
        if (Schema::hasTable('mikrotik'))  return 'mikrotik';
        return null;
    }

    protected function allowedRoutersForUser(int $userId)
    {
        $rt = $this->rt();
        if (!$rt) return collect();

        $rows = collect();

        // a) milik sendiri
        $own = collect();
        $hasCreatedBy = Schema::hasColumn($rt,'created_by');
        $hasUserId    = Schema::hasColumn($rt,'user_id');
        if ($hasCreatedBy || $hasUserId) {
            $q = DB::table($rt)->orderBy('id','desc');
            if     ($hasCreatedBy && $hasUserId) $q->where(fn($w)=>$w->where('created_by',$userId)->orWhere('user_id',$userId));
            elseif ($hasCreatedBy)               $q->where('created_by',$userId);
            else                                  $q->where('user_id',$userId);
            $own = $q->get();
        }

        // b) assignment
        $assigned = collect();
        if (Schema::hasTable('staff_mikrotik')) {
            $assigned = DB::table($rt)
                ->join('staff_mikrotik',$rt.'.id','=','staff_mikrotik.mikrotik_id')
                ->where('staff_mikrotik.user_id',$userId)
                ->select($rt.'.*')
                ->orderBy($rt.'.id','desc')
                ->get();
        }

        return $own->concat($assigned)->unique('id')->values();
    }

    public function index()
    {
        $rt = $this->rt();
        $rows = $this->allowedRoutersForUser((int)Auth::id());
        return view('staff.devices.index', ['devices'=>$rows, 'rt'=>$rt]);
    }

    public function store(Request $r)
    {
        $rt = $this->rt();
        if (!$rt) return back()->with('err','Tabel perangkat tidak ditemukan.');

        $r->validate([
            'name'     => 'required|string|max:120',
            'host'     => 'required|string|max:190',
            'username' => 'required|string|max:120',
            'password' => 'required|string|max:190',
            'port'     => 'nullable|integer|min:1|max:65535'
        ]);

        $cols = Schema::getColumnListing($rt);
        $data = [
            'name'=>$r->name, 'host'=>$r->host, 'username'=>$r->username, 'password'=>$r->password,
        ];
        if (in_array('port',$cols,true) && $r->filled('port')) $data['port'] = (int)$r->port;
        if (in_array('created_by',$cols,true)) $data['created_by'] = Auth::id();
        if (in_array('user_id',$cols,true))    $data['user_id']    = Auth::id();
        if (in_array('created_at',$cols,true)) $data['created_at'] = now();
        if (in_array('updated_at',$cols,true)) $data['updated_at'] = now();

        $id = DB::table($rt)->insertGetId($data);

        // mapping assignment otomatis bila tabelnya ada
        if (Schema::hasTable('staff_mikrotik')) {
            $exists = DB::table('staff_mikrotik')
                ->where('user_id',Auth::id())->where('mikrotik_id',$id)->exists();
            if (!$exists) {
                DB::table('staff_mikrotik')->insert([
                    'user_id'=>Auth::id(),'mikrotik_id'=>$id,'created_at'=>now(),'updated_at'=>now()
                ]);
            }
        }

        return back()->with('ok','Perangkat ditambahkan.');
    }

    public function destroy($id)
    {
        $rt = $this->rt();
        if (!$rt) return back()->with('err','Tabel perangkat tidak ditemukan.');

        // hanya boleh hapus perangkat yang allowed
        $allowedIds = $this->allowedRoutersForUser((int)Auth::id())->pluck('id')->all();
        if (!in_array((int)$id, $allowedIds, true)) {
            return back()->with('err','Tidak berhak menghapus perangkat ini.');
        }

        DB::table($rt)->where('id',$id)->delete();
        if (Schema::hasTable('staff_mikrotik')) {
            DB::table('staff_mikrotik')->where('mikrotik_id',$id)->delete();
        }
        return back()->with('ok','Perangkat dihapus.');
    }

    public function show($id)
    {
        $rt = $this->rt();
        if (!$rt) abort(404);

        $allowedIds = $this->allowedRoutersForUser((int)Auth::id())->pluck('id')->all();
        if (!in_array((int)$id, $allowedIds, true)) abort(403,'Not allowed');

        $dev = DB::table($rt)->where('id',$id)->first();
        abort_unless($dev,404);

        return view('staff.devices.show', ['d'=>$dev]);
    }
}

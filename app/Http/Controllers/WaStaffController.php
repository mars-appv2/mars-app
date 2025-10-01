<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WaStaff;

class WaStaffController extends Controller
{
    public function __construct(){ $this->middleware('auth'); }

    public function index(Request $r)
    {
        $q = trim((string)$r->query('q',''));
        $rows = WaStaff::when($q !== '', fn($qq)=>$qq->where('name','like',"%{$q}%")->orWhere('phone','like',"%{$q}%"))
            ->orderBy('id','desc')->limit(500)->get();
        return view('wa.staff', compact('rows','q'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'=>'required|string',
            'phone'=>'required|string',
            'role'=>'required|in:noc,teknisi,staff',
            'active'=>'nullable|boolean'
        ]);
        $data['active'] = $r->boolean('active');
        WaStaff::updateOrCreate(['phone'=>$data['phone']], $data);
        return back()->with('ok','Staff tersimpan.');
    }

    public function delete($id)
    {
        WaStaff::where('id',$id)->delete();
        return back()->with('ok','Staff dihapus.');
    }
}

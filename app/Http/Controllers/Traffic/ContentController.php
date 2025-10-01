<?php
namespace App\Http\Controllers\Traffic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentController extends Controller
{
    public function index()
    {
        $rows = DB::table('traffic_content_map')->orderBy('name')->get();
        return view('traffic.content.targets', ['rows'=>$rows]);
    }

    public function store(Request $r)
    {
        $r->validate([
            'name' => 'required|string|max:64',
            'cidr' => 'required|string|max:64',
        ]);

        DB::table('traffic_content_map')->insert([
            'name' => $r->input('name'),
            'cidr' => $r->input('cidr'),
            'enabled' => $r->boolean('enabled', true) ? 1 : 0,
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        return back()->with('ok','Konten ditambahkan');
    }

    public function destroy($id)
    {
        DB::table('traffic_content_map')->where('id',$id)->delete();
        return back()->with('ok','Konten dihapus');
    }
}

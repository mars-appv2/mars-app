<?php
namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\MonitorGroup;
use App\Models\MonitorGroupItem;
use Illuminate\Http\Request;

class MonitorGroupController extends Controller
{
    // List groups (JSON)
    public function index(Mikrotik $mikrotik){
        $groups = MonitorGroup::where('mikrotik_id',$mikrotik->id)
            ->withCount('items')->orderBy('name')->get(['id','name','mikrotik_id','created_at']);
        return response()->json($groups);
    }

    // Detail group (JSON items)
    public function show(Mikrotik $mikrotik, MonitorGroup $group){
        abort_unless($group->mikrotik_id === $mikrotik->id, 404);
        return response()->json([
            'id'=>$group->id,
            'name'=>$group->name,
            'items'=>$group->items()->orderBy('iface')->pluck('iface')
        ]);
    }

    // Create/replace group
    public function store(Request $r, Mikrotik $mikrotik){
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'ifaces' => 'required|array|min:1',
            'ifaces.*' => 'string|max:100'
        ]);
        $group = MonitorGroup::updateOrCreate(
            ['mikrotik_id'=>$mikrotik->id, 'name'=>$d['name']],
            []
        );
        // replace items
        MonitorGroupItem::where('group_id',$group->id)->delete();
        $ins = [];
        foreach(array_unique($d['ifaces']) as $iface){
            $ins[] = ['group_id'=>$group->id, 'iface'=>$iface, 'created_at'=>now(), 'updated_at'=>now()];
        }
        if ($ins) MonitorGroupItem::insert($ins);
        return response()->json(['ok'=>true,'id'=>$group->id]);
    }

    public function destroy(Mikrotik $mikrotik, MonitorGroup $group){
        abort_unless($group->mikrotik_id === $mikrotik->id, 404);
        $group->items()->delete();
        $group->delete();
        return response()->json(['ok'=>true]);
    }
}

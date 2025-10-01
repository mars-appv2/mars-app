<?php
namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Radius\RadCheck;
use App\Models\Radius\RadUserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RadiusUserController extends Controller
{
    public function index(Request $r){
        $q = trim((string)$r->query('q',''));
        $base = RadCheck::where('attribute','Cleartext-Password');
        if ($q!=='') $base->where('username','like',"%$q%");
        $users = $base->orderBy('username')->paginate(20);

        $plans = Plan::orderBy('price_month')->get();
        $subs = Subscription::whereIn('username',$users->pluck('username'))
                 ->get()->keyBy('username');

        return view('radius.users.index', compact('users','plans','subs','q'));
    }

    public function store(Request $r){
        $d = $r->validate([
            'username'=>'required|string|max:64',
            'password'=>'required|string|max:128',
            'plan_id' =>'nullable|exists:plans,id'
        ]);

        DB::connection('radius_mysql')->transaction(function() use($d){
            RadCheck::updateOrCreate(
                ['username'=>$d['username'],'attribute'=>'Cleartext-Password'],
                ['op'=>':=','value'=>$d['password']]
            );
        });

        if (!empty($d['plan_id'])) {
            $plan = Plan::findOrFail($d['plan_id']);
            Subscription::updateOrCreate(
                ['username'=>$d['username']],
                ['plan_id'=>$plan->id,'status'=>'active','started_at'=>now()->toDateString()]
            );
            if ($plan->groupname) {
                RadUserGroup::updateOrCreate(
                    ['username'=>$d['username'],'groupname'=>$plan->groupname],
                    ['priority'=>1]
                );
            }
        }

        return back()->with('ok','User RADIUS tersimpan');
    }

    public function updatePassword(Request $r){
        $d=$r->validate([
            'username'=>'required|string|max:64',
            'password'=>'required|string|max:128',
        ]);
        RadCheck::updateOrCreate(
            ['username'=>$d['username'],'attribute'=>'Cleartext-Password'],
            ['op'=>':=','value'=>$d['password']]
        );
        return back()->with('ok','Password diupdate');
    }
}

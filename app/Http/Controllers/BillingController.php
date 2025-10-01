<?php
namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Radius\RadUserGroup;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    /* PLANS */
    public function plans(){
        $plans = Plan::orderBy('price_month')->get();
        return view('billing.plans.index', compact('plans'));
    }
    public function planStore(Request $r){
        $d=$r->validate([
            'name'=>'required','price_month'=>'required|integer|min:0',
            'ppp_profile'=>'nullable','rate'=>'nullable','groupname'=>'nullable'
        ]);
        Plan::create($d);
        return back()->with('ok','Paket dibuat');
    }
    public function planDelete(Plan $plan){
        $plan->delete();
        return back()->with('ok','Paket dihapus');
    }

    /* SUBSCRIPTIONS */
    public function subs(){
        $subs = Subscription::with('plan')->latest()->paginate(20);
        $plans = Plan::orderBy('price_month')->get();
        return view('billing.subs.index', compact('subs','plans'));
    }
    public function subsStore(Request $r){
        $d=$r->validate([
            'username'=>'required','plan_id'=>'required|exists:plans,id'
        ]);
        $plan = Plan::findOrFail($d['plan_id']);
        $sub = Subscription::updateOrCreate(
            ['username'=>$d['username']],
            ['plan_id'=>$plan->id,'status'=>'active','started_at'=>now()->toDateString()]
        );
        if ($plan->groupname) {
            RadUserGroup::updateOrCreate(
                ['username'=>$d['username'],'groupname'=>$plan->groupname],
                ['priority'=>1]
            );
        }
        return back()->with('ok','Subscription tersimpan');
    }

    /* INVOICES (view-only sederhana) */
    public function invoices(){
        $invoices = Invoice::with('subscription.plan')->latest()->paginate(20);
        return view('billing.invoices.index', compact('invoices'));
    }
}

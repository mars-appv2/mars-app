<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BillingEnforcer;

class BillingEnforceController extends Controller
{
    public function run(Request $r, BillingEnforcer $enf)
    {
        $this->middleware(['auth','permission:manage billing']);

        $mikId = $r->input('mikrotik_id');
        $res = $enf->run($mikId ? (int)$mikId : null);

        return back()->with(
            'ok',
            "Enforce: {$res->isolated} diisolir, {$res->restored} dipulihkan, update router: {$res->routerUpdates}."
        );
    }
}

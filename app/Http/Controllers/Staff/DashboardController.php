<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        // Default counts
        $activeSessions = 0;
        $unpaidInvoices = 0;
        $openTickets    = 0;
        $routersOnline  = 0;

        // Active PPPoE sessions: radacct acctstoptime NULL
        if (Schema::hasTable('radacct')) {
            try {
                $activeSessions = DB::table('radacct')->whereNull('acctstoptime')->count();
            } catch (\Throwable $e) {}
        }

        // Unpaid invoices: cari kolom status
        if (Schema::hasTable('invoices')) {
            try {
                $cols = Schema::getColumnListing('invoices');
                $q = DB::table('invoices');
                if (in_array('status',$cols)) {
                    $q->whereNotIn('status', ['paid','lunas','success','settlement']);
                } else {
                    // fallback: total>0 and paid_at null
                    if (in_array('paid_at',$cols)) $q->whereNull('paid_at');
                    if (in_array('total',$cols))   $q->where('total','>',0);
                }
                $unpaidInvoices = $q->count();
            } catch (\Throwable $e) {}
        }

        // Open tickets: status open/pending
        if (Schema::hasTable('tickets')) {
            try {
                $cols = Schema::getColumnListing('tickets');
                $q = DB::table('tickets');
                if (in_array('status',$cols)) {
                    $q->whereIn('status', ['open','pending','progress','onhold']);
                }
                $openTickets = $q->count();
            } catch (\Throwable $e) {}
        }

        // Routers online: tabel mikrotiks (opsional)
        if (Schema::hasTable('mikrotiks')) {
            try {
                $cols = Schema::getColumnListing('mikrotiks');
                if (in_array('status',$cols)) {
                    $routersOnline = DB::table('mikrotiks')->where('status','online')->count();
                } elseif (in_array('last_seen',$cols)) {
                    $routersOnline = DB::table('mikrotiks')->where('last_seen','>=',now()->subMinutes(5))->count();
                } else {
                    $routersOnline = DB::table('mikrotiks')->count();
                }
            } catch (\Throwable $e) {}
        }

        return view('staff.dashboard', compact('activeSessions','unpaidInvoices','openTickets','routersOnline'));
    }
}

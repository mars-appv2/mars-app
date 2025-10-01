<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class TicketsController extends Controller
{
    public function index()
    {
        $tickets = [];
        if (Schema::hasTable('tickets')) {
            $q = DB::table('tickets');
            if (Schema::hasColumn('tickets','created_at')) $q->orderByDesc('created_at');
            elseif (Schema::hasColumn('tickets','id'))     $q->orderByDesc('id');
            $tickets = $q->limit(100)->get();
        }
        return view('staff.tickets', compact('tickets'));
    }

    public function store(Request $r)
    {
        if (!Schema::hasTable('tickets')) {
            return back()->with('err','Tabel tickets tidak ada.');
        }

        $r->validate([
            'subject'     => 'required|string|max:190',
            'description' => 'required|string',
            'priority'    => 'nullable|in:low,normal,high,urgent'
        ]);

        $cols = Schema::getColumnListing('tickets');
        $data = [];

        if (in_array('code',$cols,true))        $data['code'] = strtoupper(Str::random(8));
        if (in_array('subject',$cols,true))     $data['subject'] = $r->subject;
        if (in_array('description',$cols,true)) $data['description'] = $r->description;
        if (in_array('status',$cols,true))      $data['status'] = 'open';
        if (in_array('priority',$cols,true))    $data['priority'] = $r->priority ?: 'normal';
        if (in_array('created_by',$cols,true))  $data['created_by'] = Auth::id();

        if (in_array('created_at',$cols,true)) $data['created_at'] = now();
        if (in_array('updated_at',$cols,true)) $data['updated_at'] = now();

        DB::table('tickets')->insert($data);

        return back()->with('ok','Ticket dibuat.');
    }
}

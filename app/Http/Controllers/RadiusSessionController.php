<?php
namespace App\Http\Controllers;

use App\Models\Radius\RadAcct;
use Illuminate\Http\Request;

class RadiusSessionController extends Controller
{
    public function index(Request $r){
        $q = trim((string)$r->query('q',''));
        $base = RadAcct::whereNull('acctstoptime');
        if ($q!=='') $base->where('username','like',"%$q%");
        $sessions = $base->orderBy('acctstarttime','desc')->paginate(20);

        return view('radius.sessions.index', compact('sessions','q'));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsappUiController extends Controller
{
    private function gw(): string
    {
        return rtrim(env('WA_GATEWAY_URL', 'http://127.0.0.1:3900'), '/');
    }

    public function index()
    {
        return view('wa.index');
    }

    public function qrJson()
    {
        try {
            $res = Http::timeout(5)->get($this->gw().'/status')->json();
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'err'=>$e->getMessage()], 500);
        }
    }

    public function logout()
    {
        try {
            Http::timeout(10)->post($this->gw().'/logout');
        } catch (\Throwable $e) {}
        return back()->with('ok', 'Logout & reset session dikirim ke gateway.');
    }

    public function send(Request $r)
    {
        $d = $r->validate(['to'=>'required','text'=>'required|string|max:2000']);
        try{
            Http::timeout(15)->post($this->gw().'/send', $d)->throw();
            return back()->with('ok','Pesan terkirim.');
        }catch(\Throwable $e){
            return back()->with('err','Gagal kirim: '.$e->getMessage())->withInput();
        }
    }

    public function broadcast(Request $r)
    {
        $d = $r->validate(['numbers'=>'required|string','text'=>'required|string|max:2000']);
        $list = collect(preg_split('/[\r\n,;]+/', $d['numbers']))->map(function($x){
            $x = trim((string)$x);
            $x = preg_replace('/[^\d]/','',$x);
            if ($x==='') return null;
            if (Str::startsWith($x,'0')) $x = '62'.substr($x,1);
            return $x;
        })->filter()->values()->all();

        if (empty($list)) return back()->with('err','Daftar nomor kosong.');

        try{
            $res = Http::timeout(120)->post($this->gw().'/broadcast', [
                'to'=>$list, 'text'=>$d['text']
            ])->throw()->json();
            $ok  = collect($res['results']??[])->where('ok',true)->count();
            $bad = collect($res['results']??[])->where('ok',false)->count();
            return back()->with('ok',"Broadcast terkirim (OK: {$ok}, Gagal: {$bad}).");
        }catch(\Throwable $e){
            return back()->with('err','Gagal broadcast: '.$e->getMessage())->withInput();
        }
    }

    public function inbox(Request $r)
    {
        $q = trim((string)$r->query('q',''));
        $rows = DB::table('wa_messages')
            ->when($q!=='', function($qq) use($q){
                $qq->where('from','like',"%{$q}%")->orWhere('text','like',"%{$q}%");
            })
            ->orderBy('id','desc')->limit(500)->get();
        return view('wa.inbox', ['rows'=>$rows, 'q'=>$q]);
    }

    // ====== Webhook diterima dari Node Gateway ======
    public function webhook(Request $r)
    {
        $secret = $r->header('X-WA-Secret','');
        if ($secret !== env('WA_WEBHOOK_SECRET','changeme')) {
            return response()->json(['ok'=>false,'err'=>'forbidden'], 403);
        }

        $p = $r->all();
        // simpan ringkas
        DB::table('wa_messages')->insert([
            'wa_id' => (string)($p['wa_id'] ?? ''),
            'from'  => (string)($p['from']  ?? ''),
            'to'    => (string)($p['to']    ?? ''),
            'text'  => (string)($p['text']  ?? ''),
            'type'  => (string)($p['type']  ?? ''),
            'ts'    => (int)($p['ts'] ?? time()),
            'raw'   => json_encode($p),
            'created_at'=>now(),'updated_at'=>now(),
        ]);

        return response()->json(['ok'=>true]);
    }
}

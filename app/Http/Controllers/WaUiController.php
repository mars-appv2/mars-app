<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WaUiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function gwBase(): string
    {
        return rtrim(env('WA_GATEWAY_URL', 'http://127.0.0.1:3900'), '/');
    }

    /* ---------------- View ---------------- */
    public function index()
    {
        return view('wa.index');
    }

    /* ---------------- API Proxy ---------------- */
    public function refresh()
    {
        try {
            $j = $this->curlGetJson($this->gwBase().'/status', 4);
            if (!is_array($j)) throw new \RuntimeException('Bad gateway');
            return response()->json($j);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false, 'error'=>$e->getMessage()], 502);
        }
    }

    public function qr()
    {
        // proxy qr.png supaya same-origin
        try {
            $url = $this->gwBase().'/qr.png?t='.time();
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $bin = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || !$bin) {
                return response('', 404);
            }
            return response($bin, 200)->header('Content-Type','image/png')->header('Cache-Control','no-store');
        } catch (\Throwable $e) {
            return response('', 500);
        }
    }

    public function send(Request $r)
    {
        $to   = trim((string)$r->input('to',''));
        $text = trim((string)$r->input('text',''));
        if ($to==='' || $text==='') return back()->with('err','Isi nomor & pesan.');

        try{
            $res = $this->curlPostJson($this->gwBase().'/sendText', ['to'=>$to,'text'=>$text], 6);
            if (($res['ok'] ?? false) === true) return back()->with('ok','Terkirim.');
            return back()->with('err','Gagal: '.($res['error'] ?? 'unknown'));
        }catch(\Throwable $e){
            return back()->with('err','Gateway error: '.$e->getMessage());
        }
    }

    public function broadcast(Request $r)
    {
        $numbersRaw = (string)$r->input('numbers', '');
        $text       = trim((string)$r->input('text', ''));

        // pecah per baris, buang kosong
        $numbers = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $numbersRaw)), fn($v) => $v !== ''));

        if (empty($numbers) || $text === '') {
            return back()->with('err', 'Isi nomor & pesan untuk broadcast.');
        }

        $url = $this->gwBase() . '/broadcast';
        try {
            // 5 nomor per 60 detik, async
            $payload = [
                'numbers'      => $numbers,
                'text'         => $text,
                'rate_per_min' => 5,
                'window_sec'   => 60,
            ];
            $res = $this->curlPostJson($url, $payload, 10);
            if (($res['ok'] ?? false) === true) {
                $jobId = $res['job_id'] ?? '-';
                return back()
                    ->with('ok', "Broadcast dimulai (Job: {$jobId}). Sistem akan mengirim 5 nomor per menit.")
                    ->with('wa_job', $jobId);
            }
            return back()->with('err', 'Broadcast gagal: '.(($res['error'] ?? 'unknown')));
        } catch (\Throwable $e) {
            return back()->with('err', 'Gateway error: '.$e->getMessage());
        }
    }

    public function broadcastStatus(Request $r)
    {
        $id  = trim((string)$r->query('id',''));
        if ($id === '') return response()->json(['ok'=>false, 'error'=>'missing_id'], 400);

        $url = $this->gwBase() . '/broadcast/status?id=' . urlencode($id);
        $j   = $this->curlGetJson($url, 5);
        if (!is_array($j)) return response()->json(['ok'=>false, 'error'=>'gateway_unreachable'], 502);
        return response()->json($j);
    }

    public function broadcastCancel(Request $r)
    {
        $id = trim((string)$r->input('id',''));
        if ($id === '') return back()->with('err','Job ID kosong.');

        try{
            $res = $this->curlPostJson($this->gwBase().'/broadcast/cancel', ['id'=>$id], 6);
            if (($res['ok'] ?? false) === true) {
                return back()->with('ok','Job dibatalkan.')->with('wa_job', $id);
            }
            return back()->with('err','Gagal membatalkan job: '.($res['error'] ?? 'unknown'))->with('wa_job', $id);
        }catch(\Throwable $e){
            return back()->with('err','Gateway error: '.$e->getMessage())->with('wa_job', $id);
        }
    }

    /* ---------------- curl helpers ---------------- */
    private function curlGetJson(string $url, int $timeout = 5)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $txt = curl_exec($ch);
        curl_close($ch);
        $j = json_decode($txt, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $j : null;
    }

    private function curlPostJson(string $url, array $payload, int $timeout = 8)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $txt = curl_exec($ch);
        curl_close($ch);
        $j = json_decode($txt, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $j : ['ok'=>false,'error'=>'bad_json'];
    }
}

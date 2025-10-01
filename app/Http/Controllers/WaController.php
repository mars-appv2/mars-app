<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WaController extends Controller
{
    public function index()
    {
        return view('wa.index');
    }

    private function gw(): string
    {
        return rtrim(env('WA_GATEWAY_URL', 'http://127.0.0.1:3900'), '/');
    }

    /** GET via cURL (aman untuk http/https, tanpa verify ssl kalau self-signed) */
    private function httpGet(string $url): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER     => ['Accept: */*'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 200 && $code < 300 && $body !== false) {
                return $body;
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function refresh()
    {
        $raw = $this->httpGet($this->gw().'/status');
        if (!$raw) {
            return response()->json([
                'connected' => false, 'qr' => false, 'me' => null,
                'pairing_code' => null, 'error' => 'Gateway tidak dapat diakses'
            ]);
        }
        $data = json_decode($raw, true) ?: [];
        $data['error'] = null;
        return response()->json($data);
    }

    public function qr()
    {
    	$png = $this->httpGet(rtrim(env('WA_GATEWAY_URL','http://127.0.0.1:3900'),'/').'/qr');
    	if (!$png) abort(404);
    	return response($png, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }


    public function send(Request $r)
    {
        $to  = preg_replace('/\D+/', '', (string)$r->input('to'));
        $msg = (string)$r->input('message');
        if ($to === '' || $msg === '') return back()->with('err','Nomor & pesan wajib diisi.');

        $ch = curl_init($this->gw().'/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['to'=>$to,'message'=>$msg], JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $ok   = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);

        return back()->with($ok ? 'ok' : 'err', $ok ? 'Terkirim' : 'Gagal kirim');
    }

    // ... (kelas sama seperti sebelumnya)

    public function broadcast(Request $r)
    {
    	$numbersRaw = (string)$r->input('numbers', '');
    	$text       = trim((string)$r->input('text', ''));

    	$numbers = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $numbersRaw)), fn($v) => $v !== ''));

    	if (empty($numbers) || $text === '') {
            return back()->with('err', 'Isi nomor & pesan untuk broadcast.');
    	}

    	$url = $this->gwBase() . '/broadcast';
    	try {
            // 5 nomor per 60 detik + async job
            $payload = [
            	'numbers'      => $numbers,
            	'text'         => $text,
            	'rate_per_min' => 5,
            	'window_sec'   => 60,
            	'async'        => true,
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

    // ========= Proxy status job dari gateway ======== 
    public function broadcastStatus(Request $r)
    {
    	$id  = trim((string)$r->query('id',''));
    	if ($id === '') return response()->json(['ok'=>false, 'error'=>'missing_id'], 400);

    	$url = $this->gwBase() . '/broadcast/status?id=' . urlencode($id);
    	$j   = $this->curlGetJson($url, 5);
    	if (!is_array($j)) return response()->json(['ok'=>false, 'error'=>'gateway_unreachable'], 502);
    	return response()->json($j);
    }



    public function webhook(Request $r)
    {
        \Log::info('[WA webhook]', $r->all());
        return response()->json(['ok'=>true]);
    }
}

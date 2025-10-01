<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\WaService;

class WaPublicController extends Controller
{
    /**
     * POST /wa-gw/send
     * Body JSON: { "to": "62xxxxxxxxxx", "text": "pesan" }
     */
    public function send(Request $r)
    {
        try {
            $to   = trim((string) $r->input('to', ''));
            $text = (string) $r->input('text', '');

            if ($to === '' || $text === '') {
                return response()->json(['ok' => false, 'msg' => 'Missing to/text'], 422);
            }

            $wa = new WaService();
            $ok = $wa->sendText($to, $text);

            if (!$ok) {
                return response()->json(['ok' => false, 'msg' => 'Forward to WA gateway failed'], 502);
            }
            return response()->json(['ok' => true], 200);
        } catch (\Throwable $e) {
            Log::error('[WA-PUBLIC] send exception: '.$e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'Exception'], 500);
        }
    }

    /**
     * POST /wa-gw/broadcast
     * Body JSON: { "to": ["62xxxx","62yyyy"], "text": "pesan" }
     */
    public function broadcast(Request $r)
    {
        try {
            $to   = $r->input('to', []);
            $text = (string) $r->input('text', '');

            if (!is_array($to) || empty($to) || $text === '') {
                return response()->json(['ok' => false, 'msg' => 'Missing to[]/text'], 422);
            }

            $wa = new WaService();
            $ok = $wa->broadcast($to, $text);

            if (!$ok) {
                return response()->json(['ok' => false, 'msg' => 'Forward broadcast failed'], 502);
            }
            return response()->json(['ok' => true], 200);
        } catch (\Throwable $e) {
            Log::error('[WA-PUBLIC] broadcast exception: '.$e->getMessage());
            return response()->json(['ok' => false, 'msg' => 'Exception'], 500);
        }
    }

    /** GET /wa-gw/status (opsional) */
    public function status()
    {
        try {
            $wa = new WaService();
            $pong = $wa->ping();
            return response()->json(['ok' => (bool) $pong], 200);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false], 200);
        }
    }
}

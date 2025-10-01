<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WaGatewayController extends Controller
{
    private function gwUrl(): string
    {
        return env('WA_GATEWAY_URL', 'http://127.0.0.1:3900');
    }

    public function index(Request $r)
    {
        $gw = $this->gwUrl();
        $status = ['ok'=>false, 'connected'=>false, 'pairing_code'=>null];
        try {
            $resp = Http::timeout(5)->get($gw.'/status');
            if ($resp->ok()) $status = $resp->json();
        } catch (\Throwable $e) {
            $status['error'] = $e->getMessage();
        }
        return view('wa.index', ['gw'=>$gw, 'status'=>$status]);
    }

    // Proxy QR PNG dari gateway
    public function qr()
    {
        $gw = $this->gwUrl();
        $resp = Http::timeout(5)->get($gw.'/qr.png');
        abort_if(!$resp->ok(), 404);
        return response($resp->body(), 200)
            ->header('Content-Type','image/png')
            ->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');
    }
}

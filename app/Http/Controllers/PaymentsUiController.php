<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class PaymentsUiController extends Controller
{
    public function index() { return redirect()->route('payments.manual'); }

    /* =========================
     * MANUAL
     * ========================= */
    public function manual()
    {
        $cfg = [
            'enable_manual' => env('PAY_ENABLE_MANUAL', true) ? '1' : '0',

            'mandiri_name'  => env('PAY_MANDIRI_NAME', ''),
            'mandiri_no'    => env('PAY_MANDIRI_NO', ''),
            'bca_name'      => env('PAY_BCA_NAME', ''),
            'bca_no'        => env('PAY_BCA_NO', ''),
            'bri_name'      => env('PAY_BRI_NAME', ''),
            'bri_no'        => env('PAY_BRI_NO', ''),

            'qris_url'      => env('PAY_QRIS_URL', ''),

            'enable_ovo'    => env('PAY_ENABLE_OVO', 0) ? '1' : '0',
            'enable_dana'   => env('PAY_ENABLE_DANA',0) ? '1' : '0',
            'enable_gopay'  => env('PAY_ENABLE_GOPAY',0)? '1' : '0',
        ];
        return view('payments.manual', compact('cfg'));
    }

    public function saveManual(Request $r)
    {
        $pairs = [
            'PAY_ENABLE_MANUAL' => $r->boolean('enable_manual') ? '1' : '0',

            'PAY_MANDIRI_NAME'  => trim((string)$r->input('mandiri_name')),
            'PAY_MANDIRI_NO'    => trim((string)$r->input('mandiri_no')),
            'PAY_BCA_NAME'      => trim((string)$r->input('bca_name')),
            'PAY_BCA_NO'        => trim((string)$r->input('bca_no')),
            'PAY_BRI_NAME'      => trim((string)$r->input('bri_name')),
            'PAY_BRI_NO'        => trim((string)$r->input('bri_no')),

            'PAY_ENABLE_OVO'    => $r->boolean('enable_ovo')   ? '1' : '0',
            'PAY_ENABLE_DANA'   => $r->boolean('enable_dana')  ? '1' : '0',
            'PAY_ENABLE_GOPAY'  => $r->boolean('enable_gopay') ? '1' : '0',
        ];

        // Upload QRIS (opsional)
        if ($r->hasFile('qris')) {
            $path = $r->file('qris')->storeAs('public/payments', 'qris.png');
            $pairs['PAY_QRIS_URL'] = asset(str_replace('public', 'storage', $path));
        }

        $this->updateEnv($pairs);
        try { Artisan::call('config:clear'); } catch (\Throwable $e) {}

        return back()->with('ok','Pengaturan pembayaran manual tersimpan.');
    }

    /* =========================
     * GATEWAY
     * ========================= */
    public function gateway()
    {
        $cfg = [
            'provider'           => env('PAY_GATEWAY_PROVIDER','none'), // none|midtrans|xendit
            // Midtrans
            'mid_server_key'     => env('MIDTRANS_SERVER_KEY',''),
            'mid_client_key'     => env('MIDTRANS_CLIENT_KEY',''),
            'mid_is_production'  => env('MIDTRANS_IS_PRODUCTION', false) ? '1' : '0',
            // Xendit
            'xendit_key'         => env('XENDIT_SECRET_KEY',''),
        ];
        return view('payments.gateway', compact('cfg'));
    }

    public function saveGateway(Request $r)
    {
        $provider = in_array($r->input('provider'), ['midtrans','xendit','none']) ? $r->input('provider') : 'none';

        $pairs = ['PAY_GATEWAY_PROVIDER'=>$provider];

        if ($provider === 'midtrans') {
            $pairs['MIDTRANS_SERVER_KEY']    = trim((string)$r->input('mid_server_key'));
            $pairs['MIDTRANS_CLIENT_KEY']    = trim((string)$r->input('mid_client_key'));
            $pairs['MIDTRANS_IS_PRODUCTION'] = $r->boolean('mid_is_production') ? '1' : '0';
        }
        if ($provider === 'xendit') {
            $pairs['XENDIT_SECRET_KEY']      = trim((string)$r->input('xendit_key'));
        }

        $this->updateEnv($pairs);
        try { Artisan::call('config:clear'); } catch (\Throwable $e) {}

        return back()->with('ok','Kredensial gateway tersimpan.');
    }

    /* ===== util ===== */
    private function updateEnv(array $pairs): void
    {
        if (empty($pairs)) return;
        $env = base_path('.env');
        if (!is_file($env) || !is_writable($env)) return;
        $content = file_get_contents($env);
        foreach ($pairs as $k => $v) {
            $v = str_replace(["\r","\n"], ' ', (string)$v);
            $line = $k.'="'.addslashes($v).'"';
            $pattern = "/^{$k}=.*$/m";
            if (preg_match($pattern, $content)) $content = preg_replace($pattern, $line, $content);
            else $content .= PHP_EOL.$line;
        }
        file_put_contents($env, $content);
    }
}

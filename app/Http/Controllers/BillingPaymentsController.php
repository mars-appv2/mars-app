<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class BillingPaymentsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth','permission:manage billing']);
    }

    public function index()
    {
        // Bank accounts
        $banks = [
            'bca' => [
                'no'   => $this->get('bank_bca_no'),
                'name' => $this->get('bank_bca_name'),
                'note' => $this->get('bank_bca_note'),
            ],
            'bri' => [
                'no'   => $this->get('bank_bri_no'),
                'name' => $this->get('bank_bri_name'),
                'note' => $this->get('bank_bri_note'),
            ],
            'mandiri' => [
                'no'   => $this->get('bank_mandiri_no'),
                'name' => $this->get('bank_mandiri_name'),
                'note' => $this->get('bank_mandiri_note'),
            ],
        ];

        // E-wallet
        $wallets = [
            'ovo' => [
                'no'   => $this->get('wallet_ovo_no'),
                'name' => $this->get('wallet_ovo_name'),
                'note' => $this->get('wallet_ovo_note'),
            ],
            'dana' => [
                'no'   => $this->get('wallet_dana_no'),
                'name' => $this->get('wallet_dana_name'),
                'note' => $this->get('wallet_dana_note'),
            ],
            'gopay' => [
                'no'   => $this->get('wallet_gopay_no'),
                'name' => $this->get('wallet_gopay_name'),
                'note' => $this->get('wallet_gopay_note'),
            ],
        ];

        // Optional payment gateway (Midtrans) – pakai key yang sama dgn Settings
        $gateway = [
            'server_key'     => env('MIDTRANS_SERVER_KEY', $this->get('midtrans_server_key')),
            'client_key'     => env('MIDTRANS_CLIENT_KEY', $this->get('midtrans_client_key')),
            'is_production'  => (bool) (int) ($this->get('midtrans_is_production', '0')),
        ];

        return view('billing.payments', compact('banks','wallets','gateway'));
    }

    public function save(Request $r)
    {
        // BANKS
        $this->put('bank_bca_no',   $r->input('bank_bca_no'));
        $this->put('bank_bca_name', $r->input('bank_bca_name'));
        $this->put('bank_bca_note', $r->input('bank_bca_note'));

        $this->put('bank_bri_no',   $r->input('bank_bri_no'));
        $this->put('bank_bri_name', $r->input('bank_bri_name'));
        $this->put('bank_bri_note', $r->input('bank_bri_note'));

        $this->put('bank_mandiri_no',   $r->input('bank_mandiri_no'));
        $this->put('bank_mandiri_name', $r->input('bank_mandiri_name'));
        $this->put('bank_mandiri_note', $r->input('bank_mandiri_note'));

        // WALLETS
        $this->put('wallet_ovo_no',   $r->input('wallet_ovo_no'));
        $this->put('wallet_ovo_name', $r->input('wallet_ovo_name'));
        $this->put('wallet_ovo_note', $r->input('wallet_ovo_note'));

        $this->put('wallet_dana_no',   $r->input('wallet_dana_no'));
        $this->put('wallet_dana_name', $r->input('wallet_dana_name'));
        $this->put('wallet_dana_note', $r->input('wallet_dana_note'));

        $this->put('wallet_gopay_no',   $r->input('wallet_gopay_no'));
        $this->put('wallet_gopay_name', $r->input('wallet_gopay_name'));
        $this->put('wallet_gopay_note', $r->input('wallet_gopay_note'));

        // Gateway (opsional)
        if ($r->filled('midtrans_server_key') || $r->filled('midtrans_client_key') || $r->has('midtrans_is_production')) {
            $this->put('midtrans_server_key',     $r->input('midtrans_server_key'));
            $this->put('midtrans_client_key',     $r->input('midtrans_client_key'));
            $this->put('midtrans_is_production',  $r->boolean('midtrans_is_production') ? '1' : '0');
        }

        return back()->with('ok','Pengaturan pembayaran tersimpan.');
    }

    private function get($key, $def = '')
    {
        if (!class_exists(Setting::class)) return $def;
        $row = Setting::where('key',$key)->first();
        return $row ? (string)$row->value : $def;
    }

    private function put($key, $val)
    {
        if (!class_exists(Setting::class)) return;
        $val = (string) ($val ?? '');
        Setting::updateOrCreate(['key'=>$key], ['value'=>$val]);
    }
}

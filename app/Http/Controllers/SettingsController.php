<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /* ================= TELEGRAM ================= */
    public function telegram()
    {
        $token = optional(Setting::firstWhere('key','telegram_token'))->value;
        $chat  = optional(Setting::firstWhere('key','telegram_chat_ids'))->value;
        return view('settings.telegram', compact('token','chat'));
    }
    public function telegramSave(Request $r)
    {
        Setting::updateOrCreate(['key'=>'telegram_token'],    ['value'=>(string)$r->token]);
        Setting::updateOrCreate(['key'=>'telegram_chat_ids'], ['value'=>(string)$r->chat_ids]);
        return back()->with('ok','Saved');
    }

    /* ================= WHATSAPP ================= */
    public function whatsapp()
    {
        $url   = optional(Setting::firstWhere('key','wa_api_url'))->value;
        $token = optional(Setting::firstWhere('key','wa_token'))->value;
        $to    = optional(Setting::firstWhere('key','wa_default_to'))->value;
        return view('settings.whatsapp', compact('url','token','to'));
    }
    public function whatsappSave(Request $r)
    {
        Setting::updateOrCreate(['key'=>'wa_api_url'],    ['value'=>(string)$r->api_url]);
        Setting::updateOrCreate(['key'=>'wa_token'],      ['value'=>(string)$r->token]);
        Setting::updateOrCreate(['key'=>'wa_default_to'], ['value'=>(string)$r->default_to]);
        return back()->with('ok','Saved');
    }

    /* ================= PAYMENT (Gateway + Rekening/E-wallet) ================= */
    public function payment()
    {
        $server_key    = env('MIDTRANS_SERVER_KEY',  optional(Setting::firstWhere('key','midtrans_server_key'))->value);
        $client_key    = env('MIDTRANS_CLIENT_KEY',  optional(Setting::firstWhere('key','midtrans_client_key'))->value);
        $is_production = (bool) filter_var(
            env('MIDTRANS_IS_PRODUCTION', optional(Setting::firstWhere('key','midtrans_is_production'))->value),
            FILTER_VALIDATE_BOOLEAN
        );

        $bank_bca_no       = optional(Setting::firstWhere('key','bank_bca_no'))->value;
        $bank_bca_name     = optional(Setting::firstWhere('key','bank_bca_name'))->value;
        $bank_bri_no       = optional(Setting::firstWhere('key','bank_bri_no'))->value;
        $bank_bri_name     = optional(Setting::firstWhere('key','bank_bri_name'))->value;
        $bank_mandiri_no   = optional(Setting::firstWhere('key','bank_mandiri_no'))->value;
        $bank_mandiri_name = optional(Setting::firstWhere('key','bank_mandiri_name'))->value;

        $ewallet_gopay_no   = optional(Setting::firstWhere('key','ewallet_gopay_no'))->value;
        $ewallet_gopay_name = optional(Setting::firstWhere('key','ewallet_gopay_name'))->value;
        $ewallet_ovo_no     = optional(Setting::firstWhere('key','ewallet_ovo_no'))->value;
        $ewallet_ovo_name   = optional(Setting::firstWhere('key','ewallet_ovo_name'))->value;
        $ewallet_dana_no    = optional(Setting::firstWhere('key','ewallet_dana_no'))->value;
        $ewallet_dana_name  = optional(Setting::firstWhere('key','ewallet_dana_name'))->value;

        $gopay_qr_url = optional(Setting::firstWhere('key','ewallet_gopay_qr_url'))->value;
        $ovo_qr_url   = optional(Setting::firstWhere('key','ewallet_ovo_qr_url'))->value;
        $dana_qr_url  = optional(Setting::firstWhere('key','ewallet_dana_qr_url'))->value;

        return view('settings.payment', compact(
            'server_key','client_key','is_production',
            'bank_bca_no','bank_bca_name','bank_bri_no','bank_bri_name','bank_mandiri_no','bank_mandiri_name',
            'ewallet_gopay_no','ewallet_gopay_name','ewallet_ovo_no','ewallet_ovo_name','ewallet_dana_no','ewallet_dana_name',
            'gopay_qr_url','ovo_qr_url','dana_qr_url'
        ));
    }

    public function paymentSave(Request $r)
    {
        Setting::updateOrCreate(['key'=>'midtrans_server_key'],    ['value'=>(string)$r->server_key]);
        Setting::updateOrCreate(['key'=>'midtrans_client_key'],    ['value'=>(string)$r->client_key]);
        Setting::updateOrCreate(['key'=>'midtrans_is_production'], ['value'=> $r->is_production ? '1' : '0']);

        $pairs = [
            'bank_bca_no'       => (string)$r->bank_bca_no,
            'bank_bca_name'     => (string)$r->bank_bca_name,
            'bank_bri_no'       => (string)$r->bank_bri_no,
            'bank_bri_name'     => (string)$r->bank_bri_name,
            'bank_mandiri_no'   => (string)$r->bank_mandiri_no,
            'bank_mandiri_name' => (string)$r->bank_mandiri_name,
            'ewallet_gopay_no'   => (string)$r->ewallet_gopay_no,
            'ewallet_gopay_name' => (string)$r->ewallet_gopay_name,
            'ewallet_ovo_no'     => (string)$r->ewallet_ovo_no,
            'ewallet_ovo_name'   => (string)$r->ewallet_ovo_name,
            'ewallet_dana_no'    => (string)$r->ewallet_dana_no,
            'ewallet_dana_name'  => (string)$r->ewallet_dana_name,
        ];
        foreach ($pairs as $k => $v) {
            Setting::updateOrCreate(['key'=>$k], ['value'=>$v]);
        }

        if ($r->hasFile('gopay_qr')) {
            $path = $r->file('gopay_qr')->storeAs('public/payment','gopay.png');
            Setting::updateOrCreate(['key'=>'ewallet_gopay_qr_url'], ['value'=>asset(str_replace('public','storage',$path))]);
        }
        if ($r->hasFile('ovo_qr')) {
            $path = $r->file('ovo_qr')->storeAs('public/payment','ovo.png');
            Setting::updateOrCreate(['key'=>'ewallet_ovo_qr_url'], ['value'=>asset(str_replace('public','storage',$path))]);
        }
        if ($r->hasFile('dana_qr')) {
            $path = $r->file('dana_qr')->storeAs('public/payment','dana.png');
            Setting::updateOrCreate(['key'=>'ewallet_dana_qr_url'], ['value'=>asset(str_replace('public','storage',$path))]);
        }

        return back()->with('ok','Pengaturan pembayaran tersimpan.');
    }

    /* ================= ROLES (yang sudah ada) ================= */
    public function roles()
    {
        $users = User::orderBy('id')->get();
        $roles = Role::pluck('name')->all();
        return view('settings.roles', compact('users','roles'));
    }
    public function rolesSave(Request $r)
    {
        $data = $r->get('roles',[]);
        foreach($data as $uid=>$roles){
            $u = User::find($uid);
            if(!$u) continue;
            $u->syncRoles(array_keys($roles));
        }
        return back()->with('ok','Roles updated');
    }

    /* ================= PERMISSIONS (BARU) ================= */

    /** daftar permission yang dipakai menu/sidebar */
    private function menuPermissions()
    {
        return [
            ['group'=>'Dashboard', 'items'=>[
                ['name'=>'view dashboard',  'label'=>'Lihat Dashboard'],
            ]],
            ['group'=>'Network', 'items'=>[
                ['name'=>'manage mikrotik', 'label'=>'Kelola Mikrotik/Network'],
            ]],
            ['group'=>'Traffic', 'items'=>[
                ['name'=>'view traffic',    'label'=>'Lihat Traffic Monitor'],
            ]],
            ['group'=>'RADIUS', 'items'=>[
                ['name'=>'manage radius',   'label'=>'Kelola RADIUS (Users/Sessions)'],
            ]],
            ['group'=>'Billing', 'items'=>[
                ['name'=>'manage billing',  'label'=>'Kelola Billing (Plans/Invoices/Payments)'],
            ]],
            ['group'=>'Settings', 'items'=>[
                ['name'=>'manage settings', 'label'=>'Kelola Settings'],
            ]],
        ];
    }

    /** pastikan permission ada di DB */
    private function ensureMenuPermissionsExist()
    {
        foreach ($this->menuPermissions() as $grp) {
            foreach ($grp['items'] as $it) {
                Permission::firstOrCreate(['name'=>$it['name']]);
            }
        }
    }

    public function permissions()
    {
        $this->ensureMenuPermissionsExist();

        $roles  = Role::orderBy('name')->get();
        $groups = $this->menuPermissions();

        // matrix[role_id][perm_name] = true/false
        $matrix = [];
        foreach ($roles as $role) {
            $names = $role->permissions->pluck('name')->all();
            foreach ($groups as $grp) {
                foreach ($grp['items'] as $it) {
                    $matrix[$role->id][$it['name']] = in_array($it['name'], $names, true);
                }
            }
        }

        return view('settings.permissions', compact('roles','groups','matrix'));
    }

    public function permissionsSave(Request $r)
    {
        $this->ensureMenuPermissionsExist();

        $roles = Role::orderBy('name')->get();
        $posted = (array) $r->get('perm', []);

        // daftar permission yang dikelola halaman ini
        $managed = [];
        foreach ($this->menuPermissions() as $grp) {
            foreach ($grp['items'] as $it) $managed[] = $it['name'];
        }

        foreach ($roles as $role) {
            foreach ($managed as $pname) {
                $checked = !empty($posted[$pname][$role->id]);
                if ($checked) {
                    if (!$role->hasPermissionTo($pname)) {
                        $role->givePermissionTo($pname);
                    }
                } else {
                    if ($role->hasPermissionTo($pname)) {
                        $role->revokePermissionTo($pname);
                    }
                }
            }
        }

        return back()->with('ok','Permissions updated');
    }
}

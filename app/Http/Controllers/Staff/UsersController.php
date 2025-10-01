<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UsersController extends Controller
{
    protected function userModel()
    {
        // Laravel 8+: App\Models\User, Laravel 7: App\User
        return class_exists(\App\Models\User::class) ? \App\Models\User::class : \App\User::class;
    }

    // LIST USER (untuk operator)
    public function index(Request $r)
    {
        $User = $this->userModel();
        $q = $r->input('q');

        $users = $User::query()
            ->when($q, function($qq) use ($q) {
                $qq->where('email','like',"%$q%")
                   ->orWhere('name','like',"%$q%");
                if (Schema::hasColumn('users','username')) $qq->orWhere('username','like',"%$q%");
                if (Schema::hasColumn('users','phone'))    $qq->orWhere('phone','like',"%$q%");
                if (Schema::hasColumn('users','wa_number'))$qq->orWhere('wa_number','like',"%$q%");
            })
            ->orderByDesc('id')->paginate(20);

        return view('staff.users.index', compact('users','q'));
    }

    // FORM TAMBAH
    public function create()
    {
        $cols = Schema::getColumnListing('users');
        return view('staff.users.create', compact('cols'));
    }

    // SIMPAN USER
    public function store(Request $r)
    {
        $cols = Schema::getColumnListing('users');

        $rules = [
            'name'     => ['required','string','max:100'],
            'email'    => ['required','email','max:190','unique:users,email'],
            'password' => ['required','string','min:8'],
        ];
        if (in_array('username',$cols)) $rules['username'] = ['nullable','string','max:100','unique:users,username'];
        if (in_array('role',$cols))     $rules['role']     = ['nullable','string','max:32'];
        if (in_array('phone',$cols))    $rules['phone']    = ['nullable','string','max:32'];
        if (in_array('wa_number',$cols))$rules['wa_number']= ['nullable','string','max:32'];

        $data = $r->validate($rules);

        $payload = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
        ];
        if (Schema::hasColumn('users','is_active')) $payload['is_active'] = false;
        foreach (['username','role','phone','wa_number'] as $c) {
            if (isset($data[$c])) $payload[$c] = $data[$c];
        }

        $User = $this->userModel();
        $user = $User::create($payload);

        // Jika pakai Spatie Permission & ada role
        if (method_exists($user,'assignRole') && !empty($data['role'] ?? null)) {
            $user->assignRole($data['role']);
        }

        return redirect()->route('staff.users.index')->with('ok','User dibuat. (Kirim OTP aktivasi jika perlu).');
    }
}

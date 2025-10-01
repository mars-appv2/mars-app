<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) return redirect()->route('staff.dashboard');
        return view('staff.auth.login');
    }

    public function login(Request $request)
    {
    	$request->validate([
            'identity' => 'required|string',
            'password' => 'required|string',
    	]);

    	$id = $request->input('identity');
    	$pw = $request->input('password');
    	$remember = $request->boolean('remember');

    	// Cek kolom yang tersedia agar tidak query ke kolom yang tidak ada
    	$cols = \Illuminate\Support\Facades\Schema::getColumnListing('users');

    	$attempts = [];

    	// 1) Jika input format email dan kolom email ada → coba via email
    	if (filter_var($id, FILTER_VALIDATE_EMAIL) && in_array('email', $cols)) {
            $attempts[] = ['email' => $id, 'password' => $pw];
    	}

    	// 2) Jika BUKAN email & kolom username ada → coba via username
    	if (!filter_var($id, FILTER_VALIDATE_EMAIL) && in_array('username', $cols)) {
            $attempts[] = ['username' => $id, 'password' => $pw];
    	}

    	// 3) Fallback terakhir (misal user memasukkan email tapi formatnya aneh):
    	if (empty($attempts) && in_array('email', $cols)) {
            $attempts[] = ['email' => $id, 'password' => $pw];
    	}

    	foreach ($attempts as $cred) {
            if (\Illuminate\Support\Facades\Auth::attempt($cred, $remember)) {
            	$request->session()->regenerate();
            	return redirect()->intended(route('staff.dashboard'));
            }
    	}

    	return back()
            ->withErrors(['identity' => 'Kredensial tidak cocok'])
            ->withInput($request->only('identity'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('staff.login');
    }
}

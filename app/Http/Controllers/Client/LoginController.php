<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        // 👉 jika sudah login, kirim ke dashboard
        if (Auth::check()) {
            return redirect()->route('client.dashboard'); // was client.home
        }
        return view('client.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'identity' => 'required|string',
            'password' => 'required|string',
        ]);

        $identity = $request->input('identity');
        $password = $request->input('password');
        $remember = $request->boolean('remember');

        $attempted = false;
        if (filter_var($identity, FILTER_VALIDATE_EMAIL)) {
            $attempted = Auth::attempt(['email' => $identity, 'password' => $password], $remember);
        }
        if (!$attempted) {
            $attempted = Auth::attempt(['username' => $identity, 'password' => $password], $remember);
        }

        if ($attempted) {
            $request->session()->regenerate();
            // 👉 arahkan ke dashboard
            return redirect()->intended(route('client.dashboard')); // was client.home
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
        return redirect()->route('client.login');
    }
}

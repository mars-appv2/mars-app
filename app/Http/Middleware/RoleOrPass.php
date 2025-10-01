<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleOrPass
{
    public function handle(Request $request, Closure $next, $roles = '')
    {
        $user = $request->user();
        if (!$user) return redirect()->route('staff.login');

        $allowed = array_values(array_filter(explode('|', (string)$roles)));

        // Kalau tidak minta role spesifik, lanjut saja (cukup auth)
        if (empty($allowed)) return $next($request);

        // 1) Jika pakai Spatie\Permission (HasRoles)
        if (method_exists($user, 'hasAnyRole')) {
            if ($user->hasAnyRole($allowed)) return $next($request);
            abort(403, 'Peran tidak sesuai.');
        }

        // 2) Jika ada kolom tunggal "role" di tabel users
        if (isset($user->role) && in_array(strtolower($user->role), array_map('strtolower', $allowed), true)) {
            return $next($request);
        }

        // 3) Jika ada relasi roles() manual (pivot)
        if (method_exists($user, 'roles')) {
            $exists = $user->roles()->whereIn('name', $allowed)->exists();
            if ($exists) return $next($request);
        }

        // Default: tolak
        abort(403, 'Peran tidak sesuai.');
    }
}

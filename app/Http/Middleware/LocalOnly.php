<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocalOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        // Izinkan hanya koneksi dari loopback / unix socket reverse proxy
        $ip = $request->ip();
        if (
            $ip !== '127.0.0.1' &&
            $ip !== '::1' &&
            // jika melewati proxy lokal, perbolehkan X-Forwarded-For 127.0.0.1
            !in_array('127.0.0.1', array_map('trim', explode(',', (string) $request->header('X-Forwarded-For'))), true)
        ) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

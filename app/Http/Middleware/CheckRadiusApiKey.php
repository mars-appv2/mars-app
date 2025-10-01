<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRadiusApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $given = $request->header('X-Api-Key') ?: $request->query('api_key');
        $expected = config('services.radius.api_key');

        if (!$expected || !hash_equals($expected, (string)$given)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}

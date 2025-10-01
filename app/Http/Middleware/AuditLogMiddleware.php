<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if  (Auth::check() && stripos($request->method(), 'get') === false) {
            $route = $request->route();
            $actionName = optional($route->getAction())['controller'] ?? null;
            $user = Auth::user();

            AuditLogService::log([
                'user_id'     => $user->id,
                'user_name'   => $user->name,
                'user_email'  => $user->email,
                'action'      => $request->method(),
                'method'      => $request->method(),
                'target_type' => class_basename($actionName),
                'target_key'  => null, // Diisi nanti jika perlu
                'status'      => $response->status() === 200 ? 'ok' : 'error',
                'ip'          => $request->ip(),
                'route'       => $route->getName() ?? '',
                'path'        => $request->path(),
                'message'     => $response->exception ? $response->exception->getMessage() : null,
                'data'        => $request->except(['password', '_token']),
            ]);
        }

        return $response;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query();

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('user_name', 'like', '%'.$request->q.'%')
                  ->orWhere('user_email', 'like', '%'.$request->q.'%')
                  ->orWhere('action', 'like', '%'.$request->q.'%')
                  ->orWhere('target_type', 'like', '%'.$request->q.'%')
                  ->orWhere('target_key', 'like', '%'.$request->q.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->latest()->paginate(20);

        $users = AuditLog::whereNotNull('user_id')
            ->select('user_id', 'user_name', 'user_email')
            ->groupBy('user_id', 'user_name', 'user_email')
            ->orderBy('user_name')
            ->get();

        return view('logs.index', [
            'logs' => $logs,
            'q' => $request->q,
            'status' => $request->status,
            'userId' => $request->user_id,
            'from' => $request->from,
            'to' => $request->to,
            'users' => $users,
        ]);
    }
}

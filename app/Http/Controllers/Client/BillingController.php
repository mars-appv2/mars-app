<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $invoices = [];

        if (!Schema::hasTable('invoices')) {
            return view('client.invoices', compact('invoices'));
        }

        try {
            $cols = Schema::getColumnListing('invoices');
            $q = DB::table('invoices');
            $bound = false;

            // Prefer ID-based mapping first
            foreach (['user_id','customer_id','account_id','client_id','owner_id','userid','userId'] as $c) {
                if (in_array($c, $cols)) {
                    $q->where($c, $user->id);
                    $bound = true; break;
                }
            }

            // Fall back to identity columns (no PII leak; strict match only)
            if (!$bound) {
                $identity = $user->username ?? null;

                if ($identity && in_array('username', $cols)) {
                    $q->where('username', $identity);
                    $bound = true;
                } elseif (in_array('email', $cols)) {
                    $q->where('email', $user->email);
                    $bound = true;
                } elseif (in_array('payer_email', $cols)) {
                    $q->where('payer_email', $user->email);
                    $bound = true;
                } elseif ($identity && in_array('customer_code', $cols)) {
                    // jika ada kode pelanggan yang sama dengan username
                    $q->where('customer_code', $identity);
                    $bound = true;
                }
            }

            // Jika tetap belum bisa di-bind, jangan tampilkan invoice random milik orang lain
            if (!$bound) {
                $invoices = [];
                return view('client.invoices', compact('invoices'));
            }

            // Sorting terbaik yang tersedia
            if (in_array('created_at', $cols)) {
                $q->orderByDesc('created_at');
            } elseif (in_array('date', $cols)) {
                $q->orderByDesc('date');
            } elseif (in_array('id', $cols)) {
                $q->orderByDesc('id');
            }

            $invoices = $q->limit(50)->get();
        } catch (\Throwable $e) {
            $invoices = [];
        }

        return view('client.invoices', compact('invoices'));
    }
}

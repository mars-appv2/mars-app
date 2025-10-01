<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::orderBy('code')->get();
        $parents  = Account::orderBy('code')->get();
        return view('finance.accounts.index', compact('accounts','parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50|unique:accounts,code',
            'name'      => 'required|string|max:150',
            'type'      => 'required|in:1,2,3,4,5',
            'parent_id' => 'nullable|exists:accounts,id',
            'is_cash'   => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_cash']   = (bool)($data['is_cash'] ?? false);
        $data['is_active'] = (bool)($data['is_active'] ?? true);
        Account::create($data);
        return back()->with('ok','Akun ditambahkan');
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:50|unique:accounts,code,'.$account->id,
            'name'      => 'required|string|max:150',
            'type'      => 'required|in:1,2,3,4,5',
            'parent_id' => 'nullable|exists:accounts,id',
            'is_cash'   => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_cash']   = (bool)($data['is_cash'] ?? false);
        $data['is_active'] = (bool)($data['is_active'] ?? true);
        $account->update($data);
        return back()->with('ok','Akun diupdate');
    }

    public function destroy(Account $account)
    {
        DB::transaction(function() use ($account) {
            // Cegah hapus jika masih punya transaksi
            if ($account->lines()->exists()) {
                abort(422, 'Akun memiliki transaksi, tidak dapat dihapus.');
            }
            $account->delete();
        });
        return back()->with('ok','Akun dihapus');
    }
}

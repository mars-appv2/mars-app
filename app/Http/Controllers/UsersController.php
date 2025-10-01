<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    // TIDAK ADA __construct() di sini — supaya tidak ada middleware permission yg menghalangi

    // Daftar user
    public function index()
    {
        $users = User::with('roles')->orderBy('name')->paginate(15);
        return view('users.index', compact('users'));
    }

    // Form tambah user
    public function create()
    {
        // Ambil daftar role dari DB (Spatie)
        $roles = Role::orderBy('name')->pluck('name')->all();
        return view('users.create', compact('roles'));
    }

    // Simpan user
    public function store(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255','unique:users,email'],
            'password' => ['nullable','string','min:6'],
            'roles'    => ['array'],
            'roles.*'  => ['string'],
        ]);

        $password = $data['password'] ?: Str::random(10);

        $u = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($password),
        ]);

        $allowed = Role::pluck('name')->all();
        $roles   = array_values(array_intersect($allowed, $data['roles'] ?? []));
        if (!empty($roles)) {
            $u->syncRoles($roles);
        }

        return redirect()->route('users.index')->with('ok','User berhasil dibuat.');
    }

    // ===== Edit form =====
    public function edit(User $user)
    {
    	if (!auth()->check() || !auth()->user()->hasRole('admin')) {
            abort(403, 'Admins only');
    	}

    	$roles = \Spatie\Permission\Models\Role::orderBy('name')->pluck('name')->all();
    	return view('users.edit', compact('user','roles'));
    }

    // ===== Update =====
    public function update(Request $r, User $user)
    {
    	if (!auth()->check() || !auth()->user()->hasRole('admin')) {
            abort(403, 'Admins only');
    	}

    	$data = $r->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'password' => ['nullable','string','min:6'],
            'roles'    => ['array'],
            'roles.*'  => ['string'],
    	]);

    	// tanpa mass-assignment
    	$user->name  = $data['name'];
    	$user->email = $data['email'];
    	if (!empty($data['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($data['password']);
    	}
    	$user->save();

    	// sync role valid
    	$allowed = \Spatie\Permission\Models\Role::pluck('name')->all();
    	$roles   = array_values(array_intersect($allowed, $data['roles'] ?? []));
    	$user->syncRoles($roles);

    	return redirect()->route('users.index')->with('ok','User diperbarui.');
    }

    // ===== Hapus =====
    public function destroy(User $user)
    {
    	if (!auth()->check() || !auth()->user()->hasRole('admin')) {
            abort(403, 'Admins only');
    	}

    	if (auth()->id() === $user->id) {
            return back()->withErrors('Tidak bisa menghapus akun sendiri.');
    	}

    	$user->delete();
    	return redirect()->route('users.index')->with('ok','User dihapus.');
    }


}

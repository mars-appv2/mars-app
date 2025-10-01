<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('id','desc')->get();
        return view('users.index', [
            'users' => $users,
            'roles' => Role::pluck('name')->all(),
        ]);
    }

    public function create()
    {
        $roles = Role::pluck('name')->all();
        return view('users.create', compact('roles'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'     => ['required','string','max:120'],
            'email'    => ['required','email','max:190','unique:users,email'],
            'password' => ['required','string','min:6'],
            'role'     => ['required', Rule::in(Role::pluck('name')->all())],
        ]);

        $u = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $u->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('ok','User dibuat.');
    }

    public function editRoles(User $user)
    {
        $roles = Role::pluck('name')->all();
        return view('users.roles', compact('user','roles'));
    }

    public function updateRoles(User $user, Request $r)
    {
        $data = $r->validate([
            'roles' => ['array','min:1'],
            'roles.*' => [Rule::in(Role::pluck('name')->all())],
        ]);
        $user->syncRoles($data['roles'] ?? []);
        return back()->with('ok','Role diperbarui.');
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('err','Tidak bisa menghapus diri sendiri.');
        }
        $user->delete();
        return back()->with('ok','User dihapus.');
    }
}

<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Mikrotik;

class MikrotikPolicy
{
    public function viewAny(User $user): bool
    {
        // semua user yang bisa login boleh melihat daftar (nanti tetap disaring by owner)
        return true;
    }

    public function view(User $user, Mikrotik $m): bool
    {
        if (method_exists($user,'hasRole') && $user->hasRole('admin')) return true;
        return (int)$m->owner_id === (int)$user->id;
    }

    public function create(User $user): bool
    {
        return true; // kalau mau batasi, cek permission di sini
    }

    public function update(User $user, Mikrotik $m): bool
    {
        if (method_exists($user,'hasRole') && $user->hasRole('admin')) return true;
        return (int)$m->owner_id === (int)$user->id;
    }

    public function delete(User $user, Mikrotik $m): bool
    {
        if (method_exists($user,'hasRole') && $user->hasRole('admin')) return true;
        return (int)$m->owner_id === (int)$user->id;
    }
}

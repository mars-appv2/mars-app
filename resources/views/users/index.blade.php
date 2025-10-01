@extends('layouts.app')
@section('title','Kelola User')

@section('content')
<div class="container mx-auto px-3 md:px-4 text-slate-200">
  @if(session('ok'))
    <div class="mb-4 rounded-xl bg-emerald-900/40 border border-emerald-700 px-4 py-3">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="mb-4 rounded-xl bg-rose-900/40 border border-rose-700 px-4 py-3">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Kelola User</h1>
    <a href="{{ route('users.create') }}" class="m-btn m-btnp">Tambah User</a>
  </div>

  <div class="m-card p-4 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="text-slate-400">
        <tr>
          <th class="py-2 text-left">Nama</th>
          <th class="py-2 text-left">Email</th>
          <th class="py-2 text-left">Roles</th>
          <th class="py-2 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800">
        @forelse($users as $u)
          <tr>
            <td class="py-3">{{ $u->name }}</td>
            <td class="py-3">{{ $u->email }}</td>
            <td class="py-3">{{ $u->roles->pluck('name')->join(', ') ?: '-' }}</td>
            <td class="py-3 text-right">
              <a href="{{ route('users.edit',$u) }}" class="m-btn m-btnp">Edit</a>
              @if(auth()->id() !== $u->id)
                <form method="POST" action="{{ route('users.destroy',$u) }}" class="inline"
                      onsubmit="return confirm('Hapus user ini? Tindakan tidak bisa dibatalkan.');">
                  @csrf @method('DELETE')
                  <button class="m-btn">Hapus</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td class="py-4 text-slate-400" colspan="4">Belum ada user.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="mt-4">{{ $users->links() }}</div>
  </div>
</div>
@endsection

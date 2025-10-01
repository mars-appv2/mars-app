@extends('layouts.app')

@section('content')
<div class="container mx-auto px-3 md:px-4">
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-100">Role User</h1>
    <a href="{{ route('users.create') }}" class="m-btn">Tambah User</a>
  </div>

  @if(session('ok'))
    <div class="mb-3 rounded-xl bg-emerald-900/40 border border-emerald-700 px-4 py-3">
      {{ session('ok') }}
    </div>
  @endif

  @if($errors->any())
    <div class="mb-3 rounded-xl bg-rose-900/40 border border-rose-700 px-4 py-3">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Info jika belum ada role di DB --}}
  @if(empty($roles) || count($roles) === 0)
    <div class="mb-3 rounded-xl bg-amber-900/40 border border-amber-700 px-4 py-3 text-amber-100">
      Belum ada data <em>role</em>. Tambahkan role di /settings/roles (admin) atau jalankan seeder Spatie.
    </div>
  @endif

  <div class="m-card p-4 space-y-3">
    @forelse($users as $u)
      <form method="POST"
            action="{{ route('users.roles.update') }}"
            class="flex items-center justify-between gap-4 border-b border-slate-800 pb-3 last:border-0 last:pb-0"
            onsubmit="this.querySelector('button[type=submit]')?.setAttribute('disabled','disabled')">
        @csrf
        <input type="hidden" name="user_id" value="{{ $u->id }}">

        <div class="min-w-0 text-slate-200">
          <div class="font-medium truncate">{{ $u->name }}</div>
          <div class="small text-slate-400 truncate">{{ $u->email }}</div>
        </div>

        <div class="flex flex-wrap items-center gap-4 md:gap-6">
          @foreach($roles as $r)
            <label class="inline-flex items-center gap-2 select-none">
              <input
                type="checkbox"
                name="roles[]"
                value="{{ $r }}"
                class="rounded bg-slate-800 border-slate-700"
                {{ $u->hasRole($r) ? 'checked' : '' }}>
              <span class="capitalize">{{ $r }}</span>
            </label>
          @endforeach

          <button class="m-btn m-btnp" type="submit">Simpan</button>
        </div>
      </form>
    @empty
      <div class="text-slate-400">Belum ada user.</div>
    @endforelse
  </div>

  {{-- Tampilkan pagination jika $users adalah paginator --}}
  @if(method_exists($users, 'links'))
    <div class="mt-4">{{ $users->links() }}</div>
  @endif
</div>
@endsection

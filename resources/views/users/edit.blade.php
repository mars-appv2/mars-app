@extends('layouts.app')
@section('title','Edit User')

@section('content')
<div class="container mx-auto px-3 md:px-4">
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-100">Edit User</h1>
    <div class="flex gap-2">
      <a href="{{ route('users.index') }}" class="m-btn">Kelola User</a>
      <a href="{{ route('settings.roles') }}" class="m-btn">Kelola Role</a>
    </div>
  </div>

  @if(session('ok'))
    <div class="mb-3 rounded-xl bg-emerald-900/40 border border-emerald-700 px-4 py-3 text-emerald-100">
      {{ session('ok') }}
    </div>
  @endif
  @if($errors->any())
    <div class="mb-3 rounded-xl bg-rose-900/40 border border-rose-700 px-4 py-3 text-rose-100">
      <ul class="mb-0 list-disc list-inside">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <div class="m-card p-5 max-w-3xl space-y-5">
    <form method="POST" action="{{ route('users.update',$user) }}" class="space-y-5"
          onsubmit="this.querySelector('button[type=submit]')?.setAttribute('disabled','disabled')">
      @csrf
      @method('PUT')

      <div class="grid md:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm text-slate-300 mb-1">Nama</label>
          <input name="name" value="{{ old('name',$user->name) }}" required
                 class="m-inp w-full rounded-xl px-3 py-2 bg-slate-900/70 border border-slate-700/70 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Email</label>
          <input type="email" name="email" value="{{ old('email',$user->email) }}" required
                 class="m-inp w-full rounded-xl px-3 py-2 bg-slate-900/70 border border-slate-700/70 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div class="md:col-span-2 md:max-w-md">
          <label class="block text-sm text-slate-300 mb-1">Password (opsional)</label>
          <input type="password" name="password"
                 class="m-inp w-full rounded-xl px-3 py-2 bg-slate-900/70 border border-slate-700/70 text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 placeholder="Kosongkan jika tidak diganti">
        </div>
      </div>

      <div>
        <label class="block text-sm text-slate-300 mb-2">Assign Role</label>
        <div class="flex flex-wrap gap-x-6 gap-y-3">
          @foreach($roles as $r)
            <label class="inline-flex items-center gap-2 select-none text-slate-200">
              <input type="checkbox" name="roles[]" value="{{ $r }}"
                     class="rounded bg-slate-800 border-slate-700 accent-indigo-500"
                     {{ $user->hasRole($r) ? 'checked' : '' }}>
              <span class="capitalize">{{ $r }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <div class="pt-1 flex gap-2">
        <button class="m-btn m-btnp" type="submit">Simpan</button>
        <a href="{{ route('users.index') }}" class="m-btn">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

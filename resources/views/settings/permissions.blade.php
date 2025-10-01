@extends('layouts.app')
@section('title','Kelola Akses Menu')

@section('content')
@if(session('ok'))
  <div class="m-card p-3 mb-4 text-green-300">{{ session('ok') }}</div>
@endif
@if(session('err'))
  <div class="m-card p-3 mb-4 text-red-300">{{ session('err') }}</div>
@endif

<div class="m-card p-5">
  <div class="text-lg font-semibold text-slate-200 mb-2">Kelola Akses Menu (Role → Permission)</div>
  <div class="text-xs text-[var(--muted)] mb-4">
    Centang permission yang boleh diakses tiap role. Halaman ini hanya mengatur permission yang dipakai sidebar/menu:
    <code>view dashboard</code>, <code>manage mikrotik</code>, <code>view traffic</code>, <code>manage radius</code>, <code>manage billing</code>, <code>manage settings</code>.
  </div>

  <form method="POST" action="{{ route('settings.permissions.save') }}">
    @csrf

    <div class="overflow-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-[var(--muted)]">
            <th class="py-2 pr-4">Permission</th>
            @foreach($roles as $role)
              <th class="py-2 px-3">{{ $role->name }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($groups as $grp)
            <tr>
              <td colspan="{{ 1 + $roles->count() }}" class="pt-5 pb-2 text-xs tracking-widest text-[var(--muted)]">
                {{ strtoupper($grp['group']) }}
              </td>
            </tr>
            @foreach($grp['items'] as $it)
              <tr class="border-t border-[var(--line)]">
                <td class="py-2 pr-4 text-slate-200">{{ $it['label'] }}
                  <div class="text-[10px] text-[var(--muted)]">{{ $it['name'] }}</div>
                </td>
                @foreach($roles as $role)
                  <td class="py-2 px-3">
                    <label class="inline-flex items-center gap-2">
                      <input type="checkbox" class="rounded"
                             name="perm[{{ $it['name'] }}][{{ $role->id }}]"
                             {{ !empty($matrix[$role->id][$it['name']]) ? 'checked' : '' }}>
                      <span class="text-xs text-[var(--muted)]">allow</span>
                    </label>
                  </td>
                @endforeach
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-4 flex justify-end">
      <button class="m-btn m-btnp">Simpan Permissions</button>
    </div>
  </form>
</div>
@endsection

@extends('layouts.app')
@section('content')
<div class="container mx-auto px-2 md:px-4 max-w-6xl">

  <div class="m-card p-5 mt-6 edit-mktk">
    <form method="POST" action="{{ route('mikrotik.update', $mikrotik) }}" class="grid gap-4">
      @csrf
      @method('PUT')

      {{-- Header + toolbar --}}
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <div class="text-lg font-semibold text-slate-200">Edit MikroTik</div>
          <div class="text-slate-400 text-xs mt-1">
            {{ $mikrotik->name }} • {{ $mikrotik->host }}:{{ $mikrotik->port ?? 8728 }}
            @if($mikrotik->updated_at)
              <span class="ml-2">Updated {{ $mikrotik->updated_at }}</span>
            @endif
          </div>
        </div>

        <div class="flex items-center gap-3">
          {{-- Switch Aktifkan RADIUS --}}
          <label class="switch">
            <input type="checkbox" name="radius_enabled" value="1"
                   {{ old('radius_enabled', $mikrotik->radius_enabled) ? 'checked' : '' }}>
            <span class="slider"></span>
            <span class="lab">Aktifkan RADIUS</span>
          </label>

          {{-- Tombol kembali --}}
          <a href="{{ route('mikrotik.index') }}" class="m-btn inline-flex items-center gap-2">
            {{-- icon arrow-left --}}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="opacity-90">
              <path d="M10 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M3 12h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>Kembali</span>
          </a>

          {{-- Tombol simpan --}}
          <button type="submit" class="m-btn m-btnp inline-flex items-center gap-2">
            {{-- icon check --}}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="opacity-90">
              <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Simpan</span>
          </button>
        </div>
      </div>

      {{-- Fields: 5 kolom lurus --}}
      <div class="grid md:grid-cols-5 gap-3">
        <div>
          <label class="m-lab">Nama</label>
          <input name="name" value="{{ old('name',$mikrotik->name) }}" class="m-inp" required>
        </div>

        <div>
          <label class="m-lab">Host / IP</label>
          <input name="host" value="{{ old('host',$mikrotik->host) }}" class="m-inp" required>
        </div>

        <div>
          <label class="m-lab">Port API</label>
          <input type="number" name="port" value="{{ old('port',$mikrotik->port ?? 8728) }}" class="m-inp">
        </div>

        <div>
          <label class="m-lab">Username</label>
          <input name="username" value="{{ old('username',$mikrotik->username) }}" class="m-inp" required>
        </div>

        <div>
          <label class="m-lab">Password</label>
          <input name="password" value="{{ old('password',$mikrotik->password) }}" class="m-inp" required>
        </div>
      </div>
    </form>
  </div>

  {{-- Info Status RADIUS --}}
  <div class="m-card p-4 md:p-5 mt-4 edit-mktk">
    <div class="flex items-center justify-between mb-2">
      <div class="text-slate-200 font-medium">Status RADIUS</div>
      <span class="badge"
        style="border-color:{{ $mikrotik->radius_enabled ? '#14532d' : '#7f1d1d' }};
               background:{{ $mikrotik->radius_enabled ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)' }};
               color:{{ $mikrotik->radius_enabled ? '#bbf7d0' : '#fecaca' }};">
        {{ $mikrotik->radius_enabled ? 'ENABLED' : 'DISABLED' }}
      </span>
    </div>
    <div class="text-xs text-slate-400">
      Saat dicentang, perangkat akan diprovision ke FreeRADIUS menggunakan secret tersimpan.
    </div>
  </div>

</div>

{{-- SCOPED STYLES: hanya halaman ini --}}
<style>
  /* Label + input gelap agar kontras */
  .edit-mktk .m-lab{
    display:block;margin-bottom:.25rem;font-size:.875rem;line-height:1rem;color:#cbd5e1;
  }
  .edit-mktk .m-inp{
    width:100%;
    background:#0c101c;
    border:1px solid #263042;
    color:#e5e7eb;
    border-radius:.6rem;
    padding:.65rem .85rem;
    outline:none;
  }
  .edit-mktk .m-inp::placeholder{ color:#94a3b8; opacity:.85; }
  .edit-mktk .m-inp:focus{
    border-color:#3b82f6;
    box-shadow:0 0 0 2px rgba(59,130,246,.25);
  }

  /* Switch RADIUS */
  .edit-mktk .switch{display:inline-flex;align-items:center;gap:.5rem}
  .edit-mktk .switch .lab{color:#cbd5e1;font-size:.9rem}
  .edit-mktk .switch input{display:none}
  .edit-mktk .switch .slider{
    width:42px;height:22px;border-radius:9999px;background:#0f172a;border:1px solid #334155;
    position:relative;transition:.2s;
  }
  .edit-mktk .switch .slider::after{
    content:'';position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:9999px;
    background:#cbd5e1;transition:transform .2s, background .2s;
  }
  .edit-mktk .switch input:checked + .slider{background:#1d4ed8;border-color:#3b82f6}
  .edit-mktk .switch input:checked + .slider::after{transform:translateX(20px);background:#fff}
</style>
@endsection

@extends('client.layouts.app')
@section('title','WiFi / SSID — Client')

@section('content')
<div class="card">
  <div style="font-weight:600;margin-bottom:10px">Ganti SSID WiFi</div>

  @if(session('ok'))
    <div class="badge ok" style="display:block;margin-bottom:10px">{{ session('ok') }}</div>
  @endif

  <form method="POST" action="{{ route('client.wifi.update') }}" class="grid g2">
    @csrf
    <div>
      <label class="stat-label">SSID</label>
      <input class="input" name="ssid" value="{{ old('ssid') }}" placeholder="Mars-Home" required maxlength="32">
      @error('ssid')<div class="badge bad" style="margin-top:8px">{{ $message }}</div>@enderror
    </div>
    <div>
      <label class="stat-label">Password</label>
      <input class="input" name="password" type="text" value="{{ old('password') }}" placeholder="Minimal 8 karakter" required minlength="8" maxlength="64">
      @error('password')<div class="badge bad" style="margin-top:8px">{{ $message }}</div>@enderror
    </div>
    <div>
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>

  <div class="stat-label" style="margin-top:10px">Perubahan akan dieksekusi oleh sistem. Jika kamu punya integrasi Mikrotik API, hubungkan aksi ini ke router.</div>
</div>
@endsection

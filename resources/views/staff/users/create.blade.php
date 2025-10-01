@extends('staff.layouts.app')
@section('title','Tambah User — Staff')

@section('content')
<div class="card" style="max-width:720px;margin:0 auto">
  <div style="font-weight:600;margin-bottom:8px">Tambah User</div>

  @if(session('ok')) <div class="badge ok" style="display:block;margin-bottom:10px">{{ session('ok') }}</div> @endif

  <form method="POST" action="{{ route('staff.users.store') }}" class="grid g2">
    @csrf
    <div>
      <label class="stat-label">Nama</label>
      <input class="input" name="name" value="{{ old('name') }}" required>
      @error('name')<div class="badge bad" style="margin-top:8px">{{ $message }}</div>@enderror
    </div>
    <div>
      <label class="stat-label">Email</label>
      <input class="input" type="email" name="email" value="{{ old('email') }}" required>
      @error('email')<div class="badge bad" style="margin-top:8px">{{ $message }}</div>@enderror
    </div>

    @if(in_array('username', \Illuminate\Support\Facades\Schema::getColumnListing('users')))
    <div>
      <label class="stat-label">Username (opsional)</label>
      <input class="input" name="username" value="{{ old('username') }}">
      @error('username')<div class="badge bad" style="margin-top:8px">{{ $message }}</div>@enderror
    </div>
    @endif

    @if(in_array('phone', \Illuminate\Support\Facades\Schema::getColumnListing('users')))
    <div>
      <label class="stat-label">No. HP</label>
      <input class="input" name="phone" value="{{ old('phone') }}" placeholder="62xxxx">
    </div>
    @endif

    @if(in_array('wa_number', \Illuminate\Support\Facades\Schema::getColumnListing('users')))
    <div>
      <label class="stat-label">No. WhatsApp</label>
      <input class="input" name="wa_number" value="{{ old('wa_number') }}" placeholder="62xxxx">
    </div>
    @endif

    @if(in_array('role', \Illuminate\Support\Facades\Schema::getColumnListing('users')))
    <div>
      <label class="stat-label">Role</label>
      <select class="input" name="role">
        <option value="">(pilih)</option>
        <option value="operator">operator</option>
        <option value="noc">noc</option>
        <option value="teknisi">teknisi</option>
        <option value="billing">billing</option>
        <option value="staff">staff</option>
      </select>
    </div>
    @endif

    <div>
      <label class="stat-label">Password</label>
      <input class="input" type="text" name="password" placeholder="min 8 karakter" required>
      @error('password')<div class="badge bad" style="margin-top:8px">{{ $message }}</div>@enderror
    </div>

    <div style="grid-column:1/-1">
      <button class="btn" type="submit">Simpan User</button>
    </div>
  </form>
</div>
@endsection

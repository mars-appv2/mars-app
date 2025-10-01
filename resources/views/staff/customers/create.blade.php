@extends('staff.layouts.app')
@section('title','Tambah Pelanggan — Staff')

@section('content')
<div class="card" style="max-width:920px;margin:0 auto">
  <div style="font-weight:600;margin-bottom:8px">Tambah Pelanggan</div>
  @if(session('err'))<div class="badge bad" style="display:block;margin-bottom:8px">{{ session('err') }}</div>@endif

  <form method="POST" action="{{ route('staff.customers.store') }}" class="grid g2">
    @csrf
    <div>
      <label class="stat-label">Nama</label>
      <input class="input" name="name" value="{{ old('name') }}" required>
      @error('name')<div class="badge bad" style="margin-top:6px">{{ $message }}</div>@enderror
    </div>
    <div>
      <label class="stat-label">Username (PPPoE/Hotspot)</label>
      <input class="input" name="username" value="{{ old('username') }}" required>
      @error('username')<div class="badge bad" style="margin-top:6px">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="stat-label">Password Layanan</label>
      <input class="input" name="password" type="text" value="{{ old('password') }}" placeholder="min 6 karakter" required>
      @error('password')<div class="badge bad" style="margin-top:6px">{{ $message }}</div>@enderror
    </div>
    <div>
      <label class="stat-label">Email (opsional)</label>
      <input class="input" type="email" name="email" value="{{ old('email') }}">
      @error('email')<div class="badge bad" style="margin-top:6px">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="stat-label">No. HP (opsional)</label>
      <input class="input" name="phone" value="{{ old('phone') }}">
    </div>
    <div>
      <label class="stat-label">Alamat (opsional)</label>
      <input class="input" name="address" value="{{ old('address') }}">
    </div>

    <div>
      <label class="stat-label">Router (MikroTik)</label>
      <select class="input" name="mikrotik_id" required>
        <option value="">Pilih Router</option>
        @foreach($routers as $rt)
          <option value="{{ $rt->id }}" {{ old('mikrotik_id')==$rt->id?'selected':'' }}>
            {{ $rt->name ?? $rt->identity ?? ('Router #'.$rt->id) }}
          </option>
        @endforeach
      </select>
      @error('mikrotik_id')<div class="badge bad" style="margin-top:6px">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="stat-label">Service Type</label>
      <select class="input" name="service_type" required>
        <option value="pppoe" {{ old('service_type')==='pppoe'?'selected':'' }}>PPPoE</option>
        <option value="hotspot" {{ old('service_type')==='hotspot'?'selected':'' }}>Hotspot</option>
        <option value="other" {{ old('service_type')==='other'?'selected':'' }}>Other</option>
      </select>
      @error('service_type')<div class="badge bad" style="margin-top:6px">{{ $message }}</div>@enderror
    </div>

    <div>
      <label class="stat-label">Router Profile (opsional)</label>
      <input class="input" name="router_profile" value="{{ old('router_profile') }}" placeholder="mis. default, 20M, dll">
    </div>
    <div>
      <label class="stat-label">VLAN ID (opsional)</label>
      <input class="input" name="vlan_id" type="number" min="1" max="4094" value="{{ old('vlan_id') }}">
    </div>

    <div>
      <label class="stat-label">IP Address (opsional)</label>
      <input class="input" name="ip_address" value="{{ old('ip_address') }}" placeholder="mis. static IP">
    </div>

    <div>
      <label class="stat-label">Paket/Plan (opsional)</label>
      <select class="input" name="plan_id">
        <option value="">(tanpa plan)</option>
        @foreach($plans as $p)
          <option value="{{ $p->id }}" {{ old('plan_id')==$p->id?'selected':'' }}>
            {{ $p->name ?? ('Plan #'.$p->id) }}
          </option>
        @endforeach
      </select>
    </div>

    <div style="grid-column:1/-1">
      <label class="stat-label">Provisioning ke</label>
      <div class="grid g3">
        <label class="stat-label"><input type="radio" name="provision_to" value="radius" {{ old('provision_to','radius')==='radius'?'checked':'' }}> FreeRADIUS</label>
        <label class="stat-label"><input type="radio" name="provision_to" value="mikrotik" {{ old('provision_to')==='mikrotik'?'checked':'' }}> MikroTik Local Secret</label>
        <label class="stat-label"><input type="radio" name="provision_to" value="none" {{ old('provision_to')==='none'?'checked':'' }}> Simpan saja</label>
      </div>
    </div>

    <div style="grid-column:1/-1">
      <label class="stat-label">Catatan (opsional)</label>
      <input class="input" name="note" value="{{ old('note') }}">
    </div>

    <div style="grid-column:1/-1">
      <button class="btn" type="submit">Simpan Pelanggan</button>
    </div>
  </form>
</div>
@endsection

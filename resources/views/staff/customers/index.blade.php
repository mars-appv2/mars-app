@extends('staff.layouts.app')
@section('title','Customers — Staff')

@section('content')
<div class="card">
  <div style="display:flex;gap:10px;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap">
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input class="input" name="q" value="{{ $q }}" placeholder="Cari username/nama/email/phone" style="min-width:240px">
      <select class="input" name="router" style="min-width:180px">
        <option value="">Semua Router</option>
        @foreach($routers as $rt)
          <option value="{{ $rt->id }}" {{ (string)$routerFilter===(string)$rt->id?'selected':'' }}>
            {{ $rt->name ?? $rt->identity ?? ('Router #'.$rt->id) }}
          </option>
        @endforeach
      </select>
      <button class="btn" type="submit">Filter</button>
    </form>
    @if(\Illuminate\Support\Facades\Route::has('staff.customers.create'))
      <a class="btn" href="{{ route('staff.customers.create') }}">Tambah Pelanggan</a>
    @endif
  </div>

  @if(session('ok'))<div class="badge ok" style="display:inline-block;margin-bottom:8px">{{ session('ok') }}</div>@endif
  @if(session('err'))<div class="badge bad" style="display:inline-block;margin-bottom:8px">{{ session('err') }}</div>@endif

  @php
    $u = auth()->user();
    $canAccept = false;
    if ($u) {
      if (method_exists($u,'hasAnyRole'))        $canAccept = $u->hasAnyRole(['teknisi','operator','admin']);
      elseif (isset($u->role))                   $canAccept = in_array(strtolower($u->role),['teknisi','operator','admin']);
      elseif (method_exists($u,'roles'))         $canAccept = $u->roles()->whereIn('name',['teknisi','operator','admin'])->exists();
    }
    $hasProvision = \Illuminate\Support\Facades\Schema::hasColumn('customers','provision_status');
    $hasActiveCol = \Illuminate\Support\Facades\Schema::hasColumn('customers','is_active');
  @endphp

  @if($customers->isEmpty())
    <div class="stat-label">Belum ada data pelanggan.</div>
  @else
    <table class="table">
      <thead>
        <tr>
          <th>ID</th><th>Username</th><th>Nama</th><th>Router</th><th>Layanan</th><th>IP/VLAN</th><th>Status</th>
          @if($canAccept)<th>Aksi</th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach($customers as $c)
        @php
          $statusHtml = '<span class="badge">-</span>';
          if ($hasProvision) {
              $ps = strtolower($c->provision_status ?? 'pending');
              if ($ps === 'accepted')     $statusHtml = '<span class="badge ok">Aktif</span>';
              elseif ($ps === 'rejected') $statusHtml = '<span class="badge bad">Ditolak</span>';
              else                        $statusHtml = '<span class="badge">Pending</span>';
          } elseif ($hasActiveCol) {
              $statusHtml = ($c->is_active ?? 0) ? '<span class="badge ok">Aktif</span>' : '<span class="badge">Pending</span>';
          }
        @endphp
        <tr>
          <td>{{ $c->id }}</td>
          <td>{{ $c->username ?? '-' }}</td>
          <td>{{ $c->name ?? '-' }}</td>
          <td>{{ $c->mikrotik_id ?? '-' }}</td>
          <td>{{ $c->service_type ?? '-' }} {{ $c->router_profile ? ' / '.$c->router_profile : '' }}</td>
          <td>{{ $c->ip_address ?? '-' }} {{ $c->vlan_id ? ' / VLAN '.$c->vlan_id : '' }}</td>
          <td>{!! $statusHtml !!}</td>
          @if($canAccept)
            <td>
              @if($hasProvision && strtolower($c->provision_status ?? 'pending') === 'pending')
                <form method="POST" action="{{ route('staff.customers.accept',$c->id) }}"
                      onsubmit="return confirm('Terima & provision ke RADIUS?')">
                  @csrf
                  <button class="btn btn-sm">Accept</button>
                </form>
              @else
                <span class="stat-label">—</span>
              @endif
            </td>
          @endif
        </tr>
      @endforeach
      </tbody>
    </table>

    <div style="margin-top:10px">{{ $customers->appends(['q'=>$q,'router'=>$routerFilter])->links() }}</div>
  @endif
</div>
@endsection

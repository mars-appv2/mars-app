@extends('staff.layouts.app')
@section('title','Perangkat — Staff')

@section('content')
<div class="card" style="margin-bottom:14px">
  <div style="font-weight:600;margin-bottom:8px">Tambah Perangkat</div>
  @if(session('ok')) <div class="badge ok" style="display:inline-block;margin-bottom:8px">{{ session('ok') }}</div> @endif
  @if(session('err'))<div class="badge bad" style="display:inline-block;margin-bottom:8px">{{ session('err') }}</div> @endif

  <form method="POST" action="{{ route('staff.devices.store') }}" class="grid g3">
    @csrf
    <input class="input" name="name" placeholder="Nama Router" required>
    <input class="input" name="host" placeholder="IP/Hostname" required>
    <input class="input" name="port" placeholder="Port (8728)" type="number" min="1" max="65535">
    <input class="input" name="username" placeholder="Username" required>
    <input class="input" name="password" placeholder="Password" required>
    <div style="grid-column:1/-1"><button class="btn">Simpan</button></div>
  </form>
</div>

<div class="card">
  <div style="font-weight:600;margin-bottom:8px">Perangkat Saya</div>
  @if($devices->isEmpty())
    <div class="stat-label">Belum ada perangkat. Tambahkan di form di atas.</div>
  @else
  <table class="table">
    <thead><tr><th>ID</th><th>Nama</th><th>Host</th><th>Port</th><th>Aksi</th></tr></thead>
    <tbody>
    @foreach($devices as $d)
      <tr>
        <td>{{ $d->id }}</td>
        <td>{{ $d->name ?? '-' }}</td>
        <td>{{ $d->host ?? '-' }}</td>
        <td>{{ $d->port ?? '8728' }}</td>
        <td style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn btn-sm" href="{{ route('staff.devices.show',$d->id) }}">Dashboard</a>
          <form method="POST" action="{{ route('staff.devices.destroy',$d->id) }}" onsubmit="return confirm('Hapus perangkat ini?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm">Hapus</button>
          </form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
  @endif
</div>
@endsection

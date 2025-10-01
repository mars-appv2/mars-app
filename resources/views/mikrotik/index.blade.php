@extends('layouts.app')
@section('content')
<style>
.badge{font-size:.72rem;padding:.2rem .45rem;border-radius:.4rem;border:1px solid #2a3350;background:rgba(99,102,241,.15);color:#cbd5e1}
.m-card{background:#121827;border:1px solid #2a3350;border-radius:14px}
.m-btn{background:#1e293b;color:#fff;border:1px solid #334155;border-radius:10px;padding:.55rem 1rem;transition:.15s}
.m-btn:hover{filter:brightness(1.12);transform:translateY(-1px)}
.m-inp{background:#0c101c;border:1px solid #2a3350;border-radius:10px;color:#e6eaf2;width:100%;padding:.55rem .75rem}
.m-lab{color:#95a3bf;font-size:.85rem;margin-bottom:.35rem;display:block}
.m-table{width:100%;border-collapse:separate;border-spacing:0 8px}
.m-tr{background:#0f1526;border:1px solid #253052}
.m-th,.m-td{padding:.7rem .9rem;color:#e6eaf2}
.m-th{color:#95a3bf;font-weight:600;text-transform:uppercase;font-size:.75rem;letter-spacing:.04em}
</style>

<div class="container mx-auto px-2 md:px-4">
  <div class="mb-4 flex items-center gap-2">
    <span class="badge">Table List (hotfix-v5.2)</span>
    @if($list->first())
      <a href="{{ route('mikrotik.dashboard', $list->first()) }}" class="m-btn">Dashboard</a>
      <a href="{{ route('mikrotik.pppoe', $list->first()) }}" class="m-btn">PPPoE</a>
      <a href="{{ route('mikrotik.ipstatic', $list->first()) }}" class="m-btn">IP Static</a>
    @endif
  </div>

  <div class="m-card p-5">
    <div class="text-lg text-slate-200 font-semibold mb-3">Daftar MikroTik</div>
    <div class="overflow-x-auto">
      <table class="m-table">
        <thead>
          <tr>
            <th class="m-th">Nama</th>
            <th class="m-th">Host</th>
            <th class="m-th">Port</th>
            <th class="m-th">Username</th>
            <th class="m-th">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($list as $m)
            <tr class="m-tr">
              <td class="m-td">{{ $m->name }}</td>
              <td class="m-td">{{ $m->host }}</td>
              <td class="m-td">{{ $m->port }}</td>
              <td class="m-td">{{ $m->username }}</td>
              <td class="m-td">
		<a href="{{ route('mikrotik.dashboard',$m) }}" class="m-btn">Dashboard</a>
  		<a href="{{ route('mikrotik.pppoe',$m) }}" class="m-btn">PPPoE</a>
  		<a href="{{ route('mikrotik.ipstatic',$m) }}" class="m-btn">IP Static</a>
  		<a href="{{ route('mikrotik.edit', $m) }}" class="m-btn">Edit</a>
  		<form method="POST" action="{{ route('mikrotik.destroy', $m) }}" style="display:inline" onsubmit="return confirm('Yakin hapus Mikrotik ini?')">
    		  @csrf
    		  @method('DELETE')
    		  <button type="submit" class="m-btn">Hapus</button>
  		</form>


              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="m-card p-5 mt-6">
    <div class="text-lg text-slate-200 font-semibold mb-3">Tambah MikroTik</div>
    <form method="POST" action="{{ route('mikrotik.store') }}" class="grid md:grid-cols-5 gap-3 items-end">
      @csrf
      <div><label class="m-lab">Nama</label><input name="name" class="m-inp" required></div>
      <div><label class="m-lab">Host</label><input name="host" class="m-inp" placeholder="x.x.x.x" required></div>
      <div><label class="m-lab">Port</label><input name="port" class="m-inp" placeholder="8728"></div>
      <div><label class="m-lab">Username</label><input name="username" class="m-inp" required></div>
      <div><label class="m-lab">Password</label><input name="password" class="m-inp" required></div>
      <div class="md:col-span-5"><button class="m-btn m-btnp">SIMPAN</button></div>
    </form>
  </div>
</div>
@endsection

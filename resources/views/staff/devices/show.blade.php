@extends('staff.layouts.app')
@section('title','Perangkat #'.$d->id.' — '.$d->name)

@section('content')
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <div><b>{{ $d->name }}</b> — {{ $d->host }}{{ $d->port?':'.$d->port:'' }}</div>
    <a class="btn" href="{{ route('staff.devices.index') }}">Kembali</a>
  </div>

  <div class="grid g2" style="margin-top:12px">
    <a class="btn" href="#">PPPoE</a>
    <a class="btn" href="#">IP Static</a>
    <a class="btn" href="#">Monitor</a>
    <a class="btn" href="#">Settings</a>
  </div>

  <div class="stat-label" style="margin-top:12px">
    (Stub) Halaman detail perangkat versi Staff. Submenu akan diisi mengikuti fungsi yang ada.
  </div>
</div>
@endsection

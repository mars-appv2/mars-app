@extends('client.layouts.app')
@section('title','Dashboard — Client')

@section('content')
<div class="grid g3">
  <div class="card">
    <div class="stat-label">Connection</div>
    <div class="stat-value">
      @if($status === 'Online')
        <span class="badge ok">Online</span>
      @elseif($status === 'Offline')
        <span class="badge bad">Offline</span>
      @else
        <span class="badge">Unknown</span>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="stat-label">Plan</div>
    <div class="stat-value">{{ $plan }}</div>
  </div>

  <div class="card">
    <div class="stat-label">Kuota / Bulan (perkiraan)</div>
    <div class="stat-value">{{ $quota }}</div>
  </div>
</div>

<div class="grid g3" style="margin-top:14px">
  <a class="card" href="{{ route('client.traffic') }}">
    <div class="stat-label">Grafik Traffic</div>
    <div class="stat-value">24 jam</div>
  </a>
  <a class="card" href="{{ route('client.invoices') }}">
    <div class="stat-label">Invoices</div>
    <div class="stat-value">Riwayat Tagihan</div>
  </a>
  <a class="card" href="{{ route('client.wifi') }}">
    <div class="stat-label">WiFi / SSID</div>
    <div class="stat-value">Ganti SSID & Password</div>
  </a>
</div>
@endsection

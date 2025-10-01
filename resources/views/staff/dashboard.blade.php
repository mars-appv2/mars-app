@extends('staff.layouts.app')
@section('title','Dashboard — Staff')

@section('content')
<div class="grid g4" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px">
  <div class="card">
    <div class="stat-label">Active Sessions</div>
    <div class="stat-value">{{ $activeSessions }}</div>
  </div>
  <div class="card">
    <div class="stat-label">Unpaid Invoices</div>
    <div class="stat-value">{{ $unpaidInvoices }}</div>
  </div>
  <div class="card">
    <div class="stat-label">Open Tickets</div>
    <div class="stat-value">{{ $openTickets }}</div>
  </div>
  <div class="card">
    <div class="stat-label">Routers Online</div>
    <div class="stat-value">{{ $routersOnline }}</div>
  </div>
</div>

<div class="grid g2" style="margin-top:14px">
  <div class="card">
    <div style="font-weight:600;margin-bottom:8px">Snapshot Operasional</div>
    <div class="stat-label">Pantau sesi aktif, tagihan belum lunas, dan ticket open secara real-time.</div>
  </div>
  <div class="card">
    <div style="font-weight:600;margin-bottom:8px">Aksi Cepat</div>
    <div class="grid g2">
      <a class="btn" href="{{ route('staff.noc') }}">Buka NOC</a>
      <a class="btn" href="{{ route('staff.tickets') }}">Kelola Tickets</a>
      <a class="btn" href="{{ route('staff.billing') }}">Lihat Billing</a>
      <a class="btn" href="{{ route('staff.dashboard') }}">Refresh</a>
    </div>
  </div>
</div>
@endsection

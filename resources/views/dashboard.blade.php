@extends('layouts.app')
@section('title','Dashboard')
@section('content')
<div class="grid md:grid-cols-3 gap-4">
  <div class="card p-4"><div class="label mb-2">Status</div><div class="text-2xl font-bold">Welcome</div><p class="opacity-80">PT MARS DATA TELEKOMUNIKASI</p></div>
  <div class="card p-4"><div class="label mb-2">Network</div><p class="opacity-80">Kelola Mikrotik, PPPoE, VLAN, Bridge</p></div>
  <div class="card p-4"><div class="label mb-2">Billing</div><p class="opacity-80">Invoices, Cashflow, Payment Gateway</p></div>
</div>
@endsection

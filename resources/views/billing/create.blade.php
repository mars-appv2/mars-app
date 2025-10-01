@extends('layouts.app')
@section('title','New Invoice')
@section('content')
<form method="POST" action="{{ route('billing.store') }}" class="card p-4 space-y-3">@csrf
  <div class="grid md:grid-cols-2 gap-3">
    <div><label>Customer Name</label><input name="customer_name" class="field w-full" required></div>
    <div><label>Type</label>
      <select name="customer_type" class="field w-full"><option value="retail">Retail</option><option value="corpo">Corpo</option></select>
    </div>
    <div><label>PPPoE Username (untuk isolir)</label><input name="pppoe_username" class="field w-full" placeholder="(opsional)"></div>
    <div><label>Mikrotik Device ID</label><input name="mikrotik_id" class="field w-full" placeholder="ID device (opsional)"></div>
    <div><label>Amount (before PPN)</label><input name="amount" type="number" class="field w-full" required></div>
    <div><label>PPN %</label><input name="ppn_percent" type="number" value="11" class="field w-full"></div>
    <div><label>Due Date</label><input name="due_date" type="date" class="field w-full"></div>
  </div>
  <button class="btn-primary px-4 py-2 rounded-lg">Save</button>
</form>
@endsection

@extends('layouts.app')
@section('content')
<div class="container mx-auto px-3">
  <div class="m-card p-5 mt-6">
    <div class="text-lg font-semibold text-slate-200 mb-3">Invoices</div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead><tr class="text-slate-300" style="background:#0c101c">
          <th class="px-3 py-2 text-left">Invoice</th>
          <th class="px-3 py-2 text-left">Username</th>
          <th class="px-3 py-2 text-left">Plan</th>
          <th class="px-3 py-2 text-left">Periode</th>
          <th class="px-3 py-2 text-left">Amount</th>
          <th class="px-3 py-2 text-left">Status</th>
        </tr></thead>
        <tbody>
          @foreach($invoices as $inv)
          <tr class="border-t border-slate-800">
            <td class="px-3 py-2 text-slate-200">#{{ $inv->id }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $inv->subscription->username ?? '—' }}</td>
            <td class="px-3 py-2 text-slate-300">{{ optional($inv->subscription->plan)->name }}</td>
            <td class="px-3 py-2 text-slate-300">{{ $inv->period_start }} → {{ $inv->period_end }}</td>
            <td class="px-3 py-2 text-slate-300">Rp {{ number_format($inv->amount,0,',','.') }}</td>
            <td class="px-3 py-2 text-slate-300">{{ strtoupper($inv->status) }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="mt-3">{{ $invoices->links() }}</div>
  </div>
</div>
@endsection

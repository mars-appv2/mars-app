@extends('layouts.app')
@section('title','Billing – Invoices')
@section('content')
@can('manage billing')
<a class="btn-primary px-4 py-2 rounded-lg" href="{{ route('billing.create') }}">+ New Invoice</a>
@endcan
@if(session('ok'))<div class="text-green-300 mt-3">{{ session('ok') }}</div>@endif
<div class="mt-4 card p-4">
  <table class="w-full text-sm">
    <thead><tr class="text-left text-[var(--muted)]"><th>No</th><th>Customer</th><th>Total</th><th>Status</th><th>Jatuh Tempo</th><th>Aksi</th></tr></thead>
    <tbody>
      @foreach($invoices as $inv)
      <tr class="border-t border-[var(--line)]">
        <td class="p-2">{{ $inv->number }}</td>
        <td>{{ $inv->customer_name }} <span class="text-xs opacity-70">({{ $inv->customer_type }})</span></td>
        <td>Rp {{ number_format($inv->total,0,',','.') }}</td>
        <td>{{ $inv->status }}</td>
        <td>{{ optional($inv->due_date)->format('Y-m-d') }}</td>
        <td class="space-x-2">
          <a class="underline" href="{{ route('billing.pdf',[$inv,'small']) }}">Print Kecil</a>
          <a class="underline" href="{{ route('billing.pdf',[$inv,'large']) }}">Print Besar</a>
          @if($inv->status!=='paid')
            @can('manage billing')
              <form method="POST" action="{{ route('billing.paid',$inv) }}" class="inline">@csrf <button class="text-green-300">Mark Paid</button></form>
            @endcan
            <a class="underline text-indigo-300" href="{{ route('payments.pay',$inv) }}">Pay</a>
          @endif
          @can('manage billing')
            <form method="POST" action="{{ route('billing.delete',$inv) }}" class="inline">@csrf @method('DELETE') <button class="text-red-300">Hapus</button></form>
          @endcan
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div class="mt-3">{{ $invoices->links() }}</div>
</div>
@endsection

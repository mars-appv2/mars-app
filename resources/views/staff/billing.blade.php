@extends('staff.layouts.app')
@section('title','Billing — Staff')

@section('content')
<div class="card">
  <div style="font-weight:600;margin-bottom:8px">Invoice Belum Lunas</div>

  {{-- flash message --}}
  @if(session('ok')) <div class="badge ok" style="display:inline-block;margin-bottom:10px">{{ session('ok') }}</div> @endif
  @if(session('err'))<div class="badge bad" style="display:inline-block;margin-bottom:10px">{{ session('err') }}</div> @endif

  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
    <input class="input" name="q" value="{{ request('q') }}" placeholder="Cari no/username/email">
    <button class="btn" type="submit">Cari</button>
  </form>

  @if(empty($unpaid) || count($unpaid)===0)
    <div class="stat-label">Tidak ada invoice yang belum lunas.</div>
  @else
    <table class="table">
      <thead>
        <tr>
          <th>No</th>
          <th>Tanggal</th>
          <th>Pelanggan</th>
          <th>Total</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($unpaid as $inv)
          @php
            $st    = strtolower($inv->status ?? 'unpaid');
            $total = (int)($inv->total ?? $inv->amount ?? 0);
            $date  = \Carbon\Carbon::parse($inv->created_at ?? $inv->bill_date ?? $inv->date ?? now())->format('d M Y');
            $no    = $inv->number ?? ('INV-'.str_pad($inv->id,6,'0',STR_PAD_LEFT));
            $cust  = $inv->customer_name ?? $inv->username ?? $inv->email ?? '-';
          @endphp
          <tr>
            <td>{{ $no }}</td>
            <td>{{ $date }}</td>
            <td>{{ $cust }}</td>
            <td>Rp {{ number_format($total,0,',','.') }}</td>
            <td>
              @if(in_array($st,['paid','lunas']))
                <span class="badge ok">Lunas</span>
              @else
                <span class="badge bad">{{ ucfirst($st) }}</span>
              @endif
            </td>
            <td>
              @if(!in_array($st,['paid','lunas']))
                <form method="POST" action="{{ route('staff.invoices.pay',$inv->id) }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                  @csrf
                  <input class="input" name="amount" type="number" value="{{ $total }}" style="max-width:140px">
                  <input class="input" name="method" placeholder="cash/transfer" style="max-width:140px">
                  <input class="input" name="ref_no" placeholder="Ref." style="max-width:120px">
                  <button class="btn btn-sm">Bayar</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection

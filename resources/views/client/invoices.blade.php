@extends('client.layouts.app')
@section('title','Invoices — Client')

@section('content')
<div class="card">
  <div style="font-weight:600;margin-bottom:8px">Riwayat Pembayaran</div>

  @if(empty($invoices) || count($invoices)===0)
    <div class="stat-label">Belum ada invoice.</div>
  @else
    <table class="table">
      <thead>
        <tr>
          <th>No</th>
          <th>Tanggal</th>
          <th>Deskripsi</th>
          <th>Total</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoices as $inv)
          <tr>
            <td>{{ $inv->number ?? ('INV-' . str_pad($inv->id, 6, '0', STR_PAD_LEFT)) }}</td>
            <td>{{ \Carbon\Carbon::parse($inv->created_at ?? $inv->date ?? now())->format('d M Y') }}</td>
            <td>{{ $inv->description ?? '-' }}</td>
            <td>Rp {{ number_format((int)($inv->total ?? $inv->amount ?? 0),0,',','.') }}</td>
            <td>
              @php $st = strtolower($inv->status ?? 'unpaid'); @endphp
              @if(in_array($st,['paid','lunas']))
                <span class="badge ok">Lunas</span>
              @elseif(in_array($st,['pending','unpaid','belum bayar']))
                <span class="badge bad">Belum Bayar</span>
              @else
                <span class="badge">{{ ucfirst($st) }}</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection

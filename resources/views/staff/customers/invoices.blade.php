@extends('staff.layouts.app')
@section('title','Invoices — '.$c->username)

@section('content')
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
    <div><b>{{ $c->username }}</b> — {{ $c->name }}</div>
    <a class="btn" href="{{ route('staff.customers.index') }}">Kembali</a>
  </div>

  @if(session('ok'))<div class="badge ok" style="display:block;margin:10px 0">{{ session('ok') }}</div>@endif
  @if(session('err'))<div class="badge bad" style="display:block;margin:10px 0">{{ session('err') }}</div>@endif

  <h4 style="margin-top:12px">Buat Invoice</h4>
  <form method="POST" action="{{ route('staff.customers.invoices.store',$c->id) }}" class="grid g3">
    @csrf
    <input class="input" name="amount" type="number" placeholder="Jumlah (Rp)" required>
    <input class="input" name="bill_date" type="date" value="{{ date('Y-m-d') }}" required>
    <input class="input" name="due_date" type="date">
    <div style="grid-column:1/-1"><button class="btn">Simpan</button></div>
  </form>

  <h4 style="margin-top:18px">Daftar Invoice</h4>
  <table class="table">
    <thead><tr><th>ID</th><th>Tanggal</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
      @foreach($invoices as $i)
      <tr>
        <td>#{{ $i->id }}</td>
        <td>{{ $i->bill_date }}</td>
        <td>Rp {{ number_format($i->amount,0,',','.') }}</td>
        <td>{!! $i->status==='paid' ? '<span class="badge ok">Paid</span>' : '<span class="badge bad">Unpaid</span>' !!}</td>
        <td>
          @if($i->status!=='paid')
          <form method="POST" action="{{ route('staff.invoices.pay',$i->id) }}" class="grid g3">
            @csrf
            <input class="input" name="amount" type="number" value="{{ $i->amount }}" required>
            <input class="input" name="method" placeholder="cash/transfer">
            <input class="input" name="ref_no" placeholder="Ref.">
            <button class="btn btn-sm">Bayar</button>
          </form>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection

@extends('layouts.app')
@section('title','Finance — Kas Keluar/Masuk')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Kas Keluar/Masuk</div>
  @if(session('ok'))<div class="text-green-400 mb-3">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="text-red-400 mb-3">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('finance.kas.store') }}" class="grid md:grid-cols-6 gap-3">
    @csrf
    <input type="date" name="date" class="m-inp" required value="{{ now()->toDateString() }}">
    <select name="type" class="m-inp" required>
      <option value="in">Kas Masuk</option>
      <option value="out">Kas Keluar</option>
    </select>
    <select name="cash_account_id" class="m-inp" required>
      <option value="">Pilih Akun Kas/Bank</option>
      @foreach($cashAccounts as $c)
        <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
      @endforeach
    </select>
    <select name="counter_account_id" class="m-inp" required>
      <option value="">Nomor Akun </option>
      @foreach($allAccounts as $a)
        <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
      @endforeach
    </select>
    <input type="number" step="0.01" min="0" name="amount" class="m-inp" placeholder="Jumlah" required>
    <input type="text" name="ref" class="m-inp" placeholder="Ref (opsional)">
    <div class="md:col-span-6">
      <input type="text" name="description" class="m-inp" placeholder="Keterangan (opsional)">
    </div>
    <div class="md:col-span-6">
      <button class="m-btn">Simpan</button>
    </div>
  </form>
</div>

<div class="m-card p-5">
  <div class="text-slate-200 font-semibold mb-3">Transaksi Terbaru</div>
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead>
        <tr>
          <th class="text-left p-2">Tanggal</th>
          <th class="text-left p-2">Ref</th>
          <th class="text-left p-2">Deskripsi</th>
          <th class="text-right p-2">Debit</th>
          <th class="text-right p-2">Kredit</th>
        </tr>
      </thead>
      <tbody>
      @forelse($recent as $e)
        @php
          $debit = $e->lines->sum('debit');
          $credit= $e->lines->sum('credit');
        @endphp
        <tr class="border-t border-slate-700">
          <td class="p-2">{{ $e->date->format('Y-m-d') }}</td>
          <td class="p-2">{{ $e->ref }}</td>
          <td class="p-2">{{ $e->description }}</td>
          <td class="p-2 text-right">{{ number_format($debit,2) }}</td>
          <td class="p-2 text-right">{{ number_format($credit,2) }}</td>
        </tr>
      @empty
        <tr><td class="p-3" colspan="5">Belum ada transaksi.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

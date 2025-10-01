@extends('layouts.app')
@section('title','Cashflow')
@section('content')
<div class="card p-4">
  <form method="POST" class="grid md:grid-cols-4 gap-2">@csrf
    <select name="type" class="field"><option value="in">Masuk</option><option value="out">Keluar</option></select>
    <input name="amount" type="number" class="field" placeholder="Jumlah">
    <input name="date" type="date" class="field" value="{{ date('Y-m-d') }}">
    <input name="note" class="field" placeholder="Catatan">
    <button class="btn-primary px-3 py-2 rounded-lg">Tambah</button>
  </form>
  <table class="w-full text-sm mt-4">
    <thead><tr class="text-left text-[var(--muted)]"><th>Tanggal</th><th>Jenis</th><th>Jumlah</th><th>Catatan</th></tr></thead>
    <tbody>
      @foreach($rows as $r)
      <tr class="border-t border-[var(--line)]">
        <td class="p-2">{{ $r->date->format('Y-m-d') }}</td>
        <td>{{ $r->type }}</td>
        <td>Rp {{ number_format($r->amount,0,',','.') }}</td>
        <td>{{ $r->note }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection

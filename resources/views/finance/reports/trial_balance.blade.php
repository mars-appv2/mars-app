@extends('layouts.app')
@section('title','Finance — Neraca Percobaan')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Neraca Percobaan</div>
  <form method="GET" class="grid md:grid-cols-5 gap-3 mb-4">
    <input type="date" name="start" class="m-inp" value="{{ $start }}">
    <input type="date" name="end" class="m-inp" value="{{ $end }}">

    <div class="md:col-span-3 flex gap-2">
      <button class="m-btn">Tampilkan</button>
      @if(Route::has('finance.trial.export.csv'))
        <a class="m-btnp" href="{{ route('finance.trial.export.csv', request()->query()) }}">Export CSV</a>
      @endif
      @if(Route::has('finance.trial.export.pdf'))
        <a class="m-btnp" href="{{ route('finance.trial.export.pdf', request()->query()) }}">Export PDF</a>
      @endif
    </div>

  </form>

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead>
        <tr>
          <th class="text-left p-2">Kode</th>
          <th class="text-left p-2">Nama Akun</th>
          <th class="text-right p-2">Debit</th>
          <th class="text-right p-2">Kredit</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr class="border-t border-slate-700">
            <td class="p-2">{{ $row['a']->code }}</td>
            <td class="p-2">{{ $row['a']->name }}</td>
            <td class="p-2 text-right">{{ number_format($row['debitCol'],2) }}</td>
            <td class="p-2 text-right">{{ number_format($row['creditCol'],2) }}</td>
          </tr>
        @endforeach
        <tr class="border-t border-slate-600 font-semibold">
          <td class="p-2" colspan="2">TOTAL</td>
          <td class="p-2 text-right">{{ number_format($totalDebit,2) }}</td>
          <td class="p-2 text-right">{{ number_format($totalCredit,2) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection

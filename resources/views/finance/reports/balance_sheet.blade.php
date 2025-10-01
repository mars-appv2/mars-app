@extends('layouts.app')
@section('title','Finance — Neraca')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Neraca</div>
  <form method="GET" class="grid md:grid-cols-4 gap-3 mb-4">
    <input type="date" name="as_of" class="m-inp" value="{{ $asOf }}">

    <div class="md:col-span-3 flex gap-2">
      <button class="m-btn">Tampilkan</button>
  	@if(Route::has('finance.balance.export.csv'))
    	  <a class="m-btnp" href="{{ route('finance.balance.export.csv', request()->query()) }}">Export CSV</a>
  	@endif
  	@if(Route::has('finance.balance.export.pdf'))
    	  <a class="m-btnp" href="{{ route('finance.balance.export.pdf', request()->query()) }}">Export PDF</a>
  	@endif
    </div>

  </form>

  <div class="grid md:grid-cols-2 gap-6">
    <div class="m-card p-4 bg-slate-900/40">
      <div class="font-semibold mb-2">ASSET</div>
      <table class="w-full text-sm">
        @foreach($grouped['assets'] as [$a,$bal])
          <tr><td class="p-1">{{ $a->code }} — {{ $a->name }}</td><td class="p-1 text-right">{{ number_format($bal,2) }}</td></tr>
        @endforeach
        <tr class="border-t border-slate-600 font-semibold">
          <td class="p-1">TOTAL ASSET</td>
          <td class="p-1 text-right">{{ number_format($sum['assets'],2) }}</td>
        </tr>
      </table>
    </div>

    <div class="m-card p-4 bg-slate-900/40">
      <div class="font-semibold mb-2">LIABILITIES & EQUITY</div>
      <table class="w-full text-sm">
        @foreach($grouped['liabilities'] as [$a,$bal])
          <tr><td class="p-1">{{ $a->code }} — {{ $a->name }}</td><td class="p-1 text-right">{{ number_format($bal,2) }}</td></tr>
        @endforeach
        @foreach($grouped['equity'] as [$a,$bal])
          <tr><td class="p-1">{{ $a->code }} — {{ $a->name }}</td><td class="p-1 text-right">{{ number_format($bal,2) }}</td></tr>
        @endforeach
        <tr><td class="p-1">Laba/Rugi Berjalan</td><td class="p-1 text-right">{{ number_format($netIncome,2) }}</td></tr>
        <tr class="border-t border-slate-600 font-semibold">
          <td class="p-1">TOTAL LIAB + EQUITY</td>
          <td class="p-1 text-right">{{ number_format($sum['liabilities'] + $sum['equity'],2) }}</td>
        </tr>
      </table>
    </div>
  </div>
</div>
@endsection

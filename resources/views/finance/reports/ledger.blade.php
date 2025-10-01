@extends('layouts.app')
@section('title','Finance — Buku Besar')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">Buku Besar</div>
  <form method="GET" class="grid md:grid-cols-6 gap-3 mb-4">
    <select name="account_id" class="m-inp md:col-span-2" required>
      <option value="">Pilih Akun</option>
      @foreach($accounts as $a)
        <option value="{{ $a->id }}" @if(optional($account)->id==$a->id) selected @endif>{{ $a->code }} — {{ $a->name }}</option>
      @endforeach
    </select>
    <input type="date" name="start" class="m-inp" value="{{ $start }}">
    <input type="date" name="end" class="m-inp" value="{{ $end }}">

    <div class="md:col-span-2 flex gap-2">
      <button class="m-btn">Tampilkan</button>
      @if(Route::has('finance.ledger.export.csv') && $account)
        <a class="m-btnp"
           href="{{ route('finance.ledger.export.csv', [
             'account_id' => $account->id,
             'start' => $start,
             'end'   => $end
           ]) }}">
           Export CSV
        </a>
      @endif
    </div>

  </form>

  @if($account)
  <div class="text-slate-300 mb-2">Akun: <b>{{ $account->code }}</b> — {{ $account->name }}</div>
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead>
        <tr>
          <th class="text-left p-2">Tanggal</th>
          <th class="text-left p-2">Ref</th>
          <th class="text-left p-2">Keterangan</th>
          <th class="text-right p-2">Debit</th>
          <th class="text-right p-2">Kredit</th>
          <th class="text-right p-2">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <tr class="bg-slate-800/40">
          <td class="p-2" colspan="5">Saldo Awal</td>
          <td class="p-2 text-right">{{ number_format($opening,2) }}</td>
        </tr>
        @php $running = $opening; @endphp
        @foreach($lines as $l)
          @php
            $d = (float) $l->debit; $c = (float) $l->credit;
            // saldo normal sesuai tipe akun
            if (in_array($account->type,[1,5])) { $running += $d - $c; } else { $running += $c - $d; }
          @endphp
          <tr class="border-t border-slate-700">
            <td class="p-2">{{ $l->entry->date->format('Y-m-d') }}</td>
            <td class="p-2">{{ $l->entry->ref }}</td>
            <td class="p-2">{{ $l->entry->description }}</td>
            <td class="p-2 text-right">{{ number_format($d,2) }}</td>
            <td class="p-2 text-right">{{ number_format($c,2) }}</td>
            <td class="p-2 text-right">{{ number_format($running,2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>
@endsection

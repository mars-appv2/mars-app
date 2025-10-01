@extends('layouts.app')
@section('title','Pembayaran (Manual)')

@section('content')
@if(session('ok'))
  <div class="m-card p-3 mb-4 text-green-300">{{ session('ok') }}</div>
@endif
@if(session('err'))
  <div class="m-card p-3 mb-4 text-red-300">{{ session('err') }}</div>
@endif

<div class="m-card p-5 space-y-4">

  {{-- FILTER --}}
  <form method="GET" action="{{ route('billing.payments') }}" class="grid md:grid-cols-3 gap-3">
    <div>
      <label class="m-lab">Filter Perangkat</label>
      <select name="mikrotik_id" class="m-inp">
        <option value="">— semua perangkat —</option>
        @foreach($devices as $d)
          <option value="{{ $d->id }}" {{ (string)$mikrotikId===(string)$d->id?'selected':'' }}>{{ $d->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="md:col-span-2">
      <label class="m-lab">Cari (no invoice / username / nama)</label>
      <div class="flex gap-2">
        <input name="q" value="{{ $q ?? '' }}" class="m-inp" placeholder="contoh: INV202509-0007 atau johndoe">
        <button class="m-btn m-btnp m-btn-icon">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"/></svg>
          Filter
        </button>
      </div>
    </div>
  </form>

  {{-- REKENING / E-WALLET (jika ada di settings) --}}
  @php
    $pay_bca     = $accounts['pay_bca']     ?? '';
    $pay_bri     = $accounts['pay_bri']     ?? '';
    $pay_mandiri = $accounts['pay_mandiri'] ?? '';
    $pay_ovo     = $accounts['pay_ovo']     ?? '';
    $pay_dana    = $accounts['pay_dana']    ?? '';
    $pay_gopay   = $accounts['pay_gopay']   ?? '';
    $hasAny = $pay_bca||$pay_bri||$pay_mandiri||$pay_ovo||$pay_dana||$pay_gopay;
  @endphp

  <div class="m-card p-4">
    <div class="text-sm text-[var(--muted)] mb-2">Informasi Pembayaran (transfer manual)</div>
    @if($hasAny)
      <div class="grid md:grid-cols-3 gap-3 text-sm">
        @if($pay_bca)
          <div class="p-3 rounded-lg bg-[var(--panel)] border border-[var(--line)]">
            <div class="font-semibold text-slate-200 mb-1">BCA</div>
            <div class="opacity-80 leading-relaxed">{{ $pay_bca }}</div>
          </div>
        @endif
        @if($pay_bri)
          <div class="p-3 rounded-lg bg-[var(--panel)] border border-[var(--line)]">
            <div class="font-semibold text-slate-200 mb-1">BRI</div>
            <div class="opacity-80 leading-relaxed">{{ $pay_bri }}</div>
          </div>
        @endif
        @if($pay_mandiri)
          <div class="p-3 rounded-lg bg-[var(--panel)] border border-[var(--line)]">
            <div class="font-semibold text-slate-200 mb-1">Mandiri</div>
            <div class="opacity-80 leading-relaxed">{{ $pay_mandiri }}</div>
          </div>
        @endif
        @if($pay_ovo)
          <div class="p-3 rounded-lg bg-[var(--panel)] border border-[var(--line)]">
            <div class="font-semibold text-slate-200 mb-1">OVO</div>
            <div class="opacity-80 leading-relaxed">{{ $pay_ovo }}</div>
          </div>
        @endif
        @if($pay_dana)
          <div class="p-3 rounded-lg bg-[var(--panel)] border border-[var(--line)]">
            <div class="font-semibold text-slate-200 mb-1">DANA</div>
            <div class="opacity-80 leading-relaxed">{{ $pay_dana }}</div>
          </div>
        @endif
        @if($pay_gopay)
          <div class="p-3 rounded-lg bg-[var(--panel)] border border-[var(--line)]">
            <div class="font-semibold text-slate-200 mb-1">GoPay</div>
            <div class="opacity-80 leading-relaxed">{{ $pay_gopay }}</div>
          </div>
        @endif
      </div>
    @else
      <div class="text-[var(--muted)] text-sm">Belum ada data rekening / e-wallet. Simpan di menu <em>Settings → Pembayaran</em> atau pakai Setting yang kamu sediakan.</div>
    @endif
  </div>

  {{-- LIST UNPAID INVOICES --}}
  <form method="POST" action="{{ route('billing.payments.bulkPaid') }}" id="bulkPaidForm">
    @csrf

    <div class="flex items-center justify-between">
      <div class="text-slate-200 font-semibold">Invoice Belum Lunas</div>
      <button class="m-btn m-btnp m-btn-sm"
              onclick="return confirm('Tandai lunas semua yang terpilih?')">
        Tandai Lunas Terpilih
      </button>
    </div>

    <div class="overflow-auto mt-3">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-[var(--muted)]">
            <th class="py-2 pr-3"><input type="checkbox" onclick="document.querySelectorAll('.chk-inv').forEach(c=>c.checked=this.checked)"></th>
            <th class="py-2 pr-3">Invoice</th>
            <th class="py-2 pr-3">User</th>
            <th class="py-2 pr-3">Perangkat</th>
            <th class="py-2 pr-3">Periode</th>
            <th class="py-2 pr-3">Jatuh Tempo</th>
            <th class="py-2 pr-3">Total</th>
            <th class="py-2">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr class="border-t border-[var(--line)]">
              <td class="py-2 pr-3">
                <input class="chk-inv" type="checkbox" name="ids[]" value="{{ $r->id }}">
              </td>
              <td class="py-2 pr-3 text-slate-200">{{ $r->number }}</td>
              <td class="py-2 pr-3">{{ $r->username }}</td>
              <td class="py-2 pr-3">{{ $r->mikrotik_name }}</td>
              <td class="py-2 pr-3">{{ $r->period }}</td>
              <td class="py-2 pr-3">{{ $r->due_date }}</td>
              <td class="py-2 pr-3">{{ number_format((int)($r->total ?: $r->amount),0,',','.') }}</td>
              <td class="py-2">
                <form method="POST" action="{{ route('billing.payments.markPaid') }}"
                      onsubmit="return confirm('Tandai lunas invoice {{ $r->number }}?')">
                  @csrf
                  <input type="hidden" name="id" value="{{ $r->id }}">
                  <button class="m-btn m-btnp m-btn-sm">Tandai Lunas</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="py-3 text-[var(--muted)]">Tidak ada invoice unpaid.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </form>

</div>
@endsection


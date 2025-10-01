@extends('layouts.app')
@section('title','Tickets')

@section('content')
@php
  function badge($s){
    if ($s==='open')   return '<span class="px-2 py-0.5 rounded text-xs bg-amber-500/20 text-amber-300 border border-amber-500/30">OPEN</span>';
    if ($s==='closed') return '<span class="px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">CLOSED</span>';
    return e($s);
  }
@endphp

{{-- ===== Alarm banner untuk KOMPLAIN OPEN ===== --}}
@if(($alarmCount ?? 0) > 0)
  <div class="mb-4 p-3 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-200 flex items-center gap-2">
    <span class="text-xl">🔔</span>
    <div>
      <div class="font-semibold">Ada {{ $alarmCount }} tiket KOMPLAIN yang belum ditutup</div>
      <div class="text-xs opacity-80">Mohon tindak lanjuti dan tutup jika sudah selesai.</div>
    </div>
  </div>
@endif

<div class="m-card p-5 mb-4">
  <form method="get" class="grid md:grid-cols-4 gap-3">
    <div>
      <label class="text-xs text-[var(--muted)]">Status</label>
      <select name="status" class="m-inp">
        <option value="open"   {{ $qStatus==='open'?'selected':'' }}>Open</option>
        <option value="closed" {{ $qStatus==='closed'?'selected':'' }}>Closed</option>
        <option value="all"    {{ $qStatus==='all'?'selected':'' }}>Semua</option>
      </select>
    </div>
    <div>
      <label class="text-xs text-[var(--muted)]">Tipe</label>
      <select name="type" class="m-inp">
        <option value="all"      {{ $qType==='all'?'selected':'' }}>Semua</option>
        <option value="psb"      {{ $qType==='psb'?'selected':'' }}>PSB</option>
        <option value="complain" {{ $qType==='complain'?'selected':'' }}>Komplain</option>
      </select>
    </div>
    <div class="md:col-span-2">
      <label class="text-xs text-[var(--muted)]">Cari</label>
      <input type="text" name="q" value="{{ e($qSearch) }}" class="m-inp" placeholder="Kode / Username / Nama / Deskripsi">
    </div>
    <div class="md:col-span-4 flex justify-end">
      <button class="m-btn">Filter</button>
    </div>
  </form>
</div>

@if(session('ok'))
  <div class="mb-4 p-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-200">{{ session('ok') }}</div>
@endif
@if(session('err'))
  <div class="mb-4 p-3 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-200">{{ session('err') }}</div>
@endif

<div class="m-card p-0 overflow-hidden">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-800/60 text-[var(--muted)]">
      <tr>
        <th class="px-3 py-2 text-left">Kode</th>
        <th class="px-3 py-2 text-left">Tipe</th>
        <th class="px-3 py-2 text-left">Username</th>
        <th class="px-3 py-2 text-left">Nama / Alamat</th>
        <th class="px-3 py-2 text-left">Deskripsi</th>
        <th class="px-3 py-2 text-left">Status</th>
        <th class="px-3 py-2 text-right">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $t)
        <tr class="border-t border-white/5 hover:bg-white/5">
          <td class="px-3 py-2 font-mono">{{ $t->code }}</td>
          <td class="px-3 py-2 capitalize">{{ $t->type }}</td>
          <td class="px-3 py-2">{{ $t->username }}</td>
          <td class="px-3 py-2">
            <div class="font-medium">{{ $t->customer_name }}</div>
            @if(!empty($t->address))<div class="text-xs text-[var(--muted)]">{{ $t->address }}</div>@endif
          </td>
          <td class="px-3 py-2">
            <div class="max-w-[32rem] whitespace-pre-wrap">{{ $t->description }}</div>
          </td>
          <td class="px-3 py-2">{!! badge($t->status) !!}</td>
          <td class="px-3 py-2 text-right">
            @if($t->status === 'open')
              <form method="post" action="{{ route('tickets.close', ['ticket'=>$t->id]) }}" onsubmit="return confirmClose(this)">
                @csrf
                <input type="hidden" name="note" value="">
                <button class="m-btn bg-emerald-600 hover:bg-emerald-500">Tutup</button>
              </form>
            @else
              <span class="text-xs text-[var(--muted)]">–</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="px-3 py-6 text-center text-[var(--muted)]">Tidak ada data</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-4">
  {{ $rows->links() }}
</div>

<script>
  function confirmClose(form){
    const n = prompt('Catatan penutupan (opsional):','Selesai ditangani via WA');
    if (n === null) return false;
    form.querySelector('input[name=note]').value = n;
    return true;
  }
</script>
@endsection

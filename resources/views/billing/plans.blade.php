@extends('layouts.app')
@section('title','Billing — Plans')

@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">PLANS</div>

  {{-- flash message --}}
  @if(session('ok'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-green-900/40 text-green-200 border border-green-800">
      {{ session('ok') }}
    </div>
  @endif
  @if(session('err'))
    <div class="mb-3 text-sm px-3 py-2 rounded bg-red-900/40 text-red-200 border border-red-800">
      {{ session('err') }}
    </div>
  @endif

  @php
    // default nilai filter biar tidak undefined
    $mikrotikId = request('mikrotik_id');
    $q          = request('q');
  @endphp

  {{-- TOOLBAR: filter/cari + import --}}
  <div class="grid lg:grid-cols-12 gap-3 items-end mb-5">
    {{-- Filter & cari (GET) --}}
    <form method="GET" action="{{ route('billing.plans') }}" class="lg:col-span-8 grid grid-cols-12 gap-3 items-end">
      <div class="col-span-12 lg:col-span-5">
        <label class="m-lab">Filter Perangkat</label>
        <select name="mikrotik_id" class="m-inp">
          <option value="">— semua perangkat —</option>
          @foreach($devices as $d)
            <option value="{{ $d->id }}" {{ (string)$mikrotikId===(string)$d->id ? 'selected':'' }}>
              {{ $d->name }} — {{ $d->host }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-span-12 lg:col-span-5">
        <label class="m-lab">Cari nama plan</label>
        <input name="q" value="{{ $q ?? '' }}" class="m-inp" placeholder="nama plan…">
      </div>
      <div class="col-span-12 lg:col-span-2">
        <label class="m-lab opacity-0">.</label>
        <button class="m-btn w-full">Terapkan</button>
      </div>
    </form>

    {{-- IMPORT dari Mikrotik (POST) – STANDALONE, BUKAN di dalam form lain --}}
    <form method="POST" action="{{ url('/billing/plans/import') }}"
          class="lg:col-span-4 grid grid-cols-12 gap-3 items-end">
      @csrf
      <div class="col-span-9">
        <label class="m-lab">Import dari Mikrotik</label>
        <select name="mikrotik_id" class="m-inp" required>
          <option value="">— pilih perangkat —</option>
          @foreach($devices as $d)
            <option value="{{ $d->id }}">{{ $d->name }} — {{ $d->host }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-span-3">
        <label class="m-lab opacity-0">.</label>
        <button type="submit" class="m-btn w-full">Import</button>
      </div>
    </form>
  </div>

  {{-- TAMBAH PLAN manual --}}
  <form method="POST" action="{{ route('billing.plans.store') }}" class="grid lg:grid-cols-12 gap-3 items-end mb-5">
    @csrf
    <div class="lg:col-span-5">
      <label class="m-lab">Nama Plan</label>
      <input type="text" name="name" class="m-inp" placeholder="mis. Silver 10M" required>
    </div>
    <div class="lg:col-span-4">
      <label class="m-lab">Perangkat (opsional)</label>
      <select name="mikrotik_id" class="m-inp">
        <option value="">— tanpa device —</option>
        @foreach($devices as $d)
          <option value="{{ $d->id }}">{{ $d->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="lg:col-span-2">
      <label class="m-lab">Harga</label>
      <input type="number" name="price" class="m-inp" min="0" placeholder="0">
    </div>
    <div class="lg:col-span-1">
      <label class="m-lab opacity-0">.</label>
      <button class="m-btn w-full">Tambah</button>
    </div>
  </form>

  {{-- TABEL PLANS --}}
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-[var(--muted)]">
          <th class="py-2 pr-3 w-[42%]">Nama</th>
          <th class="py-2 pr-3 w-[30%]">Perangkat</th>
          <th class="py-2 pr-3 w-[14%]">Harga</th>
          <th class="py-2 pr-3 w-[14%] text-right">Aksi</th>
        </tr>
      </thead>
      <tbody>
      @forelse($plans as $p)
        <tr class="border-t border-[var(--line)]">
          {{-- form UPDATE per baris (bukan nested) --}}
          <form method="POST" action="{{ route('billing.plans.update',$p->id) }}">
            @csrf
            <td class="py-2 pr-3 align-top">
              <input type="text" name="name" value="{{ $p->name }}" class="m-inp w-full">
            </td>
            <td class="py-2 pr-3 align-top">
              <select name="mikrotik_id" class="m-inp w-full">
                <option value="">— tanpa device —</option>
                @foreach($devices as $d)
                  <option value="{{ $d->id }}" {{ (int)($p->mikrotik_id ?? 0)===(int)$d->id ? 'selected':'' }}>
                    {{ $d->name }}
                  </option>
                @endforeach
              </select>
            </td>
            <td class="py-2 pr-3 align-top">
              <input type="number" name="price" value="{{ (int)($p->price ?? $p->price_month ?? 0) }}"
                     class="m-inp w-full" min="0">
            </td>
            <td class="py-2 pr-3 text-right align-top">
              <div class="flex gap-2 justify-end">
                <button class="m-btn m-btn-outline">Simpan</button>
          </form>
                <form method="POST" action="{{ route('billing.plans.delete',$p->id) }}">
                  @csrf @method('DELETE')
                  <button class="m-btn bg-red-600/80 hover:bg-red-600">Hapus</button>
                </form>
              </div>
            </td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="py-6 text-center text-[var(--muted)]">Belum ada plan.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

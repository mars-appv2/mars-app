@extends('layouts.app')
@section('title','Traffic — Content Apps')
@section('content')
@if(session('ok'))<div class="text-green-300 mb-3">{{session('ok')}}</div>@endif
@if(session('err'))<div class="text-rose-300 mb-3">{{session('err')}}</div>@endif
@php
  $apps   = $apps ?? $items ?? collect();
  $latMap = collect($latency ?? [])->keyBy('target_ip');
@endphp

{{-- Form tambah mapping --}}
<div class="card p-4 mb-4">
  <div class="label mb-2">Tambah IP Konten</div>
  <form method="POST" action="{{ route('traffic.content.targets.store') }}" class="grid md:grid-cols-12 gap-2">
    @csrf
    <div class="md:col-span-5">
      <input name="name" class="field" placeholder="Nama (Google/Meta/...)" required>
    </div>
    <div class="md:col-span-5">
      <input name="cidr" class="field" placeholder="IP (mis. 8.8.8.8)" required>
    </div>
    <div class="md:col-span-2 flex items-center">
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="enabled" checked>
        <span>Enabled</span>
      </label>
    </div>
    <div class="md:col-span-12">
      <button class="btn-primary w-full py-2 rounded">Simpan</button>
    </div>
  </form>
</div>

{{-- Tabel daftar konten --}}
<div class="card p-4">
  <div class="label mb-2">Content Targets</div>
  <table class="w-full text-sm">
    <thead>
      <tr class="text-left text-[var(--muted)]">
        <th class="py-2">Name</th>
        <th class="py-2">IP</th>
        <th class="py-2">Avg RTT</th>
        <th class="py-2">Timeout%</th>
        <th class="py-2 text-right">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($apps as $a)
        @php
          $ip  = strpos($a->cidr,'/')===false ? $a->cidr : null;
          $row = $ip ? ($latMap[$ip] ?? null) : null;
          $avg = $row ? (int)round($row->avg_rtt) : null;
          $pct = $row ? (int)round(($row->timeouts * 100) / max(1,$row->total)) : null;
        @endphp
        <tr class="border-t border-[var(--line)]">
          <td class="p-2">{{ $a->name }}</td>
          <td class="p-2 font-mono">{{ $a->cidr }}</td>
          <td class="p-2">{{ $avg!==null ? $avg.' ms' : '—' }}</td>
          <td class="p-2">{{ $pct!==null ? $pct.'%' : '—' }}</td>
          <td class="p-2 text-right">
            <div class="flex items-center justify-end gap-2">
              <a class="m-btn m-btn-outline m-btn-sm" href="{{ route('traffic.graphs.content.show',$a->id) }}">Lihat</a>
              <form method="POST" action="{{ route('traffic.graphs.ping',$a->id) }}" class="inline-block">
                @csrf
                <button class="m-btn m-btn-outline m-btn-sm">Ping sekarang</button>
              </form>
              <form method="POST" action="{{ route('traffic.content.targets.destroy',$a->id) }}" class="inline-block"
                    onsubmit="return confirm('Hapus mapping konten ini?');">
                @csrf
                @method('DELETE')
                <button class="m-btn m-btn-outline m-btn-sm text-rose-300 hover:text-rose-200">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td class="p-3 text-slate-400" colspan="5">Belum ada mapping konten.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
  <div class="text-[var(--muted)] mt-2">
    Grafik “Content” menampilkan <b>downtime (%)</b> — 0 berarti lancar, 100 berarti timeout.
  </div>
</div>
@endsection

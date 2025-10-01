@extends('layouts.app')
@section('content')
@php
  // Normalisasi nama interface dari array RouterOS
  $interfaces = $interfaces ?? ($if ?? []);
  $ifaceOptions = [];
  if (is_iterable($interfaces)) {
      foreach ($interfaces as $row) {
          $name = '';
          if (is_array($row)) {
              $name = $row['name'] ?? ($row['default-name'] ?? '');
              $type = $row['type'] ?? '';
          } else {
              // kalau bukan array, coba cast
              $name = (string)($row->name ?? $row ?? '');
              $type = (string)($row->type ?? '');
          }
          if ($name) $ifaceOptions[] = ['name'=>$name, 'type'=>$type];
      }
  }
@endphp

<div class="container mx-auto px-2 md:px-4">
  {{-- Nav sederhana agar konsisten --}}
  <div class="mb-4 flex gap-2 items-center">
    <a href="{{ route('mikrotik.index') }}" class="px-3 py-2 rounded bg-slate-800 text-slate-100 border border-slate-700">Table List</a>
    <a href="{{ route('mikrotik.dashboard',$mikrotik) }}" class="px-3 py-2 rounded bg-slate-800 text-slate-100 border border-slate-700">Dashboard</a>
    <a href="{{ route('mikrotik.pppoe',$mikrotik) }}" class="px-3 py-2 rounded bg-slate-800 text-slate-100 border border-slate-700">PPPoE</a>
    <a href="{{ route('mikrotik.ipstatic',$mikrotik) }}" class="px-3 py-2 rounded bg-blue-600 text-white border border-blue-500">IP Static</a>
  </div>

  {{-- Flash messages --}}
  @if (session('ok'))
    <div class="mb-3 p-3 rounded border border-emerald-600 text-emerald-200 bg-emerald-900/30">{{ session('ok') }}</div>
  @endif
  @if (session('err'))
    <div class="mb-3 p-3 rounded border border-rose-600 text-rose-200 bg-rose-900/30">{{ session('err') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-3 p-3 rounded border border-amber-600 text-amber-100 bg-amber-900/30">
      <div class="font-semibold mb-1">Validasi gagal:</div>
      <ul class="list-disc ms-5">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    {{-- Tambah IP Address --}}
    <div class="p-4 rounded-lg border border-slate-800" style="background:#0f1526">
      <div class="text-slate-200 font-semibold mb-3">Tambah IP Address</div>
      <form method="POST" action="{{ route('mikrotik.ipstatic.add', $mikrotik) }}" class="space-y-3">
        @csrf
        <div>
          <label class="block text-sm text-slate-300 mb-1">Address (mis: 10.10.10.2/24)</label>
          <input name="ip" type="text" value="{{ old('ip') }}" class="w-full px-3 py-2 rounded border border-slate-700 bg-slate-900 text-slate-100" placeholder="x.x.x.x/prefix" required>
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Interface</label>
          <select name="iface" class="w-full px-3 py-2 rounded border border-slate-700 bg-slate-900 text-slate-100" required>
            <option value="">— pilih interface —</option>
            @forelse ($ifaceOptions as $opt)
              <option value="{{ $opt['name'] }}" @selected(old('iface')===$opt['name'])>
                {{ $opt['name'] }} {{ $opt['type'] ? '(' . $opt['type'] . ')' : '' }}
              </option>
            @empty
              <option value="" disabled>(tidak ada data interface)</option>
            @endforelse
          </select>
          @if(empty($ifaceOptions))
            <div class="text-xs text-amber-300 mt-1">Tidak bisa membaca interface dari perangkat. Coba refresh halaman.</div>
          @endif
        </div>
        <div>
          <label class="block text-sm text-slate-300 mb-1">Comment (opsional)</label>
          <input name="comment" type="text" value="{{ old('comment') }}" class="w-full px-3 py-2 rounded border border-slate-700 bg-slate-900 text-slate-100" placeholder="keterangan (opsional)">
        </div>
        <div class="pt-1">
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 hover:brightness-110 text-white">Simpan</button>
        </div>
      </form>
      <div class="text-xs text-slate-400 mt-3">Catatan: perintah dikirim ke <code>/ip/address/add</code> (RouterOS v6 kompatibel).</div>
    </div>

    {{-- Hapus IP Address --}}
    <div class="p-4 rounded-lg border border-slate-800" style="background:#0f1526">
      <div class="text-slate-200 font-semibold mb-3">Hapus IP Address</div>
      <form method="POST" action="{{ route('mikrotik.ipstatic.remove', $mikrotik) }}" class="space-y-3">
        @csrf
        <div>
          <label class="block text-sm text-slate-300 mb-1">Address (persis, mis: 10.10.10.2/24)</label>
          <input name="ip" type="text" value="{{ old('ip') }}" class="w-full px-3 py-2 rounded border border-slate-700 bg-slate-900 text-slate-100" placeholder="x.x.x.x/prefix" required>
        </div>
        <div class="pt-1">
          <button type="submit" class="px-4 py-2 rounded bg-rose-600 hover:brightness-110 text-white">Hapus</button>
        </div>
      </form>
      <div class="text-xs text-slate-400 mt-3">Aplikasi akan mencari <code>.id</code> IP tersebut via <code>/ip/address/print</code>, lalu menghapusnya.</div>
    </div>

    {{-- Rekam Trafik per IP (opsional) --}}
    <div class="p-4 rounded-lg border border-slate-800 lg:col-span-2" style="background:#0f1526">
      <div class="text-slate-200 font-semibold mb-3">Rekam Trafik IP (opsional)</div>
      <form method="POST" action="{{ route('mikrotik.ipstatic.record', $mikrotik) }}" class="flex flex-wrap items-center gap-3">
        @csrf
        <div class="grow min-w-[240px]">
          <label class="block text-sm text-slate-300 mb-1">IP Address</label>
          <input name="ip" type="text" class="w-full px-3 py-2 rounded border border-slate-700 bg-slate-900 text-slate-100" placeholder="x.x.x.x/prefix" required>
        </div>
        <label class="inline-flex items-center gap-2 text-slate-200 mt-6">
          <input type="checkbox" name="enable" class="w-4 h-4">
          <span>Aktifkan rekaman</span>
        </label>
        <div class="mt-5">
          <button type="submit" class="px-4 py-2 rounded bg-emerald-600 hover:brightness-110 text-white">Simpan Rekaman</button>
        </div>
      </form>
      <div class="text-xs text-slate-400 mt-3">Fitur ini hanya menandai IP di database untuk keperluan monitoring/rekap, tidak mengubah konfigurasi RouterOS.</div>
    </div>
  </div>
</div>
@endsection

@extends('layouts.app')
@section('title','Traffic — Content Targets')

@section('content')
@if(session('ok'))
  <div class="text-green-300 mb-3">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="mb-3 rounded-xl bg-rose-900/40 border border-rose-700 px-4 py-3 text-rose-100">
    <ul class="mb-0 list-disc list-inside">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="grid md:grid-cols-2 gap-4">
  <div class="card p-4">
    <div class="label mb-2">Tambah IP Konten</div>
    <form method="POST" class="grid grid-cols-2 gap-2" action="{{ route('traffic.content.targets.store') }}">
      @csrf
      <input name="name" class="field col-span-2" placeholder="Nama (google/meta/tiktok ...)">
      <input name="cidr" class="field col-span-2" placeholder="CIDR / IP (contoh: 8.8.8.0/24)">
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="enabled" checked>
        <span>Enabled</span>
      </label>
      <button class="btn-primary px-4 py-2 rounded-lg col-span-2">Simpan</button>
    </form>
  </div>

  <div class="card p-4">
    <div class="label mb-2">Daftar Konten</div>
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-[var(--muted)]">
          <th class="py-2">Nama</th>
          <th class="py-2">CIDR/IP</th>
          <th class="py-2">Status</th>
          <th class="py-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr class="border-t border-[var(--line)]">
            <td class="p-2">{{ $r->name }}</td>
            <td class="p-2 font-mono">{{ $r->cidr }}</td>
            <td class="p-2">{{ $r->enabled ? 'Enabled':'Disabled' }}</td>
            <td class="p-2">
              <form method="POST" action="{{ route('traffic.content.targets.destroy', $r->id) }}" onsubmit="return confirm('Hapus data ini?')">
                @csrf @method('DELETE')
                <button class="underline text-rose-300">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td class="p-3 text-slate-400" colspan="4">Belum ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

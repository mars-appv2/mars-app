@extends('layouts.app')
@section('title','Traffic – Targets')

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

@php
  // Safety defaults
  $devices  = $devices  ?? collect();
  $targets  = $targets  ?? collect();
  $selected = $selected ?? null;
  $selectedId = $selectedId ?? null;

  // Map device id -> device
  $devMap = $devices->keyBy('id');

  // Jika selected belum ada tapi selectedId dikirim, coba ambil dari $targets (tanpa DB)
  if (!$selected && $selectedId) {
    $selected = $targets->firstWhere('id', (int)$selectedId);
  }

  // Helper kecil
  $isEnabled = function($row) {
    if (!isset($row)) return false;
    if (isset($row->enabled))    return (int)$row->enabled === 1;
    if (isset($row->is_enabled)) return (int)$row->is_enabled === 1;
    if (isset($row->active))     return (int)$row->active === 1;
    return false;
  };
@endphp

{{-- ===== Panel Detail Target (muncul kalau ada selected) ===== --}}
@if(!empty($selected))
  <div class="card p-4 mb-4 border border-emerald-600/40">
    <div class="text-emerald-300 font-semibold mb-2">Detail Target Terpilih</div>
    <div class="grid md:grid-cols-2 gap-3 text-sm">
      <div>
        <span class="text-[var(--muted)]">Device:</span>
        {{ $devMap[$selected->mikrotik_id]->name ?? $selected->mikrotik_id }}
        ({{ $devMap[$selected->mikrotik_id]->host ?? '-' }})
      </div>
      <div>
        <span class="text-[var(--muted)]">Tipe:</span>
        {{ $selected->target_type ?? $selected->type ?? '-' }}
      </div>
      <div>
        <span class="text-[var(--muted)]">Key:</span>
        <span class="font-mono">
          {{ $selected->target_key ?? $selected->iface ?? $selected->name ?? '-' }}
        </span>
      </div>
      <div>
        <span class="text-[var(--muted)]">Label:</span>
        {{ $selected->label ?: '-' }}
      </div>
      <div>
        <span class="text-[var(--muted)]">Status:</span>
        {{ $isEnabled($selected) ? 'Enabled' : 'Disabled' }}
      </div>
      <div>
        <span class="text-[var(--muted)]">Interval:</span>
        {{ $selected->interval_sec ?? '-' }}s
      </div>
    </div>

    <div class="mt-3 flex flex-wrap gap-3 text-sm">
      {{-- Tombol cepat opsional --}}
      <a class="underline" href="{{ route('traffic.targets.export', $selected->id) }}">Export</a>
      <form method="POST" action="{{ route('traffic.targets.toggle', $selected->id) }}">
        @csrf
        <button class="underline">{{ $isEnabled($selected) ? 'Disable' : 'Enable' }}</button>
      </form>
    </div>
  </div>
@endif

<div class="grid md:grid-cols-2 gap-4">

  {{-- ========= FORM TAMBAH ========= --}}
  <div class="card p-4">
    <div class="label mb-2">Tambah Target</div>

    <form method="POST" action="{{ route('traffic.targets.store') }}" class="grid grid-cols-2 gap-2">
      @csrf

      {{-- Device --}}
      <select name="mikrotik_id" class="field">
        @foreach($devices as $d)
          <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->host }})</option>
        @endforeach
      </select>

      {{-- Tipe Target --}}
      <select name="target_type" class="field" id="targetType">
        <option value="interface">Interface</option>
        <option value="pppoe">PPPoE</option>
        <option value="ip">IP</option>
      </select>

      {{-- Key --}}
      <input name="target_key" class="field col-span-2" placeholder="ether1 / pppoe user / 1.2.3.4/32">

      {{-- Label --}}
      <input name="label" class="field col-span-2" placeholder="Label (opsional)">

      {{-- Opsi auto queue (muncul hanya untuk IP) --}}
      <div id="autoQueueRow" class="col-span-2" style="display:none">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="auto_queue" checked>
          <span>Otomatis buat Simple Queue untuk IP ini</span>
        </label>
      </div>

      <script>
      document.addEventListener('DOMContentLoaded', () => {
        const typeSel = document.getElementById('targetType');
        const autoRow = document.getElementById('autoQueueRow');
        const toggleAuto = () => { autoRow.style.display = (typeSel.value === 'ip') ? 'block' : 'none'; };
        typeSel.addEventListener('change', toggleAuto);
        toggleAuto();
      });
      </script>

      {{-- Enabled --}}
      <label class="inline-flex items-center gap-2 mt-2">
        <input type="checkbox" name="enabled" checked>
        <span>Enabled</span>
      </label>

      <button class="btn-primary px-4 py-2 rounded-lg col-span-2">Simpan</button>
    </form>
  </div>

  {{-- ========= DAFTAR TARGET ========= --}}
  <div class="card p-4">
    <div class="label mb-2">Daftar Target</div>

    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-[var(--muted)]">
          <th class="py-2">Device</th>
          <th class="py-2">Type</th>
          <th class="py-2">Key</th>
          <th class="py-2">Label</th>
          <th class="py-2">Status</th>
          <th class="py-2">Aksi</th>
        </tr>
      </thead>
      <tbody>
      @forelse($targets as $t)
        @php
          $type   = $t->target_type ?? $t->type ?? $t->category ?? '-';
          $key    = $t->target_key ?? $t->target ?? $t->iface ?? $t->name ?? '-';
          $label  = $t->label ?? $t->title ?? '';
          $enabledBool = $isEnabled($t);
          $status = $enabledBool ? 'Enabled' : 'Disabled';
          $devTxt = isset($devMap[$t->mikrotik_id]) ? ($devMap[$t->mikrotik_id]->name.' ('.$devMap[$t->mikrotik_id]->host.')') : $t->mikrotik_id;
          $rowActive = (!empty($selectedId) && (int)$t->id === (int)$selectedId);
        @endphp

        <tr id="t-{{ $t->id }}" class="border-t border-[var(--line)] {{ $rowActive ? 'bg-emerald-900/20 ring-1 ring-emerald-600/40' : '' }}">
          <td class="p-2">{{ $devTxt }}</td>
          <td class="p-2 capitalize">{{ $type }}</td>
          <td class="p-2 font-mono">{{ $key }}</td>
          <td class="p-2">{{ $label ?: '-' }}</td>
          <td class="p-2">{{ $status }}</td>
          <td class="p-2">
            <div class="flex items-center flex-wrap gap-3">
              {{-- Lihat --}}
              <a class="underline" href="{{ route('traffic.targets.show', $t->id) }}">Lihat</a>

              {{-- Enable / Disable --}}
              <form method="POST" action="{{ route('traffic.targets.toggle', $t->id) }}" class="inline-block">
                @csrf
                <button class="underline" type="submit">{{ $enabledBool ? 'Disable' : 'Enable' }}</button>
              </form>

              {{-- Export --}}
              <a class="underline" href="{{ route('traffic.targets.export', $t->id) }}">Export</a>

              {{-- Hapus --}}
              <form method="POST"
                    action="{{ route('traffic.targets.destroy', $t->id) }}"
                    class="inline-block"
                    onsubmit="return confirm('Yakin hapus target ini?');">
                @csrf
                @method('DELETE')
                <button class="underline text-rose-300 hover:text-rose-200" type="submit">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td class="p-3 text-slate-400" colspan="6">Belum ada target.</td></tr>
      @endforelse
      </tbody>
    </table>

    @if(method_exists($targets, 'links'))
      <div class="mt-2">{{ $targets->links() }}</div>
    @endif
  </div>
</div>

{{-- Auto-scroll ke baris yang dipilih --}}
@if(!empty($selectedId))
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('t-{{ (int)$selectedId }}');
    if (el) el.scrollIntoView({behavior:'smooth', block:'center'});
  });
</script>
@endif
@endsection

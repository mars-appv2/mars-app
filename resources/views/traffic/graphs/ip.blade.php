@extends('layouts.app')
@section('title','Traffic — IP Public')
@section('content')
@if(session('ok'))<div class="text-green-300 mb-3">{{session('ok')}}</div>@endif
@if(session('err'))<div class="text-rose-300 mb-3">{{session('err')}}</div>@endif

<div class="card p-4 mb-4">
  <div class="label mb-2">Tambah Target IP</div>
  <form method="POST" action="{{ route('traffic.targets.store') }}" class="grid md:grid-cols-12 gap-2">
    @csrf
    <input type="hidden" name="target_type" value="ip">
    <div class="md:col-span-3">
      <select name="mikrotik_id" class="field" required>
        <option value="">— Pilih Device —</option>
        @foreach($devices as $d)
          <option value="{{$d->id}}">{{$d->name}} ({{$d->host}})</option>
        @endforeach
      </select>
    </div>
    <div class="md:col-span-5">
      <input name="target_key" class="field" placeholder="1.2.3.4/32" required>
    </div>
    <div class="md:col-span-3">
      <input name="label" class="field" placeholder="Label (opsional)">
    </div>
    <div class="md:col-span-12">
      <label class="inline-flex items-center gap-2">
        <input type="checkbox" name="enabled" checked>
        <span>Enabled</span>
      </label>
    </div>
    <div class="md:col-span-12">
      <button class="btn-primary w-full py-2 rounded">Simpan Target</button>
    </div>
  </form>
</div>

<div class="card p-4">
  <div class="label mb-2">Daftar IP Public</div>
  <table class="w-full text-sm">
    <thead>
      <tr class="text-left text-[var(--muted)]">
        <th class="py-2">Label / IP</th>
        <th class="py-2">Device</th>
        <th class="py-2">Status</th>
        <th class="py-2 text-right">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr class="border-t border-[var(--line)]">
          <td class="p-2">{{ $r->label ?: $r->target_key }}</td>
          <td class="p-2">{{ $r->mikrotik_id }}</td>
          <td class="p-2">{{ $r->enabled ? 'Enabled' : 'Disabled' }}</td>
          <td class="p-2 text-right">
            <div class="flex items-center justify-end gap-2">
              <a class="m-btn m-btn-outline m-btn-sm" href="{{ route('traffic.graphs.show',$r->id) }}">Lihat</a>
              <form method="POST" action="{{ route('traffic.targets.destroy',$r->id) }}" class="inline-block"
                    onsubmit="return confirm('Hapus target IP ini?');">
                @csrf
                @method('DELETE')
                <button class="m-btn m-btn-outline m-btn-sm text-rose-300 hover:text-rose-200">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td class="p-3 text-slate-400" colspan="4">Belum ada data.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection

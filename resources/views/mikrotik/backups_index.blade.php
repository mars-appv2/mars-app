@extends('layouts.app')
@section('title','Backups — All Devices')

@section('content')
<div class="m-card p-5 mb-4">
  <div class="text-lg text-slate-200 font-semibold mb-4">BACKUPS — ALL DEVICES</div>

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

  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-[var(--muted)]">
          <th class="py-2 pr-3">Device</th>
          <th class="py-2 pr-3">Host</th>
          <th class="py-2 pr-3">Latest</th>
          <th class="py-2 pr-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($devices as $d)
          @php $l = $latest[$d->id] ?? null; @endphp
          <tr class="border-t border-[var(--line)]">
            <td class="py-2 pr-3">{{ $d->name }}</td>
            <td class="py-2 pr-3">{{ $d->host }}</td>
            <td class="py-2 pr-3">
              @if($l)
                <div>{{ strtoupper($l->type) }} — {{ basename($l->filename) }}</div>
                <div class="text-xs text-[var(--muted)]">{{ $l->created_at }}</div>
              @else
                <span class="text-[var(--muted)]">—</span>
              @endif
            </td>
            <td class="py-2 pr-3 text-right">
              <a href="{{ route('mikrotik.backups', $d->id) }}" class="m-btn m-btn-outline">Lihat</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="py-6 text-center text-[var(--muted)]">Tidak ada device.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

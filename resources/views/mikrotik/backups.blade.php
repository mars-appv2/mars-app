@extends('layouts.app')
@section('title','Backups — '.$mikrotik->name)

@section('content')
<div class="m-card p-5 mb-4">
  <div class="flex items-center justify-between mb-4">
    <div class="text-lg text-slate-200 font-semibold">
      BACKUPS — {{ $mikrotik->name }} ({{ $mikrotik->host }})
    </div>
    <form method="POST" action="{{ route('mikrotik.backups.run',$mikrotik->id) }}" class="flex items-center gap-2">
      @csrf
      <label class="text-sm text-[var(--muted)]">Mode:</label>
      <label class="inline-flex items-center gap-1 text-sm">
        <input type="checkbox" name="modes[]" value="radius-json" checked class="m-inp-checkbox"> radius-json
      </label>
      <label class="inline-flex items-center gap-1 text-sm">
        <input type="checkbox" name="modes[]" value="export-rsc" class="m-inp-checkbox"> export-rsc
      </label>
      <label class="inline-flex items-center gap-1 text-sm">
        <input type="checkbox" name="modes[]" value="backup-bin" class="m-inp-checkbox"> backup-bin
      </label>
      <button class="m-btn">Backup Sekarang</button>
    </form>
  </div>

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
          <th class="py-2 pr-3">Waktu</th>
          <th class="py-2 pr-3">Jenis</th>
          <th class="py-2 pr-3">File</th>
          <th class="py-2 pr-3">Ukuran</th>
          <th class="py-2 pr-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr class="border-t border-[var(--line)]">
            <td class="py-2 pr-3">{{ $r->created_at }}</td>
            <td class="py-2 pr-3">{{ strtoupper($r->type) }}</td>
            <td class="py-2 pr-3">{{ basename($r->filename) }}</td>
            <td class="py-2 pr-3">{{ number_format(($r->size ?? 0)/1024,1) }} KB</td>
            <td class="py-2 pr-3 text-right">
              <div class="flex gap-2 justify-end items-center flex-wrap">
                <a class="m-btn m-btn-outline" href="{{ route('mikrotik.backups.download',[$mikrotik->id,$r->id]) }}">Download</a>

                @if($r->type === 'radius-json')
                  <form method="POST" action="{{ route('mikrotik.backups.restore',[$mikrotik->id,$r->id]) }}"
                        onsubmit="return confirm('Restore dari JSON ke router {{ $mikrotik->host }}?');"
                        class="flex items-center gap-2">
                    @csrf
                    <label class="inline-flex items-center gap-1 text-xs text-[var(--muted)]">
                      <input type="checkbox" name="replace" class="m-inp-checkbox">
                      Replace (hapus yg tidak ada di backup)
                    </label>
                    <button class="m-btn m-btn-outline">Restore</button>
                  </form>
                @endif

                <form method="POST" action="{{ route('mikrotik.backups.delete',[$mikrotik->id,$r->id]) }}"
                      onsubmit="return confirm('Hapus backup ini?');">
                  @csrf @method('DELETE')
                  <button class="m-btn bg-red-600/80 hover:bg-red-600">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-6 text-center text-[var(--muted)]">Belum ada backup.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

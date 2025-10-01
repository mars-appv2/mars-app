@extends('layouts.app')
@section('title','Billing — Subscriptions')

@section('content')
@if(session('ok'))
  <div class="m-card p-3 mb-4 text-green-300">{{ session('ok') }}</div>
@endif
@if(session('err'))
  <div class="m-card p-3 mb-4 text-red-300">{{ session('err') }}</div>
@endif

<style>
  /* ===== Buttons (fix: text keluar saat pakai m-btn bawaan) ===== */
  .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.45rem .8rem;border-radius:.75rem;border:1px solid var(--line);background:var(--panel);color:var(--text);line-height:1;white-space:nowrap}
  .btn-sm{height:2.25rem;font-size:.875rem}
  .btn-ghost{background:transparent}
  .btn-primary{background:#3b82f6;border-color:#3b82f6;color:#fff}
  .btn-danger{background:#dc2626;border-color:#dc2626;color:#fff}
  .btn:disabled{opacity:.6;cursor:not-allowed}

  .ico{width:1rem;height:1rem;flex:0 0 auto}

  .pill{padding:.15rem .55rem;border-radius:999px;font-size:.72rem;font-weight:700;display:inline-block}
  .pill-ok{background:rgba(16,185,129,.12);color:#34d399;border:1px solid rgba(16,185,129,.35)}
  .pill-bad{background:rgba(244,63,94,.12);color:#fda4af;border:1px solid rgba(244,63,94,.35)}

  .table-scroll{overflow:auto;scrollbar-gutter:stable}
</style>

<div class="space-y-4">

  {{-- ========== FILTER & QUICK ACTIONS ========== --}}
  <div class="m-card p-5 space-y-4">

    <form method="GET" action="{{ route('billing.subs') }}" class="grid md:grid-cols-4 gap-3">
      <div>
        <label class="m-lab">Perangkat</label>
        <select name="mikrotik_id" class="m-inp">
          <option value="">— semua —</option>
          @foreach($devices as $d)
            <option value="{{ $d->id }}" {{ (string)$mikrotikId===(string)$d->id?'selected':'' }}>
              {{ $d->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="m-lab">Plan</label>
        <select name="plan_id" class="m-inp">
          <option value="">— semua —</option>
          @foreach($plans as $p)
            <option value="{{ $p->id }}" {{ (string)$planId===(string)$p->id?'selected':'' }}>
              {{ $p->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="md:col-span-2">
        <label class="m-lab">Cari username</label>
        <div class="flex gap-2">
          <input name="q" value="{{ $q ?? '' }}" class="m-inp" placeholder="username…">
          <button type="submit" class="btn btn-primary btn-sm">
            {{-- search --}}
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"/>
            </svg>
            Terapkan
          </button>
        </div>
      </div>
    </form>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      {{-- LEFT: Quick buttons --}}
      <div class="flex flex-wrap items-center gap-2">
        {{-- Sync dari RADIUS --}}
        <form method="POST" action="{{ route('billing.tools.sync') }}">
          @csrf
          <input type="hidden" name="mikrotik_id" value="{{ $mikrotikId ?: '' }}">
          <button class="btn btn-ghost btn-sm" title="Sync dari RADIUS">
            {{-- refresh-cw --}}
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 0115.54-5.54M21 12a9 9 0 01-15.54 5.54"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 4v6h6M21 20v-6h-6"/>
            </svg>
            Sync dari RADIUS
          </button>
        </form>

        {{-- Enforce Isolir/Restore --}}
        <form method="POST" action="{{ route('billing.tools.enforce') }}">
          @csrf
          <button class="btn btn-ghost btn-sm" title="Enforce (cek jatuh tempo, isolir/restore)">
            {{-- shield-check --}}
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5a10 10 0 01-7 9 10 10 0 01-7-9V7l7-4z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
            </svg>
            Enforce (Isolir/Restore)
          </button>
        </form>

        {{-- Buat Invoice (tambahan, tidak mengubah lainnya) --}}
        <form id="genForm" method="POST" action="{{ route('billing.tools.generate') }}">
          @csrf
          <input type="hidden" name="subs" id="subsPayload">
          <button type="submit" class="btn btn-primary btn-sm" title="Buat Invoice dari subscription terpilih">
            {{-- dollar-sign --}}
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>
            </svg>
            Buat Invoice
          </button>
        </form>
      </div>

      {{-- RIGHT: delete actions --}}
      <div class="flex items-center gap-3">
        <label class="flex items-center gap-2 text-xs text-[var(--muted)]">
          <input type="checkbox" id="withInvToggle" class="m-inp" style="width:1rem;height:1rem">
          sekaligus hapus invoices
        </label>

        {{-- Hapus Terpilih --}}
        <form id="formDelSelected" method="POST" action="{{ route('billing.subs.bulkDelete') }}"
              onsubmit="return confirm('Hapus subscription terpilih?')">
          @csrf
          <input type="hidden" name="scope" value="selected">
          <input type="hidden" name="with_invoices" id="withInvSelected" value="0">
          <input type="hidden" name="ids" id="idsSelected" value="">
          <button class="btn btn-primary btn-sm" title="Hapus terpilih">
            {{-- trash-2 --}}
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6l1 13a2 2 0 002 2h2a2 2 0 002-2l1-13M10 11v6M14 11v6M9 6V4a2 2 0 012-2h2a2 2 0 012 2v2"/>
            </svg>
            Hapus Terpilih
          </button>
        </form>

        {{-- Hapus Sesuai Filter --}}
        <form id="formDelFilter" method="POST" action="{{ route('billing.subs.bulkDelete') }}"
              onsubmit="return confirm('Hapus SEMUA sesuai filter saat ini?')">
          @csrf
          <input type="hidden" name="scope" value="filter">
          <input type="hidden" name="with_invoices" id="withInvFilter" value="0">
          <input type="hidden" name="mikrotik_id" value="{{ $mikrotikId ?: '' }}">
          <input type="hidden" name="plan_id" value="{{ $planId ?: '' }}">
          <input type="hidden" name="q" value="{{ $q ?? '' }}">
          <button class="btn btn-danger btn-sm" title="Hapus sesuai filter">
            {{-- funnel icon (filter) --}}
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/>
            </svg>
            Hapus Sesuai Filter
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- ========== TABLE LIST ========== --}}
  <div class="m-card p-5">
    <div class="table-scroll">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-[var(--muted)]">
            <th class="py-2 pr-3">
              <input type="checkbox" id="chkAll" style="width:1rem;height:1rem">
            </th>
            <th class="py-2 pr-3">Username</th>
            <th class="py-2 pr-3">Plan</th>
            <th class="py-2 pr-3">Perangkat</th>
            <th class="py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($subs as $s)
            <tr class="border-t border-[var(--line)]">
              <td class="py-2 pr-3">
                <input type="checkbox" class="rowchk" value="{{ $s->id }}" style="width:1rem;height:1rem">
              </td>
              <td class="py-2 pr-3 text-slate-200">{{ $s->username }}</td>
              <td class="py-2 pr-3">{{ $s->plan }}</td>
              <td class="py-2 pr-3">{{ $s->mikrotik_name }}</td>
              <td class="py-2">
                @if(strtolower($s->status)==='active')
                  <span class="pill pill-ok">ACTIVE</span>
                @else
                  <span class="pill pill-bad">{{ strtoupper($s->status) }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="py-3 text-[var(--muted)]">Tidak ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- ===== JS kecil utk bulk delete & invoice ===== --}}
<script>
  const chkAll = document.getElementById('chkAll');
  const rows   = () => Array.from(document.querySelectorAll('.rowchk'));
  const idsSel = document.getElementById('idsSelected');

  if (chkAll) {
    chkAll.addEventListener('change', e => {
      rows().forEach(c => c.checked = e.target.checked);
      collectIds();
    });
  }
  rows().forEach(c => c.addEventListener('change', collectIds));

  function collectIds(){
    const ids = rows().filter(c=>c.checked).map(c=>parseInt(c.value,10));
    idsSel.value = JSON.stringify(ids);
  }

  const withInv = document.getElementById('withInvToggle');
  const withSel = document.getElementById('withInvSelected');
  const withFil = document.getElementById('withInvFilter');
  if (withInv) {
    const upd = () => {
      const v = withInv.checked ? '1' : '0';
      withSel.value = v; withFil.value = v;
    };
    withInv.addEventListener('change', upd); upd();
  }

  // === Tambahan untuk tombol Buat Invoice ===
  const subsForm = document.getElementById('genForm');
  const subsPayload = document.getElementById('subsPayload');
  if (subsForm && subsPayload) {
    subsForm.addEventListener('submit', function(e){
      const ids = rows().filter(c=>c.checked).map(c=>parseInt(c.value,10));
      if (!ids.length) {
        alert("Pilih minimal satu subscription untuk dibuatkan invoice.");
        e.preventDefault(); return;
      }
      subsPayload.value = JSON.stringify(ids);
    });
  }
</script>
@endsection

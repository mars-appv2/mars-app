@extends('layouts.app')
@section('title','RADIUS — Sessions')

@section('content')
@php
  $mkByHost = [];
  foreach ($devices as $d) $mkByHost[trim($d->host ?? '')] = $d->name ?? $d->host;

  $humanBytes = function($v) {
      if ($v === null || $v === '') return '—';
      $v = (float)$v;
      $units = ['B','KB','MB','GB','TB'];
      $i=0; while ($v >= 1024 && $i < count($units)-1) { $v/=1024; $i++; }
      return number_format($v, $i ? 2 : 0).' '.$units[$i];
  };
  $humanSecs = function($s) {
      if ($s === null || $s === '') return '—';
      $s = (int)$s;
      $d = intdiv($s,86400); $s%=86400;
      $h = intdiv($s,3600);  $s%=3600;
      $m = intdiv($s,60);    $s%=60;
      $out = [];
      if ($d) $out[] = $d.'d';
      if ($h) $out[] = $h.'h';
      if ($m) $out[] = $m.'m';
      if ($s && !$d) $out[] = $s.'s';
      return $out ? implode(' ',$out) : '0s';
  };
@endphp

<div class="m-card p-5 mb-6" x-data="sessUi()">
  {{-- Header + auto refresh --}}
  <div class="mb-4 flex items-center justify-between">
    <div class="text-lg text-slate-200 font-semibold">Sessions</div>
    <div class="flex items-center gap-3">
      <label class="flex items-center gap-2 text-xs text-[var(--muted)]">
        <input type="checkbox" class="accent-slate-300" x-model="auto">
        Auto refresh
      </label>
      <button type="button" class="m-btn m-btn-outline !px-3 !py-1 !text-xs" @click="fetchAndRender()">
        Refresh
      </button>
    </div>
  </div>

  {{-- Toolbar filter --}}
  <form id="filter-form" method="GET" action="{{ route('radius.sessions') }}" class="grid lg:grid-cols-12 gap-3 items-end mb-4">
    <div class="lg:col-span-5">
      <label class="m-lab">Filter Perangkat</label>
      <select name="mikrotik_id" class="m-inp" x-ref="selMik">
        <option value="">— semua perangkat —</option>
        @foreach($devices as $d)
          <option value="{{ $d->id }}" {{ (string)$sel === (string)$d->id ? 'selected' : '' }}>
            {{ $d->name }} — {{ $d->host }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="lg:col-span-5">
      <label class="m-lab">Cari username</label>
      <input name="q" value="{{ $q ?? '' }}" class="m-inp" placeholder="ketik username…" x-ref="inpQ">
    </div>
    <div class="lg:col-span-2">
      <label class="m-lab opacity-0">.</label>
      <button class="m-btn w-full">Terapkan</button>
    </div>
  </form>

  {{-- Tabel sessions --}}
  <div class="overflow-auto">
    <table class="w-full text-sm" x-ref="table">
      <thead>
        <tr class="text-left text-[var(--muted)]">
          <th class="py-2 pr-3 w-[18%]">Username</th>
          <th class="py-2 pr-3 w-[16%]">Mikrotik</th>
          <th class="py-2 pr-3 w-[14%]">Client IP</th>
          <th class="py-2 pr-3 w-[18%]">Start</th>
          <th class="py-2 pr-3 w-[10%]">Uptime</th>
          <th class="py-2 pr-3 w-[12%]">Download</th>
          <th class="py-2 pr-3 w-[12%]">Upload</th>
          <th class="py-2 pr-3 w-[10%]">Source</th>
        </tr>
      </thead>
      <tbody id="sess-body">
        @forelse($sess as $s)
          @php
            $mk    = $mkByHost[trim($s->nasipaddress ?? '')] ?? ($s->nasipaddress ?? '—');
            $ip    = $s->framedipaddress ?? '—';
            $start = $s->acctstarttime ?? '—';
            $upt   = $humanSecs($s->acctsessiontime ?? null);
            $dl    = $humanBytes($s->acctoutputoctets ?? null);
            $ul    = $humanBytes($s->acctinputoctets  ?? null);
            $src   = strtolower($s->source ?? '') === 'router' ? 'Router' : 'RADIUS';
          @endphp
          <tr class="border-t border-[var(--line)]">
            <td class="py-2 pr-3 align-top text-slate-100">{{ $s->username }}</td>
            <td class="py-2 pr-3 align-top">{{ $mk }}</td>
            <td class="py-2 pr-3 align-top">{{ $ip }}</td>
            <td class="py-2 pr-3 align-top">{{ $start }}</td>
            <td class="py-2 pr-3 align-top">{{ $upt }}</td>
            <td class="py-2 pr-3 align-top">{{ $dl }}</td>
            <td class="py-2 pr-3 align-top">{{ $ul }}</td>
            <td class="py-2 pr-3 align-top">
              <span class="inline-block px-2 py-0.5 rounded text-[10px] {{ $src==='Router' ? 'bg-sky-900/40 text-sky-200 border border-sky-800' : 'bg-emerald-900/40 text-emerald-200 border border-emerald-800' }}">
                {{ $src }}
              </span>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="py-6 text-center text-[var(--muted)]">Tidak ada sesi aktif.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3 text-[11px] text-[var(--muted)]">
    *Jika <b>Filter Perangkat = semua</b>, tabel hanya menampilkan data dari <b>RADIUS (radacct)</b>.
    Untuk melihat sesi langsung dari router, pilih perangkat tertentu.
  </div>
</div>

@push('scripts')
<script>
function sessUi(){
  return {
    auto: false,
    _timer: null,
    async fetchAndRender(){
      const mk = this.$refs.selMik.value || '';
      const q  = this.$refs.inpQ.value || '';
      const url = new URL(@json(route('radius.sessions.json')));
      if(mk) url.searchParams.set('mikrotik_id', mk);
      if(q)  url.searchParams.set('q', q);
      // cache buster kecil
      url.searchParams.set('_', Date.now());
      try{
        const res = await fetch(url.toString(), {
          headers: {'X-Requested-With': 'XMLHttpRequest'},
          cache: 'no-store'
        });
        if(!res.ok) return;
        const data = await res.json();
        const body = document.getElementById('sess-body');
        body.innerHTML = '';
        if(!data.rows || !data.rows.length){
          body.innerHTML = `<tr><td colspan="8" class="py-6 text-center text-[var(--muted)]">Tidak ada sesi aktif.</td></tr>`;
          return;
        }
        for(const r of data.rows){
          const pill = r.source === 'router'
            ? 'bg-sky-900/40 text-sky-200 border border-sky-800'
            : 'bg-emerald-900/40 text-emerald-200 border border-emerald-800';
          body.insertAdjacentHTML('beforeend', `
            <tr class="border-t border-[var(--line)]">
              <td class="py-2 pr-3 align-top text-slate-100">${escapeHtml(r.username||'')}</td>
              <td class="py-2 pr-3 align-top">${escapeHtml(r.mikrotik_name||'—')}</td>
              <td class="py-2 pr-3 align-top">${escapeHtml(r.framedipaddress||'—')}</td>
              <td class="py-2 pr-3 align-top">${escapeHtml(r.acctstarttime||'—')}</td>
              <td class="py-2 pr-3 align-top">${humanSecs(r.acctsessiontime)}</td>
              <td class="py-2 pr-3 align-top">${humanBytes(r.acctoutputoctets)}</td>
              <td class="py-2 pr-3 align-top">${humanBytes(r.acctinputoctets)}</td>
              <td class="py-2 pr-3 align-top">
                <span class="inline-block px-2 py-0.5 rounded text-[10px] ${pill}">
                  ${r.source==='router'?'Router':'RADIUS'}
                </span>
              </td>
            </tr>
          `);
        }
      }catch(e){
        // diamkan
      }
    },
    start(){
      if(this._timer) clearInterval(this._timer);
      this._timer = setInterval(()=>this.fetchAndRender(), 8000);
    },
    stop(){ if(this._timer) { clearInterval(this._timer); this._timer=null; } },
    init(){
      this.$watch('auto', v => { v ? (this.fetchAndRender(), this.start()) : this.stop(); });
    }
  }
}
function humanBytes(v){
  if(v===null || v===undefined || v==='') return '—';
  v = parseFloat(v);
  const u = ['B','KB','MB','GB','TB'];
  let i=0; while(v>=1024 && i<u.length-1){ v/=1024; i++; }
  return (i?v.toFixed(2):Math.round(v))+' '+u[i];
}
function humanSecs(s){
  if(s===null || s===undefined || s==='') return '—';
  s = parseInt(s,10);
  const d=Math.floor(s/86400); s%=86400;
  const h=Math.floor(s/3600);  s%=3600;
  const m=Math.floor(s/60);    s%=60;
  const out=[];
  if(d) out.push(d+'d'); if(h) out.push(h+'h'); if(m) out.push(m+'m'); if(s && !d) out.push(s+'s');
  return out.length?out.join(' '):'0s';
}
function escapeHtml(x){ return (x||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
</script>
@endpush
@endsection

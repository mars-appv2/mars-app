@extends('layouts.app')
@section('title','Dashboard')

@section('content')
@php
  function rupiah($v){ return 'Rp '.number_format((int)$v,0,',','.'); }
@endphp

<div class="grid lg:grid-cols-3 gap-4">
  {{-- ===== STATUS RADIUS ===== --}}
  <div class="m-card p-5">
    <div class="text-xs tracking-wider text-[var(--muted)] mb-2">RADIUS</div>
    <div class="flex items-center justify-between">
      <div class="text-slate-200 font-semibold text-lg">RADIUS Server</div>
      @if($radiusUp)
        <span class="inline-flex items-center gap-2 text-emerald-300">
          <span class="w-2 h-2 rounded-full bg-emerald-400 shadow"></span> UP
        </span>
      @else
        <span class="inline-flex items-center gap-2 text-rose-300">
          <span class="w-2 h-2 rounded-full bg-rose-400 shadow"></span> DOWN
        </span>
      @endif
    </div>
    @unless($radiusUp)
      <div class="mt-2 text-xs text-[var(--muted)]">Host {{ env('RADIUS_HOST','127.0.0.1') }}:{{ env('RADIUS_AUTH_PORT',1812) }} — {{ $radiusErr }}</div>
    @endunless
  </div>

  {{-- ===== ACTIVE USERS ===== --}}
  <div class="m-card p-5">
    <div class="text-xs tracking-wider text-[var(--muted)] mb-2">USERS</div>
    <div class="text-slate-200 font-semibold text-lg">Aktif Sekarang</div>
    <div class="text-3xl mt-2 font-bold">{{ number_format($activeUsers) }}</div>
    <div class="text-xs text-[var(--muted)] mt-1">Jumlah sesi aktif (radacct)</div>
  </div>

  {{-- ===== BILLING TOTALS ===== --}}
  <div class="m-card p-5">
    <div class="text-xs tracking-wider text-[var(--muted)] mb-2">BILLING</div>
    <div class="flex items-center justify-between">
      <div>
        <div class="text-slate-200 font-semibold">Pendapatan</div>
        <div class="text-2xl font-bold mt-1">{{ rupiah($paidTotal) }}</div>
      </div>
      <div class="text-right">
        <div class="text-slate-200 font-semibold">Piutang</div>
        <div class="text-2xl font-bold mt-1">{{ rupiah($unpaidTotal) }}</div>
      </div>
    </div>
  </div>
</div>

{{-- ===== LIVE TRAFFIC ===== --}}
<div class="m-card p-5 mt-6">
  <div class="flex items-center justify-between mb-3">
    <div class="text-lg text-slate-200 font-semibold">Live Traffic</div>
    <div class="flex gap-2">
      <form id="pickForm" class="flex gap-2 items-center">
        <select id="selDevice" name="mikrotik_id" class="m-inp">
          @foreach($devices as $d)
            <option value="{{ $d->id }}" {{ $d->id==$selId?'selected':'' }}>
              {{ $d->name }} — {{ $d->host }}
            </option>
          @endforeach
        </select>
        <select id="selIface" class="m-inp">
          @foreach($interfaces as $n)
            <option value="{{ $n }}">{{ $n }}</option>
          @endforeach
        </select>
        <button type="button" id="btnRefresh" class="m-btn">Refresh</button>
      </form>
    </div>
  </div>

  <div class="text-xs text-[var(--muted)] mb-3">
    Grafik RX/TX (bps) — update tiap 2 detik. Gunakan dropdown untuk memilih perangkat & interface.
  </div>

  <canvas id="liveChart" height="100"></canvas>
</div>

{{-- ===== assets ===== --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const csrf = '{{ csrf_token() }}';
  const monxUrl = (id) => `{{ route('monx', ['id'=>'__ID__']) }}`.replace('__ID__', id);
  const ifaceListUrl = (id) => `{{ route('mikrotik.ifaces.json', ['mikrotik'=>'__ID__']) }}`.replace('__ID__', id);

  let chart, pollTimer = null, points = 60;

  function buildChart(){
    const ctx = document.getElementById('liveChart').getContext('2d');
    chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: Array(points).fill(''),
        datasets: [
          {label:'RX', data:Array(points).fill(0), borderWidth:1, tension:0.2},
          {label:'TX', data:Array(points).fill(0), borderWidth:1, tension:0.2},
        ]
      },
      options: {
        responsive: true,
        interaction: { mode:'index', intersect:false },
        plugins: { legend:{ labels:{ color:'#c7d2fe' } } },
        scales: {
          x: { ticks:{ color:'#8ea0b3' }, grid:{ color:'rgba(255,255,255,0.04)' } },
          y: { ticks:{ color:'#8ea0b3', callback:v=>formatBps(v) }, grid:{ color:'rgba(255,255,255,0.04)' } }
        }
      }
    });
  }

  function formatBps(v){
    if (v>=1e9) return (v/1e9).toFixed(2)+' Gbps';
    if (v>=1e6) return (v/1e6).toFixed(2)+' Mbps';
    if (v>=1e3) return (v/1e3).toFixed(2)+' Kbps';
    return v+' bps';
  }

  function pushData(rx, tx){
    const ds = chart.data.datasets;
    ds[0].data.push(rx); ds[0].data.shift();
    ds[1].data.push(tx); ds[1].data.shift();
    chart.update('none');
  }

  async function fetchIfaces(id){
    try {
      const res = await fetch(ifaceListUrl(id));
      const arr = await res.json();
      const sel = document.getElementById('selIface');
      sel.innerHTML = '';
      arr.forEach(n => {
        const opt = document.createElement('option'); opt.value=n; opt.textContent=n; sel.appendChild(opt);
      });
    } catch(e){}
  }

  async function poll(){
    const id = document.getElementById('selDevice').value;
    const iface = document.getElementById('selIface').value;
    try{
      const res = await fetch(monxUrl(id), {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ iface })
      });
      const j = await res.json();
      pushData(j.rx||0, j.tx||0);
    }catch(e){
      pushData(0,0);
    }
  }

  function startPolling(){
    if (pollTimer) clearInterval(pollTimer);
    // reset chart
    chart.data.datasets[0].data = Array(points).fill(0);
    chart.data.datasets[1].data = Array(points).fill(0);
    chart.update();
    pollTimer = setInterval(poll, 2000);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    buildChart();

    const selDev = document.getElementById('selDevice');
    const btnRefresh = document.getElementById('btnRefresh');

    selDev.addEventListener('change', async () => {
      await fetchIfaces(selDev.value);
      startPolling();
    });

    btnRefresh.addEventListener('click', () => startPolling());

    // inisialisasi pertama
    if (!document.getElementById('selIface').options.length) {
      await fetchIfaces(selDev.value);
    }
    startPolling();
  });
</script>
@endsection

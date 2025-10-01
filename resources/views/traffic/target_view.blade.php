@extends('layouts.app')
@section('title','Traffic – Rekaman')

@section('content')
<div class="flex items-center justify-between mb-3">
  <h1 class="text-xl font-semibold text-slate-100">Traffic – {{ $target }}</h1>
  <div class="flex items-center gap-4 text-sm">
    <a class="underline" href="{{ route('traffic.export.pdf', ['mikrotik_id'=>$mikrotik_id,'target'=>$target]) }}">Download PDF</a>
    <a class="underline" href="{{ route('traffic.targets') }}">Kembali</a>
  </div>
</div>

{{-- Range selector --}}
<div id="rangeTabs" class="flex items-center gap-3 mb-3">
  @php $ranges = ['1h'=>'1 Jam','24h'=>'24 Jam','7d'=>'7 Hari','30d'=>'30 Hari','365d'=>'1 Tahun','all'=>'Lifetime']; @endphp
  @foreach($ranges as $k=>$label)
    <button data-range="{{ $k }}"
      class="px-2.5 py-1 rounded-lg border border-[var(--line)] text-sm hover:bg-white/5 {{ $loop->first ? 'bg-white/10' : '' }}">
      {{ $label }}
    </button>
  @endforeach
  <div id="liveBadge" class="ml-2 text-xs text-slate-300 hidden">
    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse align-middle"></span>
    <span class="ml-1 align-middle">Live</span>
  </div>
</div>

{{-- Stat ringkas (aktif + 1 jam) --}}
<div class="grid md:grid-cols-3 gap-3 mb-4">
  <div class="card p-3">
    <div class="text-[var(--muted)] text-xs">Uptime (1 Jam)</div>
    <div class="text-lg font-semibold"><span id="uptimePct">-</span></div>
  </div>
  <div class="card p-3">
    <div class="text-[var(--muted)] text-xs">Downtime (1 Jam)</div>
    <div class="text-lg font-semibold"><span id="downtimePct">-</span></div>
  </div>
  <div class="card p-3">
    <div class="text-[var(--muted)] text-xs">Sampel & Rata-rata (range aktif)</div>
    <div class="text-sm">
      Sampel: <span id="samples">0</span> •
      Avg RX: <span id="avgRx">0</span> Mbps •
      Avg TX: <span id="avgTx">0</span> Mbps
    </div>
  </div>
</div>

{{-- Grafik --}}
<div class="card p-3">
  <div id="chartWrap" class="relative h-[360px]">
    <canvas id="chart" class="absolute inset-0 w-full h-full"></canvas>
    <div id="emptyHint" class="absolute inset-0 hidden flex items-center justify-center text-sm text-slate-400">
      Belum ada data untuk rentang ini.
    </div>
  </div>
</div>

{{-- Rangkuman semua rentang --}}
<div class="card p-3 mt-4">
  <div class="label mb-2">Rangkuman</div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-[var(--muted)]">
        <tr>
          <th class="text-left p-2">Rentang</th>
          <th class="text-right p-2">Samples</th>
          <th class="text-right p-2">Avg RX</th>
          <th class="text-right p-2">Avg TX</th>
          <th class="text-right p-2">Max RX</th>
          <th class="text-right p-2">Max TX</th>
          <th class="text-right p-2">Uptime</th>
          <th class="text-right p-2">Downtime</th>
        </tr>
      </thead>
      <tbody id="summaryBody"></tbody>
    </table>
  </div>
</div>

{{-- Chart.js + adapter tanggal --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
<script>
(() => {
  const TARGET = @json($target);
  const IS_IP_TARGET = /^(\d{1,3}\.){3}\d{1,3}(\/\d{1,2})?$/.test(TARGET);
  const QUEUE_NAME = 'md-' + TARGET.replace(/\/\d+$/,'');
  

  const $  = (q)=>document.querySelector(q);
  const $$ = (q)=>Array.from(document.querySelectorAll(q));
  
  const refreshMs = 2000;     // refresh tiap 2 detik
  let currentRange = '1h';
  let timer = null;           // refresh series
  let liveTimer = null;       // polling live /monx
  let chart;

  const ctx  = document.getElementById('chart').getContext('2d');
  const tabs = $$('#rangeTabs [data-range]');
  const CSRF = @json(csrf_token());
  const PARAMS = { mikrotik_id: {{ (int)$mikrotik_id }}, target: @json($target) };
  const parseTS = s => new Date(s.replace(' ', 'T')+'Z');

  // ---- autoscale Y (Mbps) sampai 100 Gbps ----
  const CAPS = [100,200,500,1000,2000,5000,10000,20000,50000,100000]; // Mbps
  function pickYAxisMax(maxMbps) {
    if (!isFinite(maxMbps) || maxMbps <= 0) return 100;
    const target = maxMbps * 1.1;
    for (const cap of CAPS) if (target <= cap) return cap;
    return 100000;
  }
  function unitFor(maxMbps) { return maxMbps >= 1000 ? 'Gbps' : 'Mbps'; }
  function fmtTick(valMbps, unit) {
    const v = unit==='Gbps' ? valMbps/1000 : valMbps;
    return (unit==='Gbps'
      ? v.toLocaleString('id-ID',{maximumFractionDigits:2})
      : v.toLocaleString('id-ID',{maximumFractionDigits:0})
    ) + ' ' + unit;
  }
  const mMbps = n => (n/1_000_000).toFixed(2).replace(/\.00$/,'');

  function buildUrl(name, params) {
    const base = name==='series' ? @json(route('traffic.series')) : @json(route('traffic.summary'));
    const u = new URL(base, window.location.origin);
    for (const k in params) u.searchParams.set(k, params[k]);
    return u;
  }

  async function fetchSeries(range) {
    const res = await fetch(buildUrl('series',{
      mikrotik_id: PARAMS.mikrotik_id,
      target: PARAMS.target,
      range
    }), { cache: 'no-store' });
    return res.ok ? (await res.json()).series || [] : [];
  }

  async function fetchSummary() {
    const res = await fetch(buildUrl('summary', {
      mikrotik_id: PARAMS.mikrotik_id,
      target: PARAMS.target
    }), { cache: 'no-store' });
    return res.ok ? (await res.json()).summary || {} : {};
  }

  function renderSummaryTable(sum) {
    const labelMap = { '1h':'1 Jam','24h':'24 Jam','7d':'7 Hari','30d':'30 Hari','365d':'1 Tahun','all':'Lifetime' };
    const order    = ['1h','24h','7d','30d','365d','all'];
    const tbody = $('#summaryBody');
    tbody.innerHTML = '';
    for (const key of order) {
      const r = sum[key]; if (!r) continue;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="p-2">${labelMap[key]}</td>
        <td class="p-2 text-right">${(r.samples||0).toLocaleString('id-ID')}</td>
        <td class="p-2 text-right">${mMbps(r.avg_rx||0)} Mbps</td>
        <td class="p-2 text-right">${mMbps(r.avg_tx||0)} Mbps</td>
        <td class="p-2 text-right">${mMbps(r.max_rx||0)} Mbps</td>
        <td class="p-2 text-right">${mMbps(r.max_tx||0)} Mbps</td>
        <td class="p-2 text-right">${(r.uptimePct||0).toFixed(1)}%</td>
        <td class="p-2 text-right">${(r.downtimePct||0).toFixed(1)}%</td>
      `;
      tbody.appendChild(tr);
    }
  }

  async function updateSummaryPeriodically(){
    const s = await fetchSummary();
    renderSummaryTable(s);
  }

  async function updateDowntime1h(){
    const r1h = await fetchSeries('1h');
    const expected = 60; // 60 menit
    const nonEmpty = r1h.length;
    const zeroBuckets = r1h.filter(r => (+r.rx<=0 && +r.tx<=0)).length;
    const down = Math.max(0, (expected - nonEmpty) + zeroBuckets);
    const up   = Math.max(0, expected - down);
    const upPct = expected ? (up/expected*100) : 0;
    const downPct = 100 - upPct;
    $('#uptimePct').textContent   = upPct.toFixed(1) + '%';
    $('#downtimePct').textContent = downPct.toFixed(1) + '%';
  }

  // ====== LIVE FEED (1h) ======
  const liveLabels = [], liveRxMbps = [], liveTxMbps = [];
  const LIVE_KEEP = 600; // ~20 menit data @ 2 detik

  async function pollLiveOnce(){
    try{
      let res;
      if (IS_IP_TARGET) {
        res = await fetch(`/monq/{{ (int)$mikrotik_id }}`, {
          method:'POST',
          headers:{ 'X-CSRF-TOKEN': @json(csrf_token()), 'Content-Type':'application/json', 'Accept':'application/json' },
          body: JSON.stringify({ queue: QUEUE_NAME })
        });
      } else {
        res = await fetch(`/monx/{{ (int)$mikrotik_id }}`, {
          method:'POST',
          headers:{ 'X-CSRF-TOKEN': @json(csrf_token()), 'Content-Type':'application/json', 'Accept':'application/json' },
          body: JSON.stringify({ iface: TARGET })
        });
      }
      if(!res.ok) return;
      const j = await res.json();
      const now = new Date();
      liveLabels.push(now);
      liveRxMbps.push(((+j.rx)||0)/1_000_000);
      liveTxMbps.push(((+j.tx)||0)/1_000_000);
      if (liveLabels.length > LIVE_KEEP) { liveLabels.shift(); liveRxMbps.shift(); liveTxMbps.shift(); }
      if (currentRange === '1h') drawLive();
    }catch(e){}
  }

  function drawLive(){
    const maxMbps = Math.max(0, ...liveRxMbps, ...liveTxMbps);
    const yMax = pickYAxisMax(maxMbps);
    const unit = unitFor(yMax);
    const hint = $('#emptyHint');
    if (!liveLabels.length) hint.classList.remove('hidden'); else hint.classList.add('hidden');

    if (!chart){
      chart = new Chart(ctx, {
        type: 'line',
        data: { labels: liveLabels, datasets: [
          { label:`RX (${unit})`, data: liveRxMbps, tension:.2, borderWidth:2, pointRadius:0 },
          { label:`TX (${unit})`, data: liveTxMbps, tension:.2, borderWidth:2, pointRadius:0 },
        ]},
        options: {
          animation:{duration:150}, responsive:true, maintainAspectRatio:false,
          scales:{ x:{type:'time', time:{unit:'second'}}, y:{beginAtZero:true,min:0,max:yMax,ticks:{callback:(v)=>fmtTick(v,unit)}}},
          plugins:{ legend:{display:true,position:'bottom'}, tooltip:{callbacks:{label:(c)=>`${c.dataset.label.split(' (')[0]}: ${fmtTick(c.raw??0,unit)}`}}}
        }
      });
      return;
    }
    chart.data.labels = liveLabels;
    chart.data.datasets[0].data = liveRxMbps;
    chart.data.datasets[1].data = liveTxMbps;
    chart.data.datasets[0].label = `RX (${unit})`;
    chart.data.datasets[1].label = `TX (${unit})`;
    chart.options.scales.y.max = yMax;
    chart.options.scales.y.ticks.callback = (v)=>fmtTick(v,unit);
    chart.update('active');
  }

  // ====== HISTORICAL (range selain 1h) ======
  function drawHistorical(rows, range){
    const labels = rows.map(r => parseTS(r.t));
    const rxMbps = rows.map(r => (+r.rx||0)/1_000_000);
    const txMbps = rows.map(r => (+r.tx||0)/1_000_000);
    const maxMbps = Math.max(0, ...rxMbps, ...txMbps);
    const yMax = pickYAxisMax(maxMbps);
    const unit = unitFor(yMax);

    const hint = $('#emptyHint');
    if (!rows.length) hint.classList.remove('hidden'); else hint.classList.add('hidden');

    if (!chart){
      chart = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [
          { label:`RX (${unit})`, data: rxMbps, tension:.2, borderWidth:2, pointRadius:0 },
          { label:`TX (${unit})`, data: txMbps, tension:.2, borderWidth:2, pointRadius:0 },
        ]},
        options: {
          animation:{duration:300}, responsive:true, maintainAspectRatio:false,
          scales:{
            x:{ type:'time', time:{ unit:(range==='1h'||range==='24h')?'minute':(range==='365d'||range==='all'?'day':'hour') }},
            y:{ beginAtZero:true, min:0, max:yMax, ticks:{ callback:(v)=>fmtTick(v,unit) } }
          },
          plugins:{ legend:{display:true,position:'bottom'}, tooltip:{callbacks:{label:(c)=>`${c.dataset.label.split(' (')[0]}: ${fmtTick(c.raw??0,unit)}`}} }
        }
      });
      return;
    }
    chart.data.labels = labels;
    chart.data.datasets[0].data = rxMbps;
    chart.data.datasets[1].data = txMbps;
    chart.data.datasets[0].label = `RX (${unit})`;
    chart.data.datasets[1].label = `TX (${unit})`;
    chart.options.scales.x.time.unit = (range==='1h'||range==='24h')?'minute':(range==='365d'||range==='all'?'day':'hour');
    chart.options.scales.y.max = yMax;
    chart.options.scales.y.ticks.callback = (v)=>fmtTick(v,unit);
    chart.update('active');
  }

  async function load(range){
    currentRange = range;

    // tab state
    tabs.forEach(b => b.classList.remove('bg-white/10'));
    (tabs.find(b => b.dataset.range===range) || tabs[0]).classList.add('bg-white/10');

    // stop timer
    if (timer) clearInterval(timer);
    if (liveTimer) clearInterval(liveTimer);

    if (range === '1h') {
      $('#liveBadge').classList.remove('hidden');
      if (chart) { chart.destroy(); chart = null; }
      liveLabels.length = liveRxMbps.length = liveTxMbps.length = 0;
      await pollLiveOnce();
      liveTimer = setInterval(pollLiveOnce, {{ 2000 }});
      updateDowntime1h();
      timer = setInterval(updateDowntime1h, 60000);
    } else {
      $('#liveBadge').classList.add('hidden');
      const redraw = async ()=>{
        const rows = await fetchSeries(range);
        drawHistorical(rows, range);
        // stats untuk range aktif
        const avgRx = rows.length ? rows.reduce((a,b)=>a+(+b.rx||0),0)/rows.length : 0;
        const avgTx = rows.length ? rows.reduce((a,b)=>a+(+b.tx||0),0)/rows.length : 0;
        $('#samples').textContent = rows.length;
        $('#avgRx').textContent = mMbps(avgRx);
        $('#avgTx').textContent = mMbps(avgTx);
        updateDowntime1h();
      };
      await redraw();
      timer = setInterval(redraw, {{ 2000 }});
    }

    // refresh rangkuman semua rentang tiap 30 detik
    updateSummaryPeriodically();
    setTimeout(updateSummaryPeriodically, 30000);
  }

  // wiring
  tabs.forEach(b => b.addEventListener('click', () => load(b.dataset.range)));

  // initial
  load('1h');
})();
</script>
@endsection

@extends('layouts.app')
@section('title','Traffic – Detail')
@section('content')
<div class="card p-4">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div>
      <div class="label">Target</div>
      <div class="text-lg font-semibold">{{ $target->target_type }} — {{ $target->target_key }}</div>
    </div>
    <div class="flex items-center gap-2">
      <form>
        <select name="range" onchange="this.form.submit()" class="field">
          <option value="day"   {{ $range==='day'?'selected':'' }}>Hari ini</option>
          <option value="week"  {{ $range==='week'?'selected':'' }}>Mingguan</option>
          <option value="month" {{ $range==='month'?'selected':'' }}>Bulanan</option>
          <option value="year"  {{ $range==='year'?'selected':'' }}>Tahunan</option>
        </select>
      </form>
      <a class="btn-primary px-3 py-2 rounded-lg" href="{{ route('traffic.targets.export',[$target,'range'=>$range]) }}">Export CSV</a>
    </div>
  </div>
  <canvas id="line" height="240" class="mt-4"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels = @json($labels); const rx = @json($rx); const tx = @json($tx);
const ctx = document.getElementById('line').getContext('2d');
const g1 = ctx.createLinearGradient(0,0,0,260); g1.addColorStop(0,'rgba(34,211,238,.35)'); g1.addColorStop(1,'rgba(34,211,238,0)');
const g2 = ctx.createLinearGradient(0,0,0,260); g2.addColorStop(0,'rgba(124,58,237,.35)'); g2.addColorStop(1,'rgba(124,58,237,0)');
new Chart(ctx,{type:'line',data:{labels,datasets:[
  {label:'RX (bytes/s)',data:rx,borderColor:'#22d3ee',backgroundColor=g1,fill:true,pointRadius:0,tension:.35},
  {label:'TX (bytes/s)',data:tx,borderColor:'#7c3aed',backgroundColor=g2,fill:true,pointRadius:0,tension:.35},
]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},
scales:{x:{grid:{color:'rgba(255,255,255,.06)'}},y:{grid:{color:'rgba(255,255,255,.06)'}}},
plugins:{legend:{display:true}}});
</script>
@endsection

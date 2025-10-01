@extends('client.layouts.app')
@section('title','Traffic — Client')

@section('content')
<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
    <div style="font-weight:600">Traffic 24 Jam</div>
    <div class="stat-label">Download vs Upload (Mbps)</div>
  </div>
  <canvas id="traf" height="120"></canvas>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(async function(){
  const res = await fetch(@json(route('client.traffic.data')));
  const data = await res.json();
  const ctx = document.getElementById('traf');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.labels,
      datasets: [
        {label:'Download (Mbps)', data:data.down, tension:.35},
        {label:'Upload (Mbps)', data:data.up, tension:.35}
      ]
    },
    options: {
      responsive: true,
      plugins: { legend: { labels:{ color:'#b7c7ea' } } },
      scales: {
        x: { ticks: { color:'#9fb2da' }, grid: { color:'rgba(255,255,255,.05)'} },
        y: { ticks: { color:'#9fb2da' }, grid: { color:'rgba(255,255,255,.05)'} }
      }
    }
  });
})();
</script>
@endsection

@extends('staff.layouts.app')
@section('title','NOC — Staff')

@section('content')
<div class="grid g2">
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div style="font-weight:600">Sesi per Jam (24h)</div>
      <div class="stat-label">Total sesi baru tiap jam</div>
    </div>
    <canvas id="nocChart" height="120"></canvas>
  </div>

  <div class="card">
    <div style="font-weight:600;margin-bottom:8px">Top 10 Sesi Aktif</div>
    @if(empty($sessions) || count($sessions)===0)
      <div class="stat-label">Tidak ada data sesi aktif.</div>
    @else
      <table class="table">
        <thead><tr>
          <th>Username</th><th>IP</th><th>Caller</th><th>Durasi</th><th>NAS</th>
        </tr></thead>
        <tbody>
        @foreach($sessions as $s)
          <tr>
            <td>{{ $s->username }}</td>
            <td>{{ $s->framedipaddress }}</td>
            <td>{{ $s->callingstationid }}</td>
            <td>{{ gmdate('H:i:s', (int)$s->acctsessiontime) }}</td>
            <td>{{ $s->nasipaddress }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(async function(){
  const res = await fetch(@json(route('staff.noc.sessions')));
  const data = await res.json();
  new Chart(document.getElementById('nocChart'), {
    type: 'line',
    data: { labels: data.labels, datasets: [{label:'Sesi', data:data.values, tension:.35}] },
    options: {
      plugins:{ legend:{ labels:{ color:'#b7c7ea' } } },
      scales:{ x:{ ticks:{ color:'#9fb2da' }}, y:{ ticks:{ color:'#9fb2da' }}}
    }
  });
})();
</script>
@endsection

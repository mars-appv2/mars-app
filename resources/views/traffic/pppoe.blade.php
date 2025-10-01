@extends('layouts.app')
@section('title','Traffic — PPPoE IP Public')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="flex items-center justify-between">
    <div class="text-lg text-slate-200 font-semibold">PPPoE IP Public (Top 100 • {{ $date }})</div>
    <form method="POST" action="{{ route('traffic.save') }}">
      @csrf
      <input type="hidden" name="type" value="pppoe">
      <input type="hidden" id="pngPayload" name="png">
      <button class="m-btn" onclick="capture()">Simpan PNG</button>
    </form>
  </div>
</div>
<div id="capArea" class="m-card p-4"><canvas id="chart" height="120"></canvas></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
const rows=@json($rows);const labels=rows.map(r=>r.host_ip);const data=rows.map(r=>Number(r.bytes));
new Chart(document.getElementById('chart'),{type:'bar',data:{labels,datasets:[{label:'Bytes',data}]},options:{responsive:true,maintainAspectRatio:false}});
async function capture(){const a=document.getElementById('capArea');const c=await html2canvas(a);document.getElementById('pngPayload').value=c.toDataURL('image/png');}
</script>
@endsection
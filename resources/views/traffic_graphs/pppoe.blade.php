@extends('layouts.app')
@section('title','Traffic — PPPoE IP Public')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="flex items-center justify-between">
    <div class="text-lg text-slate-200 font-semibold">PPPoE IP Public (Top 50 • {{ $date }})</div>
    <form method="POST" action="{{ route('traffic.graphs.save') }}">
      @csrf
      <input type="hidden" name="type" value="pppoe">
      <input type="hidden" id="pngPayload" name="png">
      <button class="m-btn" onclick="capture()">Simpan PNG</button>
    </form>
  </div>
</div>
<div id="capArea" class="grid grid-cols-1 md:grid-cols-2 gap-4">
  @foreach($rows as $r)
    @php $key = preg_replace('/[^A-Za-z0-9_.-]+/','_', $r->host_ip); @endphp
    <div class="m-card p-2">
      <div class="text-xs text-slate-400 mb-2">{{ $r->host_ip }}</div>
      <div class="grid grid-cols-2 gap-2">
        <img src="{{ route('traffic.graphs.img',['path'=>"traffic/pppoe/png/$key/day.png"]) }}" class="rounded-xl">
        <img src="{{ route('traffic.graphs.img',['path'=>"traffic/pppoe/png/$key/week.png"]) }}" class="rounded-xl">
        <img src="{{ route('traffic.graphs.img',['path'=>"traffic/pppoe/png/$key/month.png"]) }}" class="rounded-xl">
        <img src="{{ route('traffic.graphs.img',['path'=>"traffic/pppoe/png/$key/year.png"]) }}" class="rounded-xl">
      </div>
    </div>
  @endforeach
</div>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>async function capture(){const a=document.getElementById('capArea');const c=await html2canvas(a);document.getElementById('pngPayload').value=c.toDataURL('image/png');}</script>
@endsection
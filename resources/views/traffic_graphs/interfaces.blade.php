@extends('layouts.app')
@section('title','Traffic — Interfaces')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="flex items-center justify-between">
    <div class="text-lg text-slate-200 font-semibold">Interface Graph Sets (Day/Week/Month/Year)</div>
    <form method="POST" action="{{ route('traffic.graphs.save') }}">
      @csrf
      <input type="hidden" name="type" value="interfaces">
      <input type="hidden" id="pngPayload" name="png">
      <button class="m-btn" onclick="capture()">Simpan PNG</button>
    </form>
  </div>
</div>
<div id="capArea" class="grid grid-cols-1 md:grid-cols-2 gap-4">
  @foreach($files as $f)
    <div class="m-card p-2">
      <div class="text-xs text-slate-400 mb-2">{{ $f['base'] }}</div>
      <div class="grid grid-cols-2 gap-2">
        <img src="{{ route('traffic.graphs.img',['path'=>$f['day']]) }}" class="w-full rounded-xl" alt="day">
        <img src="{{ route('traffic.graphs.img',['path'=>$f['week']]) }}" class="w-full rounded-xl" alt="week">
        <img src="{{ route('traffic.graphs.img',['path'=>$f['month']]) }}" class="w-full rounded-xl" alt="month">
        <img src="{{ route('traffic.graphs.img',['path'=>$f['year']]) }}" class="w-full rounded-xl" alt="year">
      </div>
    </div>
  @endforeach
</div>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>async function capture(){const a=document.getElementById('capArea');const c=await html2canvas(a);document.getElementById('pngPayload').value=c.toDataURL('image/png');}</script>
@endsection
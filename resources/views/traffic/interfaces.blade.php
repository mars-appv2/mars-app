@extends('layouts.app')
@section('title','Traffic — Interfaces')
@section('content')
<div class="m-card p-5 mb-4">
  <div class="flex items-center justify-between">
    <div class="text-lg text-slate-200 font-semibold">Interface Graphs (RRD)</div>
    <form method="POST" action="{{ route('traffic.save') }}">
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
      <img src="{{ route('traffic.img',['path'=>$f]) }}" class="w-full rounded-xl" alt="graph">
      <div class="text-xs text-slate-400 mt-2">{{ $f }}</div>
    </div>
  @endforeach
</div>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>async function capture(){const a=document.getElementById('capArea');const c=await html2canvas(a);document.getElementById('pngPayload').value=c.toDataURL('image/png');}</script>
@endsection
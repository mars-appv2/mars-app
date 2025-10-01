@extends('layouts.app')
@section('title','Traffic — Dashboard')
@section('content')
<div class="m-card p-5">
  <div class="text-xl text-slate-200 font-semibold mb-4">Traffic Monitoring</div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <a href="{{ route('traffic.interfaces') }}" class="m-btnp">Interface Graphs (RRD)</a>
    <a href="{{ route('traffic.pppoe') }}" class="m-btnp">PPPoE IP Public</a>
    <a href="{{ route('traffic.content') }}" class="m-btnp">Content Categories</a>
  </div>
</div>
@endsection
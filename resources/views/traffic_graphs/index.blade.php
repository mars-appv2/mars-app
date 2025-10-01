@extends('layouts.app')
@section('title','Traffic — Graph Sets')
@section('content')
<div class="m-card p-5">
  <div class="text-xl text-slate-200 font-semibold mb-4">Traffic Graphs</div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <a href="{{ route('traffic.graphs.interfaces') }}" class="m-btnp">Interface Graphs</a>
    <a href="{{ route('traffic.graphs.pppoe') }}" class="m-btnp">PPPoE IP Public</a>
    <a href="{{ route('traffic.graphs.content') }}" class="m-btnp">Content Apps</a>
  </div>
</div>
@endsection